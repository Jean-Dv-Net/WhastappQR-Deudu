<?php

namespace App\Libraries\Whatsapp;

use App\Exceptions\BadRequestException;
use App\Libraries\Whatsapp\Messages\WhatsappMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * This class provides methods to interact with 2Chat API.
 * It allows sending messages and downloading media.
 */
class Client
{
    protected string $apiUrl;
    protected string $apiKey;
    protected ?string $phoneNumber;
    protected int $timeout;

    public function __construct(
        ?string $apiUrl = null,
        ?string $apiKey = null,
        ?string $phoneNumber = null,
        int $timeout = 30
    ) {
        $this->apiUrl = $apiUrl ?? config('services.whatsapp.api_url');
        $this->apiKey = $apiKey ?? config('services.whatsapp.api_key');
        $this->phoneNumber = $phoneNumber ?? config('services.whatsapp.phone_number');
        $this->timeout = $timeout;
    }

    /**
     * Create a Client instance by coordination ID.
     *
     * @param int $coordinationId The coordination ID of the channel.
     * @return Client The Client instance.
     * @throws BadRequestException
     */
    public static function makeByCoordinationId(int $coordinationId): Client
    {
        $apiUrl = config('services.whatsapp.api_url');
        $apiKey = config('services.whatsapp.api_key');

        $channel = (new \App\Models\Channel)
            ->where('coordination_id', $coordinationId)
            ->where('connection_status', 'C')
            ->orderBy('priority', 'asc')
            ->first();

        if (!$channel) {
            throw new BadRequestException(
                "No existe un canal de comunicación asignado para la coordinación de este deudor. Por favor, configure un canal válido para continuar con el proceso."
            );
        }

        return new self(
            apiUrl: $apiUrl,
            apiKey: $apiKey,
            phoneNumber: $channel->getPhoneNumber()
        );
    }

    /**
     * Get the API usage of the client.
     *
     * @return array The response from the 2Chat API.
     * @throws ConnectionException
     */
    public static function getAPIUsage(): array
    {
        $apiKey = config('services.whatsapp.api_key');

        $url = "https://api.p.2chat.io/open/info";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-User-API-Key' => $apiKey,
        ])->get($url);

        return $response->json()['usage'];
    }

    /**
     * Send a Whatsapp message.
     *
     * @param WhatsappMessage $message The message to send.
     * @return array The response from the 2Chat API.
     * @throws ConnectionException If there is a connection error.
     */
    public function sendMessage(WhatsappMessage $message): array
    {
        $url = "{$this->apiUrl}/send-message";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-User-API-Key' => $this->apiKey,
        ])->post($url, $message->toPayload($this->phoneNumber));

        return $response->json();
    }

    /**
     * Get the list of phone numbers associated with the client.
     *
     * @param int $page The page number for pagination.
     * @return array The response from the 2Chat API.
     * @throws ConnectionException
     */
    public function getNumbersWithPagination(int $page): array
    {
        $url = "{$this->apiUrl}/get-numbers?page_number={$page}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-User-API-Key' => $this->apiKey,
        ])->get($url);

        if ($response->failed()) {
            return [
                'success' => false,
                'numbers' => [],
            ];
        }

        return $response->json();
    }

    /**
     * Check if a phone number is associated with the client's account.
     *
     * @param string $phoneNumber The phone number to check.
     * @return array The response from the 2Chat API.
     * @throws ConnectionException
     */
    public function checkNumber(string $phoneNumber): array
    {
        if (str_starts_with($phoneNumber, '+')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (str_starts_with($this->phoneNumber, '+')) {
            $this->phoneNumber = substr($this->phoneNumber, 1);
        }

        $url = "{$this->apiUrl}/check-number/+{$this->phoneNumber}/+{$phoneNumber}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-User-API-Key' => $this->apiKey,
        ])->get($url);

        return $response->json();
    }

    /**
     * Get the phone number associated with the client.
     *
     * @return string|null
     */
    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * Set the phone number associated with the client.
     *
     * @param string|null $phoneNumber
     */
    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * Get the messages of a WhatsApp conversation from the 2Chat API.
     *
     * @param string $channelPhoneNumber The channel phone number (e.g. +573224816062)
     * @param string $remotePhoneNumber  The contact phone number (e.g. +573224220769)
     * @return array The response from the 2Chat API (keys: success, messages)
     * @throws ConnectionException
     */
    public function getMessages(string $channelPhoneNumber, string $remotePhoneNumber): array
    {
        // Normalize: ensure both numbers have the '+' prefix
        if (!str_starts_with($channelPhoneNumber, '+')) {
            $channelPhoneNumber = '+' . $channelPhoneNumber;
        }

        if (!str_starts_with($remotePhoneNumber, '+')) {
            $remotePhoneNumber = '+' . $remotePhoneNumber;
        }

        $url = "{$this->apiUrl}/messages/{$channelPhoneNumber}/{$remotePhoneNumber}";

        $response = Http::withHeaders([
            'Content-Type'   => 'application/json',
            'X-User-API-Key' => $this->apiKey,
        ])->timeout($this->timeout)->get($url);

        if ($response->failed()) {
            return [
                'success'  => false,
                'messages' => [],
            ];
        }

        return $response->json();
    }
}
