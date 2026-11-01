<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\RiderRideService;
use App\Models\Ride;
use App\Services\EtaCalculator;

class RideController extends Controller
{
    public function __construct(
        private RideService $rideService,
        private RiderRideService $riderRideService,
        private EtaCalculator $etaCalculator
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_address' => ['required', 'string', 'max:255'],
            'destination_address' => ['required', 'string', 'max:255'],

            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],

            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],

            'category' => ['required', Rule::in(['economy', 'comfort'])],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey || strlen($idempotencyKey) > 100) {
            return response()->json([
                'message' => 'A valid Idempotency-Key header is required.',
            ], 422);
        }

        $rider = $request->user()->rider;

        if (!$rider) {
            return response()->json([
                'message' => 'Rider profile not found.',
            ], 403);
        }

        $ride = $this->rideService->createRide(
            $rider,
            $validated,
            $idempotencyKey
        );

        $pickupEta = $this->etaCalculator->estimatePickupEta(
            (float) $validated['pickup_latitude'],
            (float) $validated['pickup_longitude'],
            $validated['category']
        );
        
        return response()->json([
            'message' => 'Ride created successfully.',
             'data' => [
                'ride' => $ride,
                'pickup_eta_minutes' => $pickupEta,
            ],
        ], 201);
    }

    public function cancel(
        Request $request,
        Ride $ride,
        RiderRideService $riderRideService
    ) {
        $rider = $request->user()->rider;

        $cancelledRide = $riderRideService->cancelRide(
            $rider,
            $ride->id
        );

        return response()->json([
            'message' => 'Ride cancelled successfully.',
            'data' => $cancelledRide,
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        if (!$rider) {
            return response()->json([
                'message' => 'Rider profile not found.',
            ], 403);
        }

        $rides = $rider->rides()
            ->with('fareEstimate')
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Ride history retrieved successfully.',
            'data' => $rides,
        ]);
    }

    public function show(Request $request, Ride $ride): JsonResponse
    {
        $rider = $request->user()->rider;

        if (!$rider) {
            return response()->json([
                'message' => 'Rider profile not found.',
            ], 403);
        }

        $ride = $rider->rides()
            ->with([
                'fareEstimate',
                'statusHistories',
                'assignments',
            ])
            ->findOrFail($ride->id);

        return response()->json([
            'message' => 'Ride details retrieved successfully.',
            'data' => $ride,
        ]);
    }
}