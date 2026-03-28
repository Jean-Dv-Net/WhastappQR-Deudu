<?php

namespace App\Jobs;

use App\Libraries\Whatsapp\Client as WhatsappClient;
use App\Models\Contact;
use App\Models\Message;
use App\ValueObjects\Delivery;
use App\ValueObjects\LastMessageEvent;
use App\ValueObjects\LastMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to sync messages for a specific WhatsApp conversation.
 *
 * This job is dispatched transparently whenever a user opens a conversation.
 * It fetches the full message history from the 2Chat API and inserts any
 * messages that are not yet stored in the local database, compensating for
 * webhook failures.
 *
 * The job is unique per channel+remote pair for 5 minutes to prevent
 * redundant API calls when multiple requests arrive for the same conversation.
 */
class SyncContactMessagesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job should be unique.
     * Prevents duplicate syncs for the same conversation pair.
     */
    public int $uniqueFor = 300; // 5 minutes

    /**
     * Constructor.
     *
     * @param string  $channelPhoneNumber  Channel phone number (e.g. +573224816062)
     * @param string  $remotePhoneNumber   Contact phone number (e.g. +573224220769)
     * @param Contact $contact             The contact model to update after sync
     */
    public function __construct(
        protected string $channelPhoneNumber,
        protected string $remotePhoneNumber,
        protected Contact $contact,
    ) {
        Log::debug('[SyncContactMessagesJob] Job created', [
            'channel_phone_number' => $channelPhoneNumber,
            'remote_phone_number'  => $remotePhoneNumber,
            'contact_id'           => $contact->getId(),
        ]);
    }

    /**
     * Unique job ID — one sync per conversation pair at a time.
     */
    public function uniqueId(): string
    {
        return 'sync-contact-messages-' . $this->channelPhoneNumber . '-' . $this->remotePhoneNumber;
    }

    /**
     * Execute the sync job.
     */
    public function handle(): void
    {
        Log::debug('[SyncContactMessagesJob] Starting sync', [
            'channel_phone_number' => $this->channelPhoneNumber,
            'remote_phone_number'  => $this->remotePhoneNumber,
        ]);

        try {
            // 1. Fetch messages from 2Chat API
            $client   = new WhatsappClient();
            $response = $client->getMessages($this->channelPhoneNumber, $this->remotePhoneNumber);

            $apiMessages = $response['messages'] ?? [];

            if (empty($apiMessages)) {
                Log::info('[SyncContactMessagesJob] No messages returned from API', [
                    'channel_phone_number' => $this->channelPhoneNumber,
                    'remote_phone_number'  => $this->remotePhoneNumber,
                ]);

                $this->updateContactSyncTimestamp();
                return;
            }

            Log::debug('[SyncContactMessagesJob] API returned messages', [
                'count' => count($apiMessages),
            ]);

            // 2. Build an O(1) lookup set of existing message UUIDs in the DB
            //    for this specific conversation (channel + remote pair).
            $existingUuids = Message::query()
                ->where('channel_phone_number', $this->channelPhoneNumber)
                ->where('remote_phone_number', $this->remotePhoneNumber)
                ->pluck('message_uuid')
                ->flip() // Convert to [uuid => index] for O(1) isset() checks
                ->all();

            // 3. Iterate API messages and insert only those missing locally.
            //    Track the most recent inbound/outbound message seen in the API
            //    response so we can update the contact's denormalized fields.
            $created = 0;
            $skipped = 0;

            // Each entry: ['msg' => array, 'ts' => Carbon|null]
            $latestInbound  = null;
            $latestOutbound = null;

            foreach ($apiMessages as $apiMsg) {
                $uuid = $apiMsg['uuid'] ?? null;

                if ($uuid === null) {
                    $skipped++;
                    continue;
                }

                // Track latest inbound / outbound across ALL API messages
                // (not just newly inserted ones) so the contact always reflects
                // the true latest, even when messages already existed locally.
                $sentBy    = $apiMsg['sent_by'] ?? null;
                $direction = $sentBy === 'user' ? 'inbound' : 'outbound';
                $ts        = isset($apiMsg['created_at'])
                    ? Carbon::parse($apiMsg['created_at'], 'UTC')
                    : null;

                if ($direction === 'inbound') {
                    if ($ts && ($latestInbound === null || $ts->gt($latestInbound['ts']))) {
                        $latestInbound = ['msg' => $apiMsg, 'ts' => $ts];
                    }
                } else {
                    if ($ts && ($latestOutbound === null || $ts->gt($latestOutbound['ts']))) {
                        $latestOutbound = ['msg' => $apiMsg, 'ts' => $ts];
                    }
                }

                // O(1) check — no additional DB query per message
                if (isset($existingUuids[$uuid])) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->insertMessage($apiMsg);
                    $created++;
                } catch (\Throwable $e) {
                    Log::warning('[SyncContactMessagesJob] Failed to insert message', [
                        'uuid'  => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }

            Log::info('[SyncContactMessagesJob] Sync complete', [
                'channel_phone_number' => $this->channelPhoneNumber,
                'remote_phone_number'  => $this->remotePhoneNumber,
                'created'              => $created,
                'skipped'              => $skipped,
                'total'                => count($apiMessages),
            ]);

            // 4. Update the contact's denormalized last_message / last_messages_events
            $this->updateContactLastMessageFields($latestInbound, $latestOutbound);

        } catch (\Throwable $e) {
            Log::error('[SyncContactMessagesJob] Sync failed', [
                'channel_phone_number' => $this->channelPhoneNumber,
                'remote_phone_number'  => $this->remotePhoneNumber,
                'error'                => $e->getMessage(),
                'trace'                => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Always update the timestamp, even if the sync partially failed,
            // to avoid flooding the API with retries on transient errors.
            $this->updateContactSyncTimestamp();
        }
    }

    /**
     * Update the contact's last_message and last_messages_events fields based
     * on the most recent inbound and outbound messages found in the API response.
     *
     * We compare timestamps so we never overwrite a more-recent value that was
     * already stored (e.g. from a webhook that arrived just before this sync).
     *
     * @param array|null $latestInbound  ['msg' => array, 'ts' => Carbon] or null
     * @param array|null $latestOutbound ['msg' => array, 'ts' => Carbon] or null
     */
    private function updateContactLastMessageFields(?array $latestInbound, ?array $latestOutbound): void
    {
        if ($latestInbound === null && $latestOutbound === null) {
            return;
        }

        try {
            // ── last_messages_events ──────────────────────────────────────────
            $existing = $this->contact->getLastMessagesEvents();

            // Keep the better (more recent) timestamp for each direction.
            $newInboundAt  = $latestInbound['ts'] ?? null;
            $newOutboundAt = $latestOutbound['ts'] ?? null;

            $prevInboundAt  = $existing?->getLastInboundAt();
            $prevOutboundAt = $existing?->getLastOutboundAt();
            $prevCheckAt    = $existing?->getLastCheckNumberAt();

            $resolvedInboundAt  = $this->mostRecent($prevInboundAt, $newInboundAt);
            $resolvedOutboundAt = $this->mostRecent($prevOutboundAt, $newOutboundAt);

            $this->contact->setLastMessagesEvents(new LastMessageEvent(
                lastInboundAt:      $resolvedInboundAt,
                lastOutboundAt:     $resolvedOutboundAt,
                lastCheckNumberAt:  $prevCheckAt,
            ));

            // ── last_message ──────────────────────────────────────────────────
            // Pick whichever direction yielded the most recent single message.
            $latestOverall = $this->mostRecentEntry($latestInbound, $latestOutbound);

            if ($latestOverall !== null) {
                $apiMsg        = $latestOverall['msg'];
                $sentBy        = $apiMsg['sent_by'] ?? null;
                $messageContent = $apiMsg['message'] ?? [];
                $type          = is_array($messageContent) ? array_key_last($messageContent) : null;
                $text          = is_array($messageContent) ? ($messageContent['text'] ?? null) : null;

                $this->contact->setLastMessage(new \App\ValueObjects\LastMessage(
                    direction: $sentBy === 'user' ? 'inbound' : 'outbound',
                    type:      $type,
                    text:      $text,
                    status:    'delivered',
                ));
            }

            Log::debug('[SyncContactMessagesJob] Updated contact last_message / last_messages_events', [
                'contact_id'      => $this->contact->getId(),
                'latest_inbound'  => $latestInbound['ts']?->toIso8601String(),
                'latest_outbound' => $latestOutbound['ts']?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SyncContactMessagesJob] Failed to update last_message fields', [
                'contact_id' => $this->contact->getId(),
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map an API message payload to a Message model and persist it.
     *
     * @param array $apiMsg Raw message object from 2Chat API
     */
    private function insertMessage(array $apiMsg): void
    {
        $sentBy    = $apiMsg['sent_by'] ?? null;
        $timestamp = $apiMsg['created_at'] ?? null;
        $parsedAt  = $timestamp ? Carbon::parse($timestamp, 'UTC') : null;

        // Determine message type: the API nests the content under the type key
        $messageContent = $apiMsg['message'] ?? [];
        $type           = is_array($messageContent) ? array_key_last($messageContent) : null;
        $text           = is_array($messageContent) ? ($messageContent['text'] ?? null) : null;

        $model = new Message();
        $model->setMessageUuid($apiMsg['uuid']);
        $model->setSessionKey($apiMsg['session_key'] ?? null);
        $model->setChannelPhoneNumber($this->channelPhoneNumber);
        $model->setRemotePhoneNumber($this->remotePhoneNumber);
        $model->setDirection($sentBy === 'user' ? 'inbound' : 'outbound');
        $model->setSentBy($sentBy);
        $model->setSource('sync'); // Marks messages inserted via sync, not webhook
        $model->setSentUserBy(null);
        $model->setType($type);
        $model->setText($text);
        $model->setStatus('delivered');
        $model->setDelivery(new Delivery(
            sentAt:      $parsedAt,
            deliveredAt: $parsedAt,
            readAt:      null,
        ));
        $model->setInternalRead(false);
        $model->setInternalReadAt(null);
        $model->setRawPayload($apiMsg);

        // Assign debtor_id from the contact if already resolved
        $model->setDebtorId($this->contact->getDebtorId());

        $model->saveQuietly();
    }

    /**
     * Update the contact's last_synced_at timestamp.
     * Any last_message / last_messages_events changes set previously on the
     * model instance will also be persisted in this single save.
     */
    private function updateContactSyncTimestamp(): void
    {
        try {
            $this->contact->setLastSyncedAt(now());
            $this->contact->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('[SyncContactMessagesJob] Failed to update contact', [
                'contact_id' => $this->contact->getId(),
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Return the more recent of two Carbon timestamps (either may be null).
     */
    private function mostRecent(?Carbon $a, ?Carbon $b): ?Carbon
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return $a->gte($b) ? $a : $b;
    }

    /**
     * Return whichever of the two API-message entries has the later timestamp.
     *
     * @param array|null $a ['msg' => array, 'ts' => Carbon]
     * @param array|null $b ['msg' => array, 'ts' => Carbon]
     */
    private function mostRecentEntry(?array $a, ?array $b): ?array
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return $a['ts']->gte($b['ts']) ? $a : $b;
    }
}
