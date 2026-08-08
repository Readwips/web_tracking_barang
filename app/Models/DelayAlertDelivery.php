<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DelayAlertDelivery extends Model
{
    public const EVENT = 'shipment.delayed';

    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_WEBHOOK = 'webhook';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shipment_id',
        'expected_arrival',
        'event',
        'channel',
        'audience',
        'destination',
        'destination_hash',
        'message',
        'status',
        'processing_token',
        'attempts',
        'last_attempt_at',
        'sent_at',
        'cancelled_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'expected_arrival' => 'date',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function destinationHash(string $channel, string $destination): string
    {
        $normalized = $channel === self::CHANNEL_MAIL
            ? Str::lower(trim($destination))
            : trim($destination);

        return hash('sha256', $channel.'|'.$normalized);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
