<?php

namespace App\Services;

use App\Models\FareEstimate;
use App\Models\IdempotencyKey;
use App\Models\Ride;
use App\Models\Rider;
use App\Models\RideStatusHistory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RideService
{
    public function __construct(
        private FareCalculator $fareCalculator
    ) {}

    public function createRide(
        Rider $rider,
        array $data,
        string $idempotencyKey
    ): Ride {
        $requestHash = hash('sha256', json_encode([
            'pickup_address' => $data['pickup_address'],
            'destination_address' => $data['destination_address'],
            'pickup_latitude' => (float) $data['pickup_latitude'],
            'pickup_longitude' => (float) $data['pickup_longitude'],
            'destination_latitude' => (float) $data['destination_latitude'],
            'destination_longitude' => (float) $data['destination_longitude'],
            'category' => $data['category'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $rider,
            $data,
            $idempotencyKey,
            $requestHash
        ) {
            // Insert the key only if it does not already exist.
            DB::table('idempotency_keys')->insertOrIgnore([
                'rider_id' => $rider->id,
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'created_at' => now(),
            ]);

            // Lock the key record to handle concurrent duplicate requests.
            $keyRecord = IdempotencyKey::where('rider_id', $rider->id)
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($keyRecord->request_hash !== $requestHash) {
                throw new ConflictHttpException(
                    'This idempotency key was already used with different ride details.'
                );
            }

            // Return the original ride for a repeated identical request.
            if ($keyRecord->ride_id !== null) {
                return Ride::with('fareEstimate')
                    ->findOrFail($keyRecord->ride_id);
            }

            $fare = $this->fareCalculator->calculate(
                (float) $data['pickup_latitude'],
                (float) $data['pickup_longitude'],
                (float) $data['destination_latitude'],
                (float) $data['destination_longitude'],
                $data['category']
            );

            $ride = Ride::create([
                'rider_id' => $rider->id,
                'pickup_address' => $data['pickup_address'],
                'destination_address' => $data['destination_address'],
                'pickup_latitude' => $data['pickup_latitude'],
                'pickup_longitude' => $data['pickup_longitude'],
                'destination_latitude' => $data['destination_latitude'],
                'destination_longitude' => $data['destination_longitude'],
                'category' => $data['category'],
                'status' => 'searching',
            ]);

            FareEstimate::create([
                'ride_id' => $ride->id,
                ...$fare,
            ]);

            RideStatusHistory::create([
                'ride_id' => $ride->id,
                'old_status' => null,
                'new_status' => 'searching',
                'changed_by_type' => 'rider',
                'changed_by_id' => $rider->id,
                'created_at' => now(),
            ]);

            $keyRecord->update([
                'ride_id' => $ride->id,
            ]);

            return $ride->load('fareEstimate');
        });
    }
}