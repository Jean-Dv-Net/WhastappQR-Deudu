<?php

namespace App\Observers;

use App\Models\CampaignRecord;
use App\Models\CampaignStatistic;
use App\Models\Message;
use App\StateMachines\CampaignRecordStateMachine;
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
        // Only process messages linked to a campaign record
        $messageInfo = Message::find($message->getId());
        $metadata = $messageInfo->metadata;
        $type = $messageInfo->type;
        $campaignRecordId = $metadata['campaign_record'] ?? null;

        if (!$campaignRecordId) {
            return;
        }

        // Only care about changes to the delivery field
        if (!$message->wasChanged('delivery')) {
            return;
        }

        // TODO: Warning we check here if the type is document, if it is, we don't increment the statistics
        if ($type === "document") {
            return;
        }

        // Detect state transitions
        $originalDelivery = $this->extractOriginalDelivery($message->getOriginal('delivery'));
        $currentDelivery  = $message->getDelivery();

        $becameDelivered = $originalDelivery['delivered_at'] === null && $currentDelivery->deliveredAt() !== null;
        $becameRead      = $originalDelivery['read_at'] === null && $currentDelivery->readAt() !== null;

        if (!$becameDelivered && !$becameRead) {
            return;
        }

        $record = CampaignRecord::find($campaignRecordId);

        if (!$record) {
            Log::warning('[MessageDeliveryObserver] Campaign record not found', [
                'message_uuid'       => $message->getMessageUuid(),
                'campaign_record_id' => $campaignRecordId,
            ]);
            return;
        }

        $statistic = CampaignStatistic::where('campaign_id', $record->campaign_id)->first();

        if (!$statistic) {
            Log::warning('[MessageDeliveryObserver] Campaign statistic not found', [
                'campaign_id' => (string) $record->campaign_id,
            ]);
            return;
        }

        if ($becameRead) {
            $this->handleReadTransition($record, $statistic, $message->getMessageUuid());
        } elseif ($becameDelivered) {
            $this->handleDeliveredTransition($record, $statistic, $message->getMessageUuid());
        }
    }

    // ----- Handlers -----
    private function handleReadTransition(
        CampaignRecord    $record,
        CampaignStatistic $statistic,
        string            $messageUuid
    ): void {
        $previousStatus = $record->status;

        // Only increment if the record has not already reached "read"
        $transitioned = CampaignRecordStateMachine::transition($record, CampaignRecord::STATUS_READ);

        if ($transitioned) {
            $statistic->increment('read');

            // If the record jumped directly from sent → read, also count it as delivered
            // since the message was read before the delivery confirmation arrived
            if ($previousStatus === CampaignRecord::STATUS_SENT) {
                $statistic->increment('delivered');
            }

            Log::debug('[MessageDeliveryObserver] Record marked as read', [
                'campaign_record_id' => (string) $record->id,
                'message_uuid'       => $messageUuid,
                'previous_status'    => $previousStatus,
            ]);
        }
    }

    private function handleDeliveredTransition(
        CampaignRecord    $record,
        CampaignStatistic $statistic,
        string            $messageUuid
    ): void {
        // Only increment if the transition is valid (record was not already delivered or read)
        $transitioned = CampaignRecordStateMachine::transition($record, CampaignRecord::STATUS_DELIVERED);

        if ($transitioned) {
            $statistic->increment('delivered');

            Log::debug('[MessageDeliveryObserver] Record marked as delivered', [
                'campaign_record_id' => (string) $record->id,
                'message_uuid'       => $messageUuid,
            ]);
        }
    }

    // ----- Helpers ---------
    private function extractOriginalDelivery(mixed $original): array
    {
        if ($original instanceof \App\ValueObjects\Delivery) {
            return [
                'delivered_at' => $original->deliveredAt(),
                'read_at'      => $original->readAt(),
            ];
        }

        if (is_array($original)) {
            return [
                'delivered_at' => $original['delivered_at'] ?? $original['delivery']['delivered_at'] ?? null,
                'read_at'      => $original['read_at']      ?? $original['delivery']['read_at']      ?? null,
            ];
        }

        return ['delivered_at' => null, 'read_at' => null];
    }
}
