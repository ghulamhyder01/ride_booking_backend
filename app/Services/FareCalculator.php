<?php

namespace App\Services;

use InvalidArgumentException;

class FareCalculator
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public function calculate(
        float $pickupLatitude,
        float $pickupLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
        string $category
    ): array {
        $pricing = config("ride.pricing.{$category}");

        if (!$pricing) {
            throw new InvalidArgumentException(
                'Invalid ride category.'
            );
        }

        $distance = $this->calculateDistance(
            $pickupLatitude,
            $pickupLongitude,
            $destinationLatitude,
            $destinationLongitude
        );

        $distance = round($distance, 2);

        $baseFare = (float) $pricing['base_fare'];
        $perKmRate = (float) $pricing['per_km_rate'];
        $categoryMultiplier = 1.0;

        $estimatedFare = round(
            $baseFare + ($distance * $perKmRate),
            2
        );

        return [
            'distance_km' => $distance,
            'base_fare' => $baseFare,
            'per_km_rate' => $perKmRate,
            'category_multiplier' => $categoryMultiplier,
            'estimated_fare' => $estimatedFare,
            'currency' => 'PKR',
        ];
    }

    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $latDifference = deg2rad($lat2 - $lat1);
        $lonDifference = deg2rad($lon2 - $lon1);

        $a = sin($latDifference / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lonDifference / 2) ** 2;

        $centralAngle = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return self::EARTH_RADIUS_KM * $centralAngle;
    }
}