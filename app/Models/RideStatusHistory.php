<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ride_id',
        'old_status',
        'new_status',
        'changed_by_type',
        'changed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}