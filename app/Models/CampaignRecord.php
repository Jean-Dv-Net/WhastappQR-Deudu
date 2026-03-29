<?php

namespace App\Models;

use App\Casts\AsObjectId;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class CampaignRecord extends Model
{
    /**
     * @var string $collection The MongoDB collection associated with the model.
     */
    protected string $collection = 'campaign_records';

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
        'phone_number',
        'identification',
        'debtor_id',
        'message',
        'attachment_url'
    ];

    /**
     * @var string[] $casts The attributes that should be cast to native types.
     */
    protected $casts = [
        'campaign_id' => AsObjectId::class,
        'phone_number' => 'string',
        'identification' => 'string',
        'debtor_id' => 'integer'
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
     * Get the campaign associated with the campaign record.
     *
     * @return BelongsTo
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }
}
