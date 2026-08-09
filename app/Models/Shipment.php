<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Booking dibuat',
        'Kontainer diterima',
        'Menunggu keberangkatan',
        'Dalam perjalanan',
        'Tiba di pelabuhan tujuan',
        'Selesai',
    ];

    public const ARRIVED_STATUSES = [
        'Tiba di pelabuhan tujuan',
        'Selesai',
    ];

    protected $fillable = [
        'booking_number',
        'customer_id',
        'container_id',
        'cargo_type_id',
        'vessel_id',
        'origin_port_id',
        'destination_port_id',
        'schedule_id',
        'departure_date',
        'estimated_arrival',
        'actual_arrival',
        'status',
        'latest_status_at',
        'delay_reported_at',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'estimated_arrival' => 'date',
            'actual_arrival' => 'date',
            'latest_status_at' => 'datetime',
            'delay_reported_at' => 'datetime',
            'operational_version' => 'integer',
            'delay_report_sequence' => 'integer',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('customer')) {
            return $query->whereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('user_id', $user->id));
        }

        return $query;
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query
            ->whereNull('actual_arrival')
            ->whereNotIn('status', self::ARRIVED_STATUSES)
            ->where(function (Builder $delayQuery): void {
                $delayQuery
                    ->whereNotNull('delay_reported_at')
                    ->orWhereDate('estimated_arrival', '<', today());
            });
    }

    public function isDelayed(): bool
    {
        return $this->actual_arrival === null
            && ! in_array($this->status, self::ARRIVED_STATUSES, true)
            && ($this->delay_reported_at !== null || $this->hasPassedEstimatedArrival());
    }

    public function hasPassedEstimatedArrival(): bool
    {
        return $this->estimated_arrival->startOfDay()->lt(today());
    }

    public function daysLate(): int
    {
        if (! $this->isDelayed()) {
            return 0;
        }

        if (! $this->hasPassedEstimatedArrival()) {
            return 0;
        }

        return max(1, (int) $this->estimated_arrival->startOfDay()->diffInDays(today()));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class)->latest();
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class)->oldest();
    }

    public function delayAlertDeliveries(): HasMany
    {
        return $this->hasMany(DelayAlertDelivery::class);
    }
}
