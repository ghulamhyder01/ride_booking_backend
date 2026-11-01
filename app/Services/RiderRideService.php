<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Ride;
use App\Models\RideStatusHistory;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RiderRideService
{
    public function cancelRide(Rider $rider, int $rideId): Ride
    {
        return DB::transaction(function () use ($rider, $rideId) {
            $ride = Ride::whereKey($rideId)
                ->where('rider_id', $rider->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($ride->status, ['searching', 'accepted'], true)) {
                throw new ConflictHttpException(
                    'This ride cannot be cancelled.'
                );
            }

            $oldStatus = $ride->status;
            $now = now();

            if ($oldStatus === 'accepted') {
                $assignment = DriverAssignment::where('ride_id', $ride->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$assignment) {
                    throw new ConflictHttpException(
                        'Active driver assignment not found.'
                    );
                }

                $driver = Driver::whereKey($assignment->driver_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $assignment->update([
                    'status' => 'cancelled',
                    'ended_at' => $now,
                ]);

                $driver->update([
                    'availability' => 'available',
                ]);
            }

            $ride->update([
                'status' => 'cancelled',
            ]);

            RideStatusHistory::create([
                'ride_id' => $ride->id,
                'old_status' => $oldStatus,
                'new_status' => 'cancelled',
                'changed_by_type' => 'rider',
                'changed_by_id' => $rider->id,
                'created_at' => $now,
            ]);

            return $ride->load('fareEstimate');
        });
    }
}