<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;

/**
 * System Configuration Model.
 * 
 * This model represents the system configuration settings.
 */
class SystemConfig extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var string $collection The MongoDB collection associated with the model.
     */
    protected string $collection = 'system_configs';

    /**
     * @var string $connection The database connection name.
     */
    protected $connection = 'mongodb';

    /**
     * @var string[] $fillable The attributes that are mass assignable.
     */
    protected $fillable = ['key', 'settings', 'updated_by'];

    public static function getByKey(string $key): ?array
    {
        return static::where('key', $key)->first()?->settings ?? self::getDefaultSettings($key);
    }

    private static function getDefaultSettings(string $key): ?array
    {
        return match ($key) {
            'campaign-messaging-rules' => self::getDefaultCampaignMessageSettings(),
            default => null,
        };
    }

    private static function getDefaultCampaignMessageSettings(): array
    {
        return [
            'max_messages_per_campaign' => 10000,
            'send_cadence' => [
                'messages_each_second' => 10,
            ],
            'time_window' => [
                'start' => '08:00',
                'end' => '20:00',
                'timezone' => 'America/Bogota'
            ],
            'auto_pause_on_error' => true,
            'error_threshold_percentage' => 15,
            'notifications' => [
                'enabled' => true
            ]
        ];
    }
}