<?php

namespace Tests\Feature;

use App\Services\FareCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class FareCalculatorTest extends TestCase
{
    public function test_it_calculates_economy_fare(): void
    {
        $calculator = new FareCalculator();

        $result = $calculator->calculate(
            31.5204,
            74.3587,
            31.5300,
            74.3500,
            'economy'
        );

        $this->assertEquals(1.35, $result['distance_km']);
        $this->assertEquals(140.50, $result['estimated_fare']);
        $this->assertEquals('PKR', $result['currency']);
    }

    public function test_it_calculates_comfort_fare(): void
    {
        $calculator = new FareCalculator();

        $result = $calculator->calculate(
            31.5204,
            74.3587,
            31.5300,
            74.3500,
            'comfort'
        );

        $this->assertEquals(204.00, $result['estimated_fare']);
    }

    public function test_it_rejects_invalid_ride_category(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $calculator = new FareCalculator();

        $calculator->calculate(
            31.5204,
            74.3587,
            31.5300,
            74.3500,
            'premium'
        );
    }
}