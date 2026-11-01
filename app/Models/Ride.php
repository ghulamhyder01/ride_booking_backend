<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ride extends Model
{
    protected $fillable = [
        'rider_id',
        'pickup_address',
        'destination_address',
        'pickup_latitude',
        'pickup_longitude',
        'destination_latitude',
        'destination_longitude',
        'category',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function fareEstimate(): HasOne
    {
        return $this->hasOne(FareEstimate::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)
            ->where('status', 'active');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RideStatusHistory::class);
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(IdempotencyKey::class);
    }
}