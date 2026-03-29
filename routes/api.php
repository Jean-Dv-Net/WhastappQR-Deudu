<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('channels')->group(function () {
        /**
         * Get all channels
         */
        Route::get('/', [\App\Http\Controllers\Api\V1\Channel\GetChannelController::class, '__invoke']);

        /**
         * Get channel available for debtor
         */
        Route::get('/available/{coordinationId}', [\App\Http\Controllers\Api\V1\Channel\GetChannelAvailableForDebtorController::class, '__invoke']);

        /**
         * Get channels associated with coordination
         */
        Route::get('/associated/{coordinationId}', [\App\Http\Controllers\Api\V1\Channel\GetChannelsAssociatedWithCoordinationController::class, '__invoke']);

        /**
         * Get coordination by channel.
         */
        Route::get('/coordination/{channel}', [\App\Http\Controllers\Api\V1\Channel\GetCoordinationByChannelController::class, '__invoke']);

        /**
         * Put coordination id to channel
         */
        Route::put('/{channel}', [\App\Http\Controllers\Api\V1\Channel\PutChannelController::class, '__invoke']);
    });

    Route::prefix('messages')->group(function () {
        /**
         * Post a message
         */
        Route::post('/', [\App\Http\Controllers\Api\V1\Message\PostMessageController::class, '__invoke']);

        /**
         * Get messages by remote phone number
         */
        Route::get('/{remotePhoneNumber}', [\App\Http\Controllers\Api\V1\Message\GetMessagesByRemothePhoneNumberController::class, '__invoke']);
    });

    Route::prefix('webhook')->group(function () {
        /**
         * Webhook endpoint
         */
        Route::post('/', [\App\Http\Controllers\Api\V1\Webhook\PostWebhookController::class, '__invoke']);
    });

    Route::prefix('debtors')->group(function () {
        /**
         * Get messages by debtor ID
         */
        Route::get('/{debtorId}/messages', [\App\Http\Controllers\Api\V1\Message\GetMessagesByDebtorController::class, '__invoke']);

        /**
         * Mark as read
         */
        Route::post('/{debtorId}/mark-as-read', [\App\Http\Controllers\Api\V1\Message\PostMarkAsReadController::class, '__invoke']);

        /**
         * Mark as unread
         */
        Route::post('/{debtorId}/mark-as-unread', [\App\Http\Controllers\Api\V1\Message\PostMarkAsUnreadByDebtorController::class, '__invoke']);
    });

    Route::prefix('conversations')->group(function () {
        /**
         * Unified contact summary endpoint (replaces by-debtors and by-coordination)
         * Supports flexible filtering and optional grouping by debtor.
         * You can use this endpoint with method GET or POST.
         */
        Route::get('/summary', [\App\Http\Controllers\Api\V1\Contact\GetContactSummaryController::class, '__invoke']);
        Route::post('/summary', [\App\Http\Controllers\Api\V1\Contact\GetContactSummaryController::class, '__invoke']);

        /**
         * Mark contact as resolved
         */
        Route::post('/{contact}/mark-as-resolved', [\App\Http\Controllers\Api\V1\Contact\MarkAsResolvedController::class, '__invoke']);

        /**
         * Mark contact as unread
         */
        Route::post('/{remotePhoneNumber}/mark-as-unread', [\App\Http\Controllers\Api\V1\Message\PostMarkAsUnreadByRemotePhoneNumberController::class, '__invoke']);
    });


    Route::prefix('batches')->group(function () {
        Route::prefix('validator-numbers')->group(function () {
            Route::prefix('limits')->group(function () {
                /**
                 * Get limits validator batch
                 */
                Route::get('/', [\App\Http\Controllers\Api\V1\ValidatorBatch\Limits\GetLimitsController::class, '__invoke']);

                /**
                 * Get types limits validator batch
                 */
                Route::get('/types', [\App\Http\Controllers\Api\V1\ValidatorBatch\Limits\GetTypesLimitsController::class, '__invoke']);

                /**
                 * Get periods limits validator batch
                 */
                Route::get('/periods', [\App\Http\Controllers\Api\V1\ValidatorBatch\Limits\GetPeriodsLimitsController::class, '__invoke']);

                /**
                 * Update or Create limit validator batch
                 */
                Route::put('/{limit}', [\App\Http\Controllers\Api\V1\ValidatorBatch\Limits\PutLimitController::class, '__invoke']);
            });
            /**
             * Get all validator batches
             */
            Route::get('/', [\App\Http\Controllers\Api\V1\ValidatorBatch\GetValidatorBatchsController::class, '__invoke']);
            /**
             * Create a batch validator numbers
             */
            Route::post('/', [\App\Http\Controllers\Api\V1\ValidatorBatch\CreateValidatorBatchController::class, '__invoke']);

            /**
             * Approve a batch validator numbers
             */
            Route::put('/{batch}/approve', [\App\Http\Controllers\Api\V1\ValidatorBatch\ApproveValidatorBatchController::class, '__invoke']);

            /**
             * Reject a batch validator numbers
             */
            Route::put('/{batch}/reject', [\App\Http\Controllers\Api\V1\ValidatorBatch\RejectValidatorBatchController::class, '__invoke']);
        });
    });

    Route::prefix('settings')->group(function () {
        /**
         * Get settings by key
         */
        Route::get('/{key}', [\App\Http\Controllers\Api\V1\Settings\GetSettingsController::class, '__invoke']);

        /**
         * Update or Create setting
         */
        Route::put('/{key}', [\App\Http\Controllers\Api\V1\Settings\PutSettingController::class, '__invoke']);
    });

    Route::prefix('campaigns')->group(function () {
        /**
         * Create a campaign
         */
        Route::post('/', [\App\Http\Controllers\Api\V1\Campaign\PostCampaignController::class, '__invoke']);

        Route::prefix('records')->group(function () {
            /**
             * Update a campaign record
             */
            Route::put('/{record}', [\App\Http\Controllers\Api\V1\Campaign\Record\PutCampaignRecordController::class, '__invoke']);
        });

        /**
         * Get campaign statistics
         */
        Route::get('/{campaign_id}/statistics', [\App\Http\Controllers\Api\V1\Campaign\Statistics\GetCampaignStatisticsController::class, '__invoke']);
    });

    Route::prefix('uploads')->group(function () {
        /**
         * Upload a file
         */
        Route::post('/', [\App\Http\Controllers\Api\V1\PostFileController::class, '__invoke']);
    });
});

