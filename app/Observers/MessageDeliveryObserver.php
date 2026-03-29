<?php

namespace App\Observers;

use App\Models\CampaignRecord;
use App\Models\CampaignStatistic;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Message model to handle campaign statistics when delivery statuses change.
 * Specifically, it tracks when a message becomes delivered or read, updating the
 * corresponding campaign record status and campaign statistic counters.
 *
 * @package App\Observers
 */
class MessageDeliveryObserver
{
    /**
     * Priority order for message statuses to prevent downgrading.
     */
    private const array STATUS_PRIORITY = [
        CampaignRecord::STATUS_PENDING => 0,
        CampaignRecord::STATUS_READY => 1,
        CampaignRecord::STATUS_SENT => 2,
        CampaignRecord::STATUS_DELIVERED => 3,
        CampaignRecord::STATUS_READ => 4,
        CampaignRecord::STATUS_FAILED => 5,
    ];

    /**
     * Handle the Message "updated" event.
     */
    public function updated(Message $message): void
    {
        Log::info('MessageDeliveryObserver: Message updated', [
            'message_uuid' => $message->getMessageUuid(),
            'delivery' => $message->delivery,
            'message' => $message,
        ]);
        // Only process messages that belong to a campaign record
        $metadata = Message::find($message->getId())->metadata;
        $campaignRecordId = $metadata['campaign_record'] ?? null;
        if (!$campaignRecordId) {
            Log::info('MessageDeliveryObserver: Campaign record not found', [
                'message_uuid' => $message->getMessageUuid(),
                'metadata' => $metadata,
            ]);
            return;
        }

        Log::info('MessageDeliveryObserver: Campaign record found', [
            'campaign_record_id' => $campaignRecordId,
        ]);

        // We only care about changes to the delivery object
        if (!$message->wasChanged('delivery')) {
            return;
        }

        Log::info('MessageDeliveryObserver: Delivery changed', [
            'message_uuid' => $message->getMessageUuid(),
            'delivery' => $message->delivery,
        ]);

        $originalDelivery = $message->getOriginal('delivery');
        $currentDelivery = $message->getDelivery();

        Log::info('MessageDeliveryObserver: Original delivery', [
            'original_delivery' => $originalDelivery,
        ]);
        Log::info('MessageDeliveryObserver: Current delivery', [
            'current_delivery' => $currentDelivery,
        ]);

        // Extract original timestamps (handling array/object gracefully, since it's casted)
        $originalDeliveredAt = is_array($originalDelivery) ? ($originalDelivery['delivered_at'] ?? null) : null;
        if ($originalDelivery instanceof \App\ValueObjects\Delivery) {
             $originalDeliveredAt = $originalDelivery->deliveredAt();
        } elseif (isset($originalDelivery['delivery']['delivered_at'])) {
             $originalDeliveredAt = $originalDelivery['delivery']['delivered_at'];
        }

        $originalReadAt = is_array($originalDelivery) ? ($originalDelivery['read_at'] ?? null) : null;
        if ($originalDelivery instanceof \App\ValueObjects\Delivery) {
             $originalReadAt = $originalDelivery->readAt();
        } elseif (isset($originalDelivery['delivery']['read_at'])) {
             $originalReadAt = $originalDelivery['delivery']['read_at'];
        }

        Log::info('MessageDeliveryObserver: Original timestamps', [
            'original_delivered_at' => $originalDeliveredAt,
            'original_read_at' => $originalReadAt,
        ]);

        $nowDeliveredAt = $currentDelivery->deliveredAt();
        $nowReadAt = $currentDelivery->readAt();

        Log::info('MessageDeliveryObserver: Current timestamps', [
            'now_delivered_at' => $nowDeliveredAt,
            'now_read_at' => $nowReadAt,
        ]);

        // Detect transitions
        $becameDelivered = $originalDeliveredAt === null && $nowDeliveredAt !== null;
        $becameRead = $originalReadAt === null && $nowReadAt !== null;

        if (!$becameDelivered && !$becameRead) {
            return;
        }

        $record = CampaignRecord::find($campaignRecordId);

        Log::info('MessageDeliveryObserver: Campaign record', [
            'record' => $record,
        ]);

        if (!$record) {
            Log::warning('[MessageDeliveryObserver] Campaign record not found for message', [
                'message_uuid' => $message->getMessageUuid(),
                'campaign_record_id' => $campaignRecordId
            ]);
            return;
        }

        $statistic = CampaignStatistic::where('campaign_id', $record->campaign_id)->first();
        
        Log::info('MessageDeliveryObserver: Campaign statistic', [
            'statistic' => $statistic,
        ]);
        if (!$statistic) {
            Log::warning('[MessageDeliveryObserver] Campaign statistic not found', [
                'campaign_id' => (string)$record->campaign_id
            ]);
            return;
        }

        $currentStatusPriority = self::STATUS_PRIORITY[$record->status] ?? 0;

        Log::info('MessageDeliveryObserver: Current status priority', [
            'current_status_priority' => $currentStatusPriority,
        ]);

        // Process Read transition (Highest Priority)
        if ($becameRead) {
            $newStatusPriority = self::STATUS_PRIORITY[CampaignRecord::STATUS_READ];
            if ($newStatusPriority > $currentStatusPriority) {
                $record->update(['status' => CampaignRecord::STATUS_READ]);
            }
            $statistic->increment('read');

            Log::debug('[MessageDeliveryObserver] Campaign record marked as read', [
                'campaign_record_id' => (string)$record->id,
                'message_uuid' => $message->getMessageUuid()
            ]);
        }
        // Process Delivered transition
        elseif ($becameDelivered) {
            $newStatusPriority = self::STATUS_PRIORITY[CampaignRecord::STATUS_DELIVERED];
            if ($newStatusPriority > $currentStatusPriority) {
                $record->update(['status' => CampaignRecord::STATUS_DELIVERED]);
            }
            $statistic->increment('delivered');

            Log::debug('[MessageDeliveryObserver] Campaign record marked as delivered', [
                'campaign_record_id' => (string)$record->id,
                'message_uuid' => $message->getMessageUuid()
            ]);
        }
    }
}
