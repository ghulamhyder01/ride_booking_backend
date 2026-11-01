<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ridePayload(): array
    {
        return [
            'pickup_address' => 'Liberty Market, Lahore',
            'destination_address' => 'Lahore Railway Station',
            'pickup_latitude' => 31.5100,
            'pickup_longitude' => 74.3440,
            'destination_latitude' => 31.5497,
            'destination_longitude' => 74.3436,
            'category' => 'economy',
        ];
    }

    private function createRider(): User
    {
        $user = User::factory()->create([
            'role' => 'rider',
        ]);

        Rider::create([
            'user_id' => $user->id,
            'phone' => '03000000001',
        ]);

        return $user;
    }

    public function test_authenticated_rider_can_create_a_ride(): void
    {
        $user = $this->createRider();

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'test-ride-001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.ride.status', 'searching')
            ->assertJsonPath('data.ride.category', 'economy')
            ->assertJsonPath('data.ride.fare_estimate.currency', 'PKR');

        $this->assertDatabaseCount('rides', 1);
        $this->assertDatabaseCount('fare_estimates', 1);
    }

    public function test_unauthenticated_user_cannot_create_a_ride(): void
    {
        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'test-ride-002',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_driver_cannot_create_a_rider_ride(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        Driver::create([
            'user_id' => $user->id,
            'phone' => '03000000002',
            'vehicle_category' => 'economy',
            'availability' => 'available',
        ]);

        Sanctum::actingAs($user, ['driver']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'test-ride-003',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_idempotency_key_is_required(): void
    {
        $user = $this->createRider();

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload());

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A valid Idempotency-Key header is required.'
            );

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_repeated_request_with_same_key_does_not_create_duplicate_ride(): void
    {
        $user = $this->createRider();

        Sanctum::actingAs($user, ['rider']);

        $headers = [
            'Idempotency-Key' => 'test-ride-005',
        ];

        $this->postJson('/api/rides', $this->ridePayload(), $headers)
            ->assertCreated();

        $this->postJson('/api/rides', $this->ridePayload(), $headers)
            ->assertCreated();

        $this->assertDatabaseCount('rides', 1);
        $this->assertDatabaseCount('fare_estimates', 1);
        $this->assertDatabaseCount('ride_status_histories', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_reusing_same_key_with_different_payload_returns_conflict(): void
    {
        $user = $this->createRider();

        Sanctum::actingAs($user, ['rider']);

        $headers = [
            'Idempotency-Key' => 'test-ride-006',
        ];

        $this->postJson('/api/rides', $this->ridePayload(), $headers)
            ->assertCreated();

        $differentPayload = $this->ridePayload();
        $differentPayload['category'] = 'comfort';

        $this->postJson('/api/rides', $differentPayload, $headers)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'This idempotency key was already used with different ride details.'
            );

        $this->assertDatabaseCount('rides', 1);
    }

    
    /*
    |--------------------------------------------------------------------------
    | Pickup ETA
    |--------------------------------------------------------------------------
    */

    public function test_ride_creation_returns_eta_for_nearest_matching_driver(): void
    {
        $user = $this->createRider();

        $pickupLat = 31.5100;
        $pickupLon = 74.3440;

        // Farther available economy driver.
        $this->createEtaDriver(
            'economy',
            'available',
            31.5300,
            74.3440
        );

        // Nearest available economy driver.
        $this->createEtaDriver(
            'economy',
            'available',
            31.5150,
            74.3440
        );

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'eta-test-001',
        ]);

        $expectedDistance = app(\App\Services\FareCalculator::class)
            ->calculateDistance(
                $pickupLat,
                $pickupLon,
                31.5150,
                74.3440
            );

        $expectedEta = (int) ceil(
            ($expectedDistance / 25) * 60
        );

        $response->assertCreated()
            ->assertJsonPath('data.pickup_eta_minutes', $expectedEta);
    }

    public function test_eta_ignores_busy_and_wrong_category_drivers(): void
    {
        $user = $this->createRider();

        // Closest driver, but wrong category.
        $this->createEtaDriver(
            'comfort',
            'available',
            31.5101,
            74.3440
        );

        // Economy driver, but busy.
        $this->createEtaDriver(
            'economy',
            'busy',
            31.5102,
            74.3440
        );

        // Eligible economy driver.
        $this->createEtaDriver(
            'economy',
            'available',
            31.5200,
            74.3440
        );

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'eta-test-002',
        ]);

        $expectedDistance = app(\App\Services\FareCalculator::class)
            ->calculateDistance(
                31.5100,
                74.3440,
                31.5200,
                74.3440
            );

        $expectedEta = (int) ceil(
            ($expectedDistance / 25) * 60
        );

        $response->assertCreated()
            ->assertJsonPath('data.pickup_eta_minutes', $expectedEta);
    }

    public function test_eta_ignores_drivers_without_coordinates(): void
    {
        $user = $this->createRider();

        $this->createEtaDriver(
            'economy',
            'available',
            null,
            null
        );

        $this->createEtaDriver(
            'economy',
            'available',
            31.5150,
            74.3440
        );

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'eta-test-003',
        ]);

        $expectedDistance = app(\App\Services\FareCalculator::class)
            ->calculateDistance(
                31.5100,
                74.3440,
                31.5150,
                74.3440
            );

        $expectedEta = (int) ceil(
            ($expectedDistance / 25) * 60
        );

        $response->assertCreated()
            ->assertJsonPath('data.pickup_eta_minutes', $expectedEta);
    }

    public function test_eta_is_null_when_no_eligible_driver_exists(): void
    {
        $user = $this->createRider();

        Sanctum::actingAs($user, ['rider']);

        $response = $this->postJson('/api/rides', $this->ridePayload(), [
            'Idempotency-Key' => 'eta-test-004',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pickup_eta_minutes', null);
    }

    private function createEtaDriver(
        string $category,
        string $availability,
        ?float $latitude,
        ?float $longitude
    ): Driver {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        return Driver::create([
            'user_id' => $user->id,
            'phone' => fake()->unique()->numerify('03#########'),
            'vehicle_category' => $category,
            'availability' => $availability,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

}
