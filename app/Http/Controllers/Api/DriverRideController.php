<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\Request;
use App\Services\DriverRideService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Models\DriverAssignment;

class DriverRideController extends Controller
{
    public function availableRides(Request $request)
    {
        $driver = $request->user()->driver;

        $rides = Ride::query()
            ->where('status', 'searching')
            ->where('category', $driver->vehicle_category)
            ->with('fareEstimate')
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return response()->json([
            'data' => $rides->items(),
            'meta' => [
                'current_page' => $rides->currentPage(),
                'last_page' => $rides->lastPage(),
                'per_page' => $rides->perPage(),
                'total' => $rides->total(),
            ],
        ]);
    }
    
    public function acceptRide(Request $request,Ride $ride,DriverRideService $driverRideService) {
        $driver = $request->user()->driver;

        $acceptedRide = $driverRideService->acceptRide(
            $driver,
            $ride->id
        );

        return response()->json([
            'message' => 'Ride accepted successfully.',
            'data' => $acceptedRide,
        ], 200);
    }

    public function startRide(Request $request,Ride $ride,DriverRideService $driverRideService) {
        $driver = $request->user()->driver;

        $startedRide = $driverRideService->startRide(
            $driver,
            $ride->id
        );

        return response()->json([
            'message' => 'Ride started successfully.',
            'data' => $startedRide,
        ], 200);
    }

    public function completeRide(
        Request $request,
        Ride $ride,
        DriverRideService $driverRideService
    ) {
        $driver = $request->user()->driver;

        $completedRide = $driverRideService->completeRide(
            $driver,
            $ride->id
        );

        return response()->json([
            'message' => 'Ride completed successfully.',
            'data' => $completedRide,
        ], 200);
    }

    public function currentRide(Request $request)
    {
        $driver = $request->user()->driver;

        $assignment = DriverAssignment::query()
            ->where('driver_id', $driver->id)
            ->with('ride.fareEstimate')
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => $assignment,
        ]);
    }
}
