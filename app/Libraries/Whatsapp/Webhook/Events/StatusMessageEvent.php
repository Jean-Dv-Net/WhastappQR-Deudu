<?php

namespace App\Libraries\Whatsapp\Webhook\Events;

use App\ValueObjects\Delivery;

class StatusMessageEvent extends WebhookEvent
{
    /**
     * @var string $uuid The UUID of the status message.
     */
    public string $uuid;

    /**
     * Priority order for message statuses.
     * Higher value = higher priority.
     */
    private const array STATUS_PRIORITY = [
        'sent'      => 1,
        'delivered' => 2,
        'read'      => 3,
    ];

    /**
     * Process the status message event.
     *
     * @return void
     */
    public function process(): void
    {
        $message = (new \App\Models\Message())
            ->where('message_uuid', $this->uuid)
            ->firstOrFail([
                'id',
                'message_uuid',
                'status',
                'delivery',
            ]);

        $event = str_replace('message.', '', $this->eventType);

        /** @var \App\Models\Message $message */
        match ($event) {
            'sent' => $this->handleSentEvent($message, $event),
            'received' => $this->handleDeliveredEvent($message, $event),
            'read' => $this->handleReadEvent($message, $event),
            default => null,
        };

        $message->save();
    }

    /**
     * Determines whether the incoming event should update the current status.
     * Only upgrades are allowed (e.g. sent → delivered → read).
     */
    private function shouldUpdateStatus(string $currentStatus, string $newStatus): bool
    {
        $currentPriority = self::STATUS_PRIORITY[$currentStatus] ?? 0;
        $newPriority     = self::STATUS_PRIORITY[$newStatus]     ?? 0;

        return $newPriority > $currentPriority;
    }

    private function handleSentEvent(\App\Models\Message &$message, string $event): void
    {
        // We only updated if the status is lower priority than the current status
        if ($this->shouldUpdateStatus($message->getStatus(), "sent")) {
            $message->setStatus("sent");
        }

        // Always update the sentAt timestamp
        $message->setDelivery(new Delivery(
            sentAt: isset($this->timestamp) ? \Carbon\Carbon::parse($this->timestamp, 'UTC') : null,
            deliveredAt: $message->getDelivery()?->deliveredAt,
            readAt: $message->getDelivery()?->readAt
        ));
    }

    private function handleDeliveredEvent(\App\Models\Message &$message, string $event): void
    {
        // We only updated if the status is lower priority than the current status
        if ($this->shouldUpdateStatus($message->getStatus(), "delivered")) {
            $message->setStatus("delivered");
        }

        // Always update the deliveredAt timestamp
        $message->setDelivery(new Delivery(
            sentAt: $message->getDelivery()?->sentAt,
            deliveredAt: isset($this->timestamp) ? \Carbon\Carbon::parse($this->timestamp, 'UTC') : null,
            readAt: $message->getDelivery()?->readAt
        ));
    }

    private function handleReadEvent(\App\Models\Message &$message, string $event): void
    {
        // We only updated if the status is lower priority than the current status
        if ($this->shouldUpdateStatus($message->getStatus(), "read")) {
            $message->setStatus("read");
        }

        // Always update the readAt timestamp
        $message->setDelivery(new Delivery(
            sentAt: $message->getDelivery()?->sentAt,
            deliveredAt: $message->getDelivery()?->deliveredAt,
            readAt: isset($this->timestamp) ? \Carbon\Carbon::parse($this->timestamp, 'UTC') : null
        ));
    }
}
