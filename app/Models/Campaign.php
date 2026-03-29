<?php

namespace App\Models;

use App\Casts\AsObjectId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

/**
 * Campaign model.
 *
 * This model represents a campaign in the system.
 */
class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    public const string STATUS_BUILDING = 'building';
    public const string STATUS_SENDING = 'sending';
    public const string STATUS_DONE = 'done';
    public const string STATUS_FAILED = 'failed';

    /**
     * @var string $collection The MongoDB collection associated with the model.
     */
    protected string $collection = 'campaigns';

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
        'channel_id',
        'name',
        'has_attachment',
        'template_type',
        'template',
        'status'
    ];

    /**
     * @var string[] $casts The attributes that should be cast to native types.
     */
    protected $casts = [
        'channel_id' => AsObjectId::class,
        'name' => 'string',
        'has_attachment' => 'boolean',
        'template_type' => 'string',
        'template' => 'string',
        'status' => 'string',
    ];

    /**
     * @var string[] $hidden The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
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
     * Get the channel associated with the campaign.
     *
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the campaign records associated with the campaign.
     *
     * @return HasMany
     */
    public function records(): HasMany
    {
        return $this->hasMany(CampaignRecord::class, 'campaign_id', 'id');
    }

    /**
     * Get the statistics associated with the campaign.
     *
     * @return \MongoDB\Laravel\Relations\HasOne
     */
    public function statistics(): \MongoDB\Laravel\Relations\HasOne
    {
        return $this->hasOne(CampaignStatistic::class, 'campaign_id', 'id');
    }
}
