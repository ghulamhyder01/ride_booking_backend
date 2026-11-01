<?php

namespace App\Services;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Ride;
use App\Models\RideStatusHistory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DriverRideService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}
    public function acceptRide(Driver $driver, int $rideId): Ride
    {
        return DB::transaction(function () use ($driver, $rideId) {
            // Lock the ride so only one driver can accept it.
            $ride = Ride::whereKey($rideId)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock the driver to prevent accepting multiple rides.
            $driver = Driver::whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ride->status !== 'searching') {
                throw new ConflictHttpException(
                    'This ride is no longer available.'
                );
            }

            if ($driver->availability !== 'available') {
                throw new ConflictHttpException(
                    'You are not available to accept a ride.'
                );
            }

            if ($driver->vehicle_category !== $ride->category) {
                throw new ConflictHttpException(
                    'Your vehicle category does not match this ride.'
                );
            }

            DriverAssignment::create([
                'ride_id' => $ride->id,
                'driver_id' => $driver->id,
                'status' => 'active',
                'accepted_at' => now(),
            ]);

            $ride->update([
                'status' => 'accepted',
            ]);

            $driver->update([
                'availability' => 'busy',
            ]);

            RideStatusHistory::create([
                'ride_id' => $ride->id,
                'old_status' => 'searching',
                'new_status' => 'accepted',
                'changed_by_type' => 'driver',
                'changed_by_id' => $driver->id,
                'created_at' => now(),
            ]);

            return $ride->load('fareEstimate');
        });
    }

    public function startRide(Driver $driver, int $rideId): Ride
    {
        return DB::transaction(function () use ($driver, $rideId) {
            $ride = Ride::whereKey($rideId)
                ->lockForUpdate()
                ->firstOrFail();

            $driver = Driver::whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ride->status !== 'accepted') {
                throw new ConflictHttpException(
                    'Only accepted rides can be started.'
                );
            }

            $assignment = DriverAssignment::where('ride_id', $ride->id)
                ->where('driver_id', $driver->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$assignment) {
                throw new ConflictHttpException(
                    'You are not assigned to this ride.'
                );
            }

            $ride->update([
                'status' => 'in_progress',
            ]);

            RideStatusHistory::create([
                'ride_id' => $ride->id,
                'old_status' => 'accepted',
                'new_status' => 'in_progress',
                'changed_by_type' => 'driver',
                'changed_by_id' => $driver->id,
                'created_at' => now(),
            ]);

            return $ride->load('fareEstimate');
        });
    }

    public function completeRide(Driver $driver, int $rideId): Ride
    {
        return DB::transaction(function () use ($driver, $rideId) {
            $ride = Ride::whereKey($rideId)
                ->lockForUpdate()
                ->firstOrFail();

            $driver = Driver::whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ride->status !== 'in_progress') {
                throw new ConflictHttpException(
                    'Only in-progress rides can be completed.'
                );
            }

            $assignment = DriverAssignment::where('ride_id', $ride->id)
                ->where('driver_id', $driver->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$assignment) {
                throw new ConflictHttpException(
                    'You are not assigned to this ride.'
                );
            }

            $now = now();

            $ride->update([
                'status' => 'completed',
            ]);

            $assignment->update([
                'status' => 'completed',
                'ended_at' => $now,
            ]);

            $driver->update([
                'availability' => 'available',
            ]);

            RideStatusHistory::create([
                'ride_id' => $ride->id,
                'old_status' => 'in_progress',
                'new_status' => 'completed',
                'changed_by_type' => 'driver',
                'changed_by_id' => $driver->id,
                'created_at' => $now,
            ]);

            return $ride->load('fareEstimate');
        });
    }
}
