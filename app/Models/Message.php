<?php

namespace App\Models;

use App\Observers\MessageDeliveryObserver;
use App\Observers\MessageObserver;
use App\ValueObjects\Delivery;
use App\ValueObjects\Edited;
use App\ValueObjects\Media;
use App\ValueObjects\Quoted;
use App\ValueObjects\Reaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Message model
 *
 * This model represents a message reference in the system
 * with whatsapp integration.
 */
#[ObservedBy([MessageObserver::class, MessageDeliveryObserver::class])]
class Message extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var string $collection The MongoDB collection name
     */
    protected string $collection = 'messages';

    /**
     * @var string $connection The database connection name
     */
    protected $connection = 'mongodb';

    /**
     * @var string[] $fillable The attributes that are mass assignable
     */
    protected $fillable = [
        'message_uuid',
        'session_key',
        'channel_uuid',
        'channel_phone_number',
        'remote_phone_number',
        'contact_id',
        'debtor_id',
        'direction',
        'sent_by',
        'quoted',
        'source',
        'sent_user_by',
        'type',
        'text',
        'media',
        'status',
        'delivery',
        'internal_read',
        'internal_read_at',
        'reactions',
        'edited',
        'raw_payload',
        'metadata'
    ];

    /**
     * @var string[] $casts The attributes that should be cast to native types
     */
    protected $casts = [
        // Simple fields
        'message_uuid' => 'string',
        'session_key' => 'string',
        'channel_uuid' => 'string',
        'channel_phone_number' => 'string',
        'remote_phone_number' => 'string',
        'debtor_id' => 'integer',
        'direction' => 'string',
        'sent_by' => 'string',
        'source' => 'string',
        'sent_user_by' => 'integer',
        'type' => 'string',
        'text' => 'string',
        'status' => 'string',
        'internal_read' => 'boolean',
        'raw_payload' => 'array',
        'internal_read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

        // Complex fields
        'media' => \App\Casts\MediaCast::class,
        'delivery' => \App\Casts\DeliveryCast::class,
        'reactions' => \App\Casts\ReactionsCast::class,
        'edited' => \App\Casts\EditedCast::class,
        'object_id' => \App\Casts\AsObjectId::class,
        'quoted' => \App\Casts\QuotedCast::class,
    ];

    /**
     * @var string[] $hidden The attributes that should be hidden for arrays
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the message ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return (string) $this->_id;
    }

    /**
     * Set the message ID.
     *
     * @param string $value
     * @return void
     */
    public function setId(string $value): void
    {
        $this->_id = new ObjectId($value);
    }

    /**
     * Get the message UUID.
     *
     * @return string|null Get the message UUID
     */
    public function getMessageUuid(): ?string
    {
        return $this->message_uuid;
    }

    /**
     * Set the message UUID.
     *
     * @param string|null $value
     * @return void
     */
    public function setMessageUuid(?string $value): void
    {
        $this->message_uuid = $value;
    }

    /**
     * Get the session key.
     *
     * @return string|null Get the session key
     */
    public function getSessionKey(): ?string
    {
        return $this->session_key;
    }

    /**
     * Set the session key.
     *
     * @param string|null $value
     * @return void
     */
    public function setSessionKey(?string $value): void
    {
        $this->session_key = $value;
    }

    /**
     * Get the channel UUID.
     *
     * @return string|null
     */
    public function getChannelUuid(): ?string
    {
        return $this->channel_uuid;
    }

    /**
     * Set the channel UUID.
     *
     * @param string|null $value
     * @return void
     */
    public function setChannelUuid(?string $value): void
    {
        $this->channel_uuid = $value;
    }

    /**
     * Get the channel phone number.
     *
     * @return string|null
     */
    public function getChannelPhoneNumber(): ?string
    {
        return $this->channel_phone_number;
    }

    /**
     * Set the channel phone number.
     *
     * @param string|null $value
     * @return void
     */
    public function setChannelPhoneNumber(?string $value): void
    {
        $this->channel_phone_number = $value;
    }

    /**
     * Get the remote phone number.
     *
     * @return string|null
     */
    public function getRemotePhoneNumber(): ?string
    {
        return $this->remote_phone_number;
    }

    /**
     * Set the remote phone number.
     *
     * @param string|null $value
     * @return void
     */
    public function setRemotePhoneNumber(?string $value): void
    {
        $this->remote_phone_number = $value;
    }

    /**
     * Get the debtor ID.
     *
     * @return int|null
     */
    public function getDebtorId(): ?int
    {
        return $this->debtor_id;
    }

    /**
     * Set the debtor ID.
     *
     * @param int|null $value
     * @return void
     */
    public function setDebtorId(?int $value): void
    {
        $this->debtor_id = $value;
    }

    /**
     * Get the direction.
     *
     * @return string|null
     */
    public function getDirection(): ?string
    {
        return $this->direction;
    }

    /**
     * Set the direction.
     *
     * @param string|null $value
     * @return void
     */
    public function setDirection(?string $value): void
    {
        $this->direction = $value;
    }

    /**
     * Get the sent by.
     *
     * @return string|null
     */
    public function getSentBy(): ?string
    {
        return $this->sent_by;
    }

    /**
     * Set the sent by.
     *
     * @param string|null $value
     * @return void
     */
    public function setSentBy(?string $value): void
    {
        $this->sent_by = $value;
    }

    /**
     * Get the source.
     *
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Set the source.
     *
     * @param string|null $value
     * @return void
     */
    public function setSource(?string $value): void
    {
        $this->source = $value;
    }

    /**
     * Get the sent user by.
     *
     * @return int|null
     */
    public function getSentUserBy(): ?int
    {
        return $this->sent_user_by;
    }

    /**
     * Set the sent user by.
     *
     * @param int|null $value
     * @return void
     */
    public function setSentUserBy(?int $value): void
    {
        $this->sent_user_by = $value;
    }

    /**
     * Get the type.
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the type.
     *
     * @param string|null $value
     * @return void
     */
    public function setType(?string $value): void
    {
        $this->type = $value;
    }

    /**
     * Get the text.
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * Set the text.
     *
     * @param string|null $value
     * @return void
     */
    public function setText(?string $value): void
    {
        $this->text = $value;
    }

    /**
     * Get the media (ValueObject).
     *
     * @return Media
     */
    public function getMedia(): Media
    {
        return $this->media;
    }

    /**
     * Set the media (ValueObject).
     *
     * @param mixed $value
     * @return void
     */
    public function setMedia(Media $value): void
    {
        $this->media = $value;
    }

    /**
     * Get the status.
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Set the status.
     *
     * @param string|null $value
     * @return void
     */
    public function setStatus(?string $value): void
    {
        $this->status = $value;
    }

    /**
     * Get the delivery (ValueObject).
     *
     * @return Delivery
     */
    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    /**
     * Set the delivery (ValueObject).
     *
     * @param Delivery $value
     * @return void
     */
    public function setDelivery(Delivery $value): void
    {
        $this->delivery = $value;
    }

    /**
     * Get the internal_read.
     *
     * @return bool
     */
    public function getInternalRead(): bool
    {
        return (bool) $this->internal_read;
    }

    /**
     * Set the internal_read.
     *
     * @param bool $value
     * @return void
     */
    public function setInternalRead(bool $value): void
    {
        $this->internal_read = $value;
    }

    /**
     * Get the internal_read_at.
     *
     * @return Carbon|null
     */
    public function getInternalReadAt(): ?Carbon
    {
        return $this->internal_read_at;
    }

    /**
     * Set the internal_read_at.
     *
     * @param Carbon|null $value
     * @return void
     */
    public function setInternalReadAt(?Carbon $value): void
    {
        $this->internal_read_at = $value;
    }

    /**
     * Get the reactions (ValueObject).
     *
     * @return Reaction[]
     */
    public function getReactions(): array
    {
        return $this->reactions;
    }

    /**
     * Set the reactions (ValueObject).
     *
     * @param mixed $value
     * @return void
     */
    public function setReactions(mixed $value): void
    {
        $this->reactions = $value;
    }

    /**
     * Get the edited (ValueObject).
     *
     * @return Edited|null
     */
    public function getEdited(): ?Edited
    {
        return $this->edited;
    }

    /**
     * Set the edited (ValueObject).
     *
     * @param mixed $value
     * @return void
     */
    public function setEdited(mixed $value): void
    {
        $this->edited = $value;
    }

    /**
     * Get the raw payload.
     *
     * @return mixed
     */
    public function getRawPayload(): mixed
    {
        return $this->raw_payload;
    }

    /**
     * Set the raw payload.
     *
     * @param mixed $value
     */
    public function setRawPayload(mixed $value): void
    {
        $this->raw_payload = $value;
    }

    public function getQuoted(): ?Quoted
    {
        return $this->quoted;
    }

    public function setQuoted(?Quoted $value): void
    {
        $this->quoted = $value;
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        $this->internal_read = true;
        $this->internal_read_at = now();
        $this->getDelivery()->setReadAt(now());
    }

    /**
     * Mark as unread
     */
    public function markAsUnread(): void
    {
        $this->internal_read = false;
        $this->internal_read_at = null;
        $this->getDelivery()->setReadAt(null);
    }
}
