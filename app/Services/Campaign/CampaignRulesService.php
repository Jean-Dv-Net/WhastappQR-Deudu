<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SystemConfig;

class CampaignRulesService
{
    /**
     * Returns the effective rules by merging global
     */
    public function getEffectiveRules(): array
    {
        return SystemConfig::getByKey('campaign-messaging-rules');
    }

    /**
     * Checks if the error percentage exceeds the threshold → pauses the campaign
     */
    public function checkErrorThreshold(Campaign $campaign): void
    {
        $rules = $this->getEffectiveRules();

        if (! $rules['auto_pause_on_error']) return;

        $total  = $campaign->messages_sent;
        $failed = $campaign->messages_failed;

        if ($total === 0) return;

        $errorRate = ($failed / $total) * 100;

        if ($errorRate >= $rules['error_threshold_percentage']) {
            $campaign->update(['status' => 'PAUSED']);
            // Disparar notificación si está habilitada
            if ($rules['notifications']['on_critical_error']) {
                // NotifyCriticalError::dispatch($campaign);
            }
        }
    }

    /**
     * Verifica si la hora actual está dentro de la ventana permitida
     */
    public function isWithinTimeWindow(Campaign $campaign): bool
    {
        $rules    = $this->getEffectiveRules();
        $timezone = $rules['time_window']['timezone'];
        $now      = now()->setTimezone($timezone);

        $start = $now->copy()->setTimeFromTimeString($rules['time_window']['start']);
        $end   = $now->copy()->setTimeFromTimeString($rules['time_window']['end']);

        return $now->between($start, $end);
    }
}