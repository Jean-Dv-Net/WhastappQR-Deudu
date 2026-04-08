<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Broadcast channel for contacts.
 * Any client can listen to contact events.
 */
Broadcast::channel('contacts.{userId}', function ($userId) {
    return true;
});

/**
 * Broadcast channel for contacts everywhere.
 * Any client can listen to contact events.
 */
Broadcast::channel('contacts', function () {
    return true;
});

/**
 * Broadcast channel for messages.
 * Any client can listen to message events.
 */
Broadcast::channel('messages.by_debtor.{debtorId}', function ($debtorId) {
    return true;
});

/**
 * Broadcast channel for messages by remote phone number.
 * Any client can listen to message events.
 */
Broadcast::channel('messages.by_remote_phone_number.{remotePhoneNumber}', function ($remotePhoneNumber) {
    return true;
});

/**
 * Broadcast channel for campaign done event to coordinator.
 */
Broadcast::channel('campaigns.finished.{coordinatorId}', function ($coordinatorId) {
    return true;
});

