<?php

namespace App\Jobs;

use App\Libraries\Whatsapp\Client;
use App\Libraries\Whatsapp\Messages\TextMessage;
use App\Models\Campaign;
use App\Models\CampaignRecord;
use App\Models\CampaignStatistic;
use App\Models\Message;
use App\Models\SystemConfig;
use App\ValueObjects\Delivery;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 0;

    /**
     * The campaign to send messages for.
     */
    protected string $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct(Campaign $campaign)
    {
        $this->campaignId = (string) $campaign->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign) {
            Log::error('SendCampaignMessagesJob: Campaña no encontrada.', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        $rules = SystemConfig::getByKey('campaign-messaging-rules');

        $channel = $campaign->channel;

        if (!$channel) {
            Log::error('SendCampaignMessagesJob: Canal no encontrado para la campaña.', [
                'campaign_id' => $this->campaignId,
            ]);
            $campaign->update(['status' => Campaign::STATUS_FAILED]);
            return;
        }

        $client = new Client(
            phoneNumber: $channel->getPhoneNumber()
        );

        $records = $campaign->records()
            ->where('status', CampaignRecord::STATUS_READY)
            ->get();

        if ($records->isEmpty()) {
            Log::info('SendCampaignMessagesJob: No hay registros listos para enviar.', [
                'campaign_id' => $this->campaignId,
            ]);
            $campaign->update(['status' => Campaign::STATUS_DONE]);
            return;
        }

        // Enforce max messages per campaign limit
        $maxMessages = $rules['max_messages_per_campaign'] ?? 10000;
        $records = $records->take($maxMessages);

        // Cadence: messages per second
        $messagesPerSecond = $rules['send_cadence']['messages_each_second'] ?? 10;
        $delayMicroseconds = $messagesPerSecond * 1000000;

        $statistic = CampaignStatistic::firstOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'pending'   => $records->count(),
                'sent'      => 0,
                'delivered'  => 0,
                'read'      => 0,
                'failed'    => 0,
            ]
        );

        $sentCount = 0;
        $failedCount = 0;

        foreach ($records as $record) {
            // Check time window before each message — if outside, re-dispatch for next day
            if (!$this->isWithinTimeWindow($rules)) {
                $delaySeconds = $this->secondsUntilNextWindowStart($rules);

                Log::info('SendCampaignMessagesJob: Fuera de la ventana horaria. Re-programando para el siguiente inicio de ventana.', [
                    'campaign_id'    => $this->campaignId,
                    'delay_seconds'  => $delaySeconds,
                ]);

                self::dispatch($campaign)->delay(now()->addSeconds($delaySeconds));
                return;
            }

            // Check error threshold
            $totalProcessed = $sentCount + $failedCount;
            if ($totalProcessed > 0 && ($rules['auto_pause_on_error'] ?? true)) {
                $errorRate = ($failedCount / $totalProcessed) * 100;
                if ($errorRate >= ($rules['error_threshold_percentage'] ?? 15)) {
                    Log::warning('SendCampaignMessagesJob: Umbral de error alcanzado. Pausando campaña.', [
                        'campaign_id' => $this->campaignId,
                        'error_rate'  => $errorRate,
                    ]);
                    $campaign->update(['status' => Campaign::STATUS_FAILED]);
                    return;
                }
            }

            try {
                // Send the text message
                $textMessage = new TextMessage(
                    to: $record->phone_number,
                    text: $campaign->template_type === 'text' ? $record->message : null,
                    url: $campaign->template_type !== 'text' ? $campaign->template : null,
                );

                Log::info('SendCampaignMessagesJob: Enviando mensaje.', [
                    'campaign_id' => $this->campaignId,
                    'message' => $textMessage->toPayload($channel->getPhoneNumber()),
                ]);

                $response = $client->sendMessage($textMessage);

                if (isset($response['error']) && $response['error'] === true) {
                    throw new \Exception($response['error_message'] ?? 'Error desconocido al enviar mensaje.');
                }

                // Store the message with campaign_record metadata
                $message = new Message();
                $message->setMessageUuid($response['message_uuid'] ?? null);
                $message->setChannelPhoneNumber($client->phoneNumber());
                $message->setRemotePhoneNumber($record->phone_number);
                $message->setDirection('outbound');
                $message->setType('text');
                $message->setText($record->message);
                $message->setStatus('sent');
                $message->setSource('campaign');
                $message->setDelivery(new Delivery(
                    sentAt: Carbon::now(),
                    deliveredAt: null,
                    readAt: null,
                ));
                $message->metadata = [
                    'campaign_record' => (string) $record->id,
                ];
                $message->save();

                // If campaign has attachment, send a second message with the URL
                if ($campaign->has_attachment && !empty($record->attachment_url)) {
                    usleep($delayMicroseconds);

                    $attachmentMessage = new TextMessage(
                        to: $record->phone_number,
                        text: null,
                        url: $record->attachment_url,
                    );

                    $attachmentResponse = $client->sendMessage($attachmentMessage);

                    if (isset($attachmentResponse['error']) && $attachmentResponse['error'] === true) {
                        Log::warning('SendCampaignMessagesJob: Error al enviar adjunto.', [
                            'campaign_id'      => $this->campaignId,
                            'campaign_record'  => (string) $record->id,
                            'error'            => $attachmentResponse['error_message'] ?? 'Desconocido',
                        ]);
                    } else {
                        // Store attachment message with same campaign_record metadata
                        $attachmentMsg = new Message();
                        $attachmentMsg->setMessageUuid($attachmentResponse['message_uuid'] ?? null);
                        $attachmentMsg->setChannelPhoneNumber($client->phoneNumber());
                        $attachmentMsg->setRemotePhoneNumber($record->phone_number);
                        $attachmentMsg->setDirection('outbound');
                        $attachmentMsg->setType('text');
                        $attachmentMsg->setText($record->attachment_url);
                        $attachmentMsg->setStatus('sent');
                        $attachmentMsg->setSource('campaign');
                        $attachmentMsg->setDelivery(new Delivery(
                            sentAt: Carbon::now(),
                            deliveredAt: null,
                            readAt: null,
                        ));
                        $attachmentMsg->metadata = [
                            'campaign_record' => (string) $record->id,
                        ];
                        $attachmentMsg->save();
                    }
                }

                // Update record status
                $record->update(['status' => CampaignRecord::STATUS_SENT]);

                $sentCount++;
                $statistic->increment('sent');
                $statistic->decrement('pending');

            } catch (\Exception $e) {
                Log::error('SendCampaignMessagesJob: Error al enviar mensaje.', [
                    'campaign_id'      => $this->campaignId,
                    'campaign_record'  => (string) $record->id,
                    'error'            => $e->getMessage(),
                ]);

                $record->update(['status' => CampaignRecord::STATUS_FAILED]);

                $failedCount++;
                $statistic->increment('failed');
                $statistic->decrement('pending');
            }

            // Respect send cadence
            usleep($delayMicroseconds);
        }

        // Determine final campaign status
        if ($failedCount === $records->count()) {
            $campaign->update(['status' => Campaign::STATUS_FAILED]);
        } else {
            $campaign->update(['status' => Campaign::STATUS_DONE]);
        }

        Log::info('SendCampaignMessagesJob: Envío de campaña finalizado.', [
            'campaign_id' => $this->campaignId,
            'sent'        => $sentCount,
            'failed'      => $failedCount,
        ]);
    }

    /**
     * Check if the current time is within the allowed time window.
     */
    private function isWithinTimeWindow(array $rules): bool
    {
        $timeWindow = $rules['time_window'] ?? null;

        if (!$timeWindow) {
            return true;
        }

        $timezone = $timeWindow['timezone'] ?? 'America/Bogota';
        $now = now()->setTimezone($timezone);

        $start = $now->copy()->setTimeFromTimeString($timeWindow['start'] ?? '08:00');
        $end = $now->copy()->setTimeFromTimeString($timeWindow['end'] ?? '20:00');

        return $now->between($start, $end);
    }

    /**
     * Calculate seconds until the next day's time window start.
     */
    private function secondsUntilNextWindowStart(array $rules): int
    {
        $timeWindow = $rules['time_window'] ?? [];
        $timezone = $timeWindow['timezone'] ?? 'America/Bogota';
        $startTime = $timeWindow['start'] ?? '08:00';

        $now = now()->setTimezone($timezone);
        $nextStart = $now->copy()->addDay()->setTimeFromTimeString($startTime);

        return (int) $now->diffInSeconds($nextStart);
    }
}
