<?php

namespace App\Observers;

use App\Events\MessageCreated as MessageCreatedEvent;
use App\Models\Contact;
use App\Models\Message;
use App\ValueObjects\LastMessage;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Message model to handle actions after a message is created.
 * Specifically, it increments the unread message count for the associated contact
 * when an inbound message is received and broadcasts the event via WebSocket.
 *
 * @package App\Observers
 */
class MessageObserver
{
    public function created(Message $message): void
    {
        Log::debug('[MessageObserver] Processing new message', [
            'message_id' => $message->getId(),
            'direction' => $message->getDirection(),
            'debtor_id' => $message->getDebtorId(),
            'remote_phone_number' => $message->getRemotePhoneNumber(),
        ]);

        // Broadcast the MessageCreated event via WebSocket
        $debtorId = $message->getDebtorId();
        $remotePhoneNumber = $message->getRemotePhoneNumber();

        broadcast(new MessageCreatedEvent($message, $debtorId, $remotePhoneNumber));

        // If the message is outbound, put the last message and return
        if ($message->getDirection() !== "inbound") {
            $contact = $this->getContactByDebtorIdOrPhoneNumber($message);

            if (!$contact)
                return;

            $contact->setLastMessage(new LastMessage(
                direction: $message->getDirection(),
                type: $message->getType(),
                text: $message->getText(),
                status: $message->getStatus()
            ));
            $contact->save();

            Log::debug('[MessageCreatedObserver] Updated last message for outbound message', [
                'contact_id' => $contact->getId()
            ]);
            return;
        }

        // Only increment for inbound messages
        $contact = $this->getContactByDebtorIdOrPhoneNumber($message);

        // If contact is found, increment the inbound message count
        if ($contact) {
            $previousUnreadCount = $contact->getUnreadMessages();

            Log::debug('[MessageCreatedObserver] Updating contact unread messages', [
                'contact_id' => $contact->getId(),
                'previous_unread_count' => $previousUnreadCount,
                'new_unread_count' => $previousUnreadCount + 1
            ]);

            $contact->setUnreadMessages($contact->getUnreadMessages() + 1);
            $contact->setLastMessage(new LastMessage(
                direction: $message->getDirection(),
                type: $message->getType(),
                text: $message->getText(),
                status: $message->getStatus()
            ));

            $contact->save();

            Log::debug('[MessageCreatedObserver] Contact updated successfully', [
                'contact_id' => $contact->getId(),
                'unread_messages' => $contact->getUnreadMessages()
            ]);
        } else {
            Log::debug('[MessageCreatedObserver] No contact found for message', [
                'message_id' => $message->getId(),
                'debtor_id' => $message->getDebtorId(),
                'remote_phone_number' => $message->getRemotePhoneNumber()
            ]);
        }
    }

    private function getContactByDebtorIdOrPhoneNumber(Message $message): ?Contact
    {
        // Search the contact, first by debtor_id if not found
        // then by remote_phone_number
        $contact = null;

        if ($message->getDebtorId()) {
            Log::debug('[MessageCreatedObserver] Searching contact by debtor_id', [
                'debtor_id' => $message->getDebtorId()
            ]);

            // Search the contact by debtor_id
            $contact = (new \App\Models\Contact())
                ->where('debtor_id', $message->getDebtorId())
                ->first();

            if ($contact) {
                Log::debug('[MessageCreatedObserver] Contact found by debtor_id', [
                    'contact_id' => $contact->getId(),
                    'debtor_id' => $contact->getDebtorId()
                ]);
            } else {
                Log::debug('[MessageCreatedObserver] Contact not found by debtor_id', [
                    'debtor_id' => $message->getDebtorId()
                ]);
            }
        }

        if (!$contact && $message->getRemotePhoneNumber()) {
            Log::debug('[MessageCreatedObserver] Searching contact by remote_phone_number', [
                'remote_phone_number' => $message->getRemotePhoneNumber()
            ]);

            // Search the contact by remote_phone_number
            $contact = (new \App\Models\Contact())
                ->where('remote_phone_number', $message->getRemotePhoneNumber())
                ->first();

            if ($contact) {
                Log::debug('[MessageCreatedObserver] Contact found by remote_phone_number', [
                    'contact_id' => $contact->getId(),
                    'remote_phone_number' => $contact->getRemotePhoneNumber()
                ]);
            } else {
                Log::debug('[MessageCreatedObserver] Contact not found by remote_phone_number', [
                    'remote_phone_number' => $message->getRemotePhoneNumber()
                ]);
            }
        }
        return $contact;
    }

    /**
     * Handle the Message "updated" event.
     */
    public function updated(Message $message): void
    {
        Log::debug('[MessageCreatedObserver] Message updated', [
            'message_id' => $message->getId(),
            'direction' => $message->getDirection(),
        ]);

        // Broadcast the MessageUpdated event via WebSocket
        $debtorId = $message->getDebtorId();
        $remotePhoneNumber = $message->getRemotePhoneNumber();
        broadcast(new \App\Events\MessageUpdated($message, $debtorId, $remotePhoneNumber));
    }
}
