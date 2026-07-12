<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use HasFactory;

    protected $fillable = ['container_number', 'container_type', 'status'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
