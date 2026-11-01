<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FareEstimate extends Model
{
    protected $fillable = [
        'ride_id',
        'distance_km',
        'base_fare',
        'per_km_rate',
        'category_multiplier',
        'estimated_fare',
        'final_fare',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'base_fare' => 'decimal:2',
            'per_km_rate' => 'decimal:2',
            'category_multiplier' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'final_fare' => 'decimal:2',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}