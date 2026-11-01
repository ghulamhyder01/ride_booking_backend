<?php

namespace App\Services;

use App\Models\Driver;

class EtaCalculator
{
    public function __construct(
        private FareCalculator $fareCalculator
    ) {}

    public function estimatePickupEta(
        float $pickupLatitude,
        float $pickupLongitude,
        string $category
    ): ?int {
        $drivers = Driver::query()
            ->where('availability', 'available')
            ->where('vehicle_category', $category)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        if ($drivers->isEmpty()) {
            return null;
        }

        $nearestDistance = $drivers->map(function ($driver) use (
            $pickupLatitude,
            $pickupLongitude
        ) {
            return $this->fareCalculator->calculateDistance(
                $pickupLatitude,
                $pickupLongitude,
                (float) $driver->latitude,
                (float) $driver->longitude
            );
        })->min();

        $averageSpeed = (float) config('ride.eta.average_speed_kmh');

        if ($averageSpeed <= 0) {
            throw new \LogicException('Average speed must be greater than zero.');
        }

        return (int) ceil(($nearestDistance / $averageSpeed) * 60);
    }
}