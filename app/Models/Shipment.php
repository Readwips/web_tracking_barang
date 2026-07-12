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
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'estimated_arrival' => 'date',
            'actual_arrival' => 'date',
            'latest_status_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('customer')) {
            return $query->whereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('user_id', $user->id));
        }

        return $query;
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
}
