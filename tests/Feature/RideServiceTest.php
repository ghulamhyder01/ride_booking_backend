<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\User;
use App\Services\RideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class RideServiceTest extends TestCase
{
    use RefreshDatabase;

    private Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Test Rider',
            'email' => 'test-rider@example.com',
            'password' => 'Password@123',
            'role' => 'rider',
        ]);

        $this->rider = Rider::create([
            'user_id' => $user->id,
            'phone' => '03001234567',
        ]);
    }

    private function rideData(): array
    {
        return [
            'pickup_address' => 'Liberty Market, Lahore',
            'destination_address' => 'Gulberg, Lahore',
            'pickup_latitude' => 31.5204,
            'pickup_longitude' => 74.3587,
            'destination_latitude' => 31.5300,
            'destination_longitude' => 74.3500,
            'category' => 'economy',
        ];
    }

    public function test_it_creates_ride_with_fare_and_status_history(): void
    {
        $service = app(RideService::class);

        $ride = $service->createRide(
            $this->rider,
            $this->rideData(),
            'test-key-001'
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'rider_id' => $this->rider->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseHas('fare_estimates', [
            'ride_id' => $ride->id,
            'estimated_fare' => 140.50,
            'currency' => 'PKR',
        ]);

        $this->assertDatabaseHas('ride_status_histories', [
            'ride_id' => $ride->id,
            'new_status' => 'searching',
            'changed_by_type' => 'rider',
        ]);

        $this->assertDatabaseHas('idempotency_keys', [
            'rider_id' => $this->rider->id,
            'key' => 'test-key-001',
            'ride_id' => $ride->id,
        ]);
    }

    public function test_same_idempotency_key_returns_existing_ride(): void
    {
        $service = app(RideService::class);
        $data = $this->rideData();

        $firstRide = $service->createRide(
            $this->rider,
            $data,
            'test-key-002'
        );

        $secondRide = $service->createRide(
            $this->rider,
            $data,
            'test-key-002'
        );

        $this->assertSame($firstRide->id, $secondRide->id);

        $this->assertDatabaseCount('rides', 1);
        $this->assertDatabaseCount('fare_estimates', 1);
        $this->assertDatabaseCount('ride_status_histories', 1);
    }

    public function test_same_idempotency_key_with_different_payload_returns_conflict(): void
    {
        $service = app(RideService::class);
        $data = $this->rideData();

        $service->createRide(
            $this->rider,
            $data,
            'test-key-003'
        );

        $differentData = $data;
        $differentData['category'] = 'comfort';

        $this->expectException(ConflictHttpException::class);

        $service->createRide(
            $this->rider,
            $differentData,
            'test-key-003'
        );
    }
}