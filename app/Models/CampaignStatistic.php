<?php

namespace App\Models;

use App\Casts\AsObjectId;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * CampaignStatistic model.
 *
 * Flat statistics for a campaign, stored in its own collection.
 */
class CampaignStatistic extends Model
{
    /**
     * @var string $collection The MongoDB collection associated with the model.
     */
    protected string $collection = 'campaign_statistics';

    /**
     * @var string $connection The database connection name.
     */
    protected $connection = 'mongodb';

    /**
     * @var string The primary key of the model.
     */
    protected $primaryKey = 'id';

    /**
     * @var string[] $fillable The attributes that are mass assignable.
     */
    protected $fillable = [
        'campaign_id',
        'pending',
        'sent',
        'delivered',
        'read',
        'failed',
    ];

    /**
     * @var string[] $casts The attributes that should be cast to native types.
     */
    protected $casts = [
        'campaign_id' => AsObjectId::class,
        'pending'     => 'integer',
        'sent'        => 'integer',
        'delivered'   => 'integer',
        'read'        => 'integer',
        'failed'      => 'integer',
    ];

    /**
     * @var string[] $hidden The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var string[] $appends Accessors to append to the model's array/JSON form.
     */
    protected $appends = [
        'delivery_rate',
        'read_rate',
    ];

    /**
     * Override the getAttribute method to handle the _id attribute.
     */
    public function getAttribute($key)
    {
        if ($key === 'id' && isset($this->attributes['id'])) {
            return new ObjectId($this->attributes['id']);
        }

        return parent::getAttribute($key);
    }

    /**
     * Delivery rate (%): (Delivered / Sent) * 100
     */
    public function getDeliveryRateAttribute(): float
    {
        $sent = (int) ($this->attributes['sent'] ?? 0);

        if ($sent === 0) {
            return 0.0;
        }

        return round(($this->attributes['delivered'] ?? 0) / $sent * 100, 2);
    }

    /**
     * Read rate (%): (Read / Delivered) * 100
     */
    public function getReadRateAttribute(): float
    {
        $sent = (int) ($this->attributes['sent'] ?? 0);

        if ($sent === 0) {
            return 0.0;
        }

        return round(($this->attributes['read'] ?? 0) / $sent * 100, 2);
    }

    /**
     * Get the campaign associated with this statistic.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }
}
