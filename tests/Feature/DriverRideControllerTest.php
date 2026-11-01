<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Ride;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Services\RiderRideService;

class DriverRideControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Successful acceptance
    |--------------------------------------------------------------------------
    */

    public function test_driver_can_accept_a_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        Sanctum::actingAs($driver->user, ['driver']);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Ride accepted successfully.')
            ->assertJsonPath('data.id', $ride->id)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'busy',
        ]);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication and authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_accept_ride(): void
    {
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertUnauthorized();
    }

    public function test_rider_cannot_accept_ride(): void
    {
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        Sanctum::actingAs($rider->user, ['rider']);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Invalid acceptance requests
    |--------------------------------------------------------------------------
    */

    public function test_driver_cannot_accept_already_accepted_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider, 'accepted');

        Sanctum::actingAs($driver->user, ['driver']);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertStatus(409);

        $this->assertDatabaseCount('driver_assignments', 0);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);
    }

    public function test_driver_cannot_accept_ride_with_wrong_category(): void
    {
        $driver = $this->createDriver('economy');
        $rider = $this->createRider();
        $ride = $this->createRide($rider, 'searching', 'comfort');

        Sanctum::actingAs($driver->user, ['driver']);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_busy_driver_cannot_accept_another_ride(): void
    {
        $driver = $this->createDriver('economy', 'busy');
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        Sanctum::actingAs($driver->user, ['driver']);

        $response = $this->postJson(
            "/api/driver/rides/{$ride->id}/accept"
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_accepting_nonexistent_ride_returns_404(): void
    {
        $driver = $this->createDriver();

        Sanctum::actingAs($driver->user, ['driver']);

        $response = $this->postJson(
            '/api/driver/rides/999999/accept'
        );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Test helpers
    |--------------------------------------------------------------------------
    */

    private function createDriver(
        string $category = 'economy',
        string $availability = 'available'
    ): Driver {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        return Driver::create([
            'user_id' => $user->id,
            'phone' => sprintf('0300%07d', $user->id),
            'vehicle_category' => $category,
            'availability' => $availability,
        ]);
    }

    private function createRider(): Rider
    {
        $user = User::factory()->create([
            'role' => 'rider',
        ]);

        return Rider::create([
            'user_id' => $user->id,
            'phone' => sprintf('0301%07d', $user->id),
        ]);
    }

    private function createRide(
        Rider $rider,
        string $status = 'searching',
        string $category = 'economy'
    ): Ride {
        return Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Liberty Market',
            'destination_address' => 'DHA',
            'pickup_latitude' => 31.5100,
            'pickup_longitude' => 74.3440,
            'destination_latitude' => 31.4700,
            'destination_longitude' => 74.3800,
            'category' => $category,
            'status' => $status,
        ]);
    }

    public function test_driver_can_start_accepted_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);
        $service->acceptRide($driver, $ride->id);

        $response = $this->actingAs($driver->user)
            ->postJson("/api/driver/rides/{$ride->id}/start");

        $response->assertOk()
            ->assertJsonPath('message', 'Ride started successfully.')
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_unauthenticated_user_cannot_start_ride(): void
    {
        $response = $this->postJson('/api/driver/rides/999/start');

        $response->assertUnauthorized();
    }

    public function test_rider_cannot_start_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $response = $this->actingAs($rider->user)
            ->postJson("/api/driver/rides/{$ride->id}/start");

        $response->assertForbidden();
    }

    public function test_driver_cannot_start_another_drivers_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $anotherDriver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);
        $service->acceptRide($driver, $ride->id);

        $response = $this->actingAs($anotherDriver->user)
            ->postJson("/api/driver/rides/{$ride->id}/start");

        $response->assertStatus(409);
    }

    public function test_driver_cannot_start_searching_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $response = $this->actingAs($driver->user)
            ->postJson("/api/driver/rides/{$ride->id}/start");

        $response->assertStatus(409);
    }

    public function test_starting_nonexistent_ride_through_api_returns_404(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver->user)
            ->postJson('/api/driver/rides/999999/start');

        $response->assertNotFound();
    }

    public function test_driver_can_complete_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);
        $service->acceptRide($driver, $ride->id);
        $service->startRide($driver, $ride->id);

        $response = $this->actingAs($driver->user)
            ->postJson("/api/driver/rides/{$ride->id}/complete");

        $response->assertOk()
            ->assertJsonPath('message', 'Ride completed successfully.')
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);
    }

    public function test_unauthenticated_user_cannot_complete_ride(): void
    {
        $this->postJson('/api/driver/rides/999/complete')
            ->assertUnauthorized();
    }

    public function test_rider_cannot_complete_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $this->actingAs($rider->user)
            ->postJson("/api/driver/rides/{$ride->id}/complete")
            ->assertForbidden();
    }

    public function test_another_driver_cannot_complete_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $anotherDriver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);
        $service->acceptRide($driver, $ride->id);
        $service->startRide($driver, $ride->id);

        $this->actingAs($anotherDriver->user)
            ->postJson("/api/driver/rides/{$ride->id}/complete")
            ->assertStatus(409);
    }

    public function test_driver_cannot_complete_searching_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $this->actingAs($driver->user)
            ->postJson("/api/driver/rides/{$ride->id}/complete")
            ->assertStatus(409);
    }

    public function test_completing_nonexistent_ride_through_api_returns_404(): void
    {
        $driver = $this->createDriver();

        $this->actingAs($driver->user)
            ->postJson('/api/driver/rides/999999/complete')
            ->assertNotFound();
    }
    public function test_rider_can_cancel_searching_ride_through_api(): void
    {
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $response = $this->actingAs($rider->user)
            ->postJson("/api/rides/{$ride->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Ride cancelled successfully.')
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_rider_can_cancel_accepted_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        app(\App\Services\DriverRideService::class)
            ->acceptRide($driver, $ride->id);

        $response = $this->actingAs($rider->user)
            ->postJson("/api/rides/{$ride->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_unauthenticated_user_cannot_cancel_ride(): void
    {
        $this->postJson('/api/rides/999/cancel')
            ->assertUnauthorized();
    }

    public function test_driver_cannot_cancel_ride_through_rider_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $this->actingAs($driver->user)
            ->postJson("/api/rides/{$ride->id}/cancel")
            ->assertForbidden();
    }

    public function test_rider_cannot_cancel_another_riders_ride_through_api(): void
    {
        $rider = $this->createRider();
        $anotherRider = $this->createRider();
        $ride = $this->createRide($rider);

        $this->actingAs($anotherRider->user)
            ->postJson("/api/rides/{$ride->id}/cancel")
            ->assertNotFound();
    }

    public function test_rider_cannot_cancel_in_progress_ride_through_api(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);
        $service->acceptRide($driver, $ride->id);
        $service->startRide($driver, $ride->id);

        $this->actingAs($rider->user)
            ->postJson("/api/rides/{$ride->id}/cancel")
            ->assertStatus(409);
    }

    public function test_cancelling_nonexistent_ride_through_api_returns_404(): void
    {
        $rider = $this->createRider();

        $this->actingAs($rider->user)
            ->postJson('/api/rides/999999/cancel')
            ->assertNotFound();
    }

    
    /*
    |--------------------------------------------------------------------------
    | Rider ride history and details
    |--------------------------------------------------------------------------
    */

    public function test_rider_can_view_own_ride_history(): void
    {
        $rider = $this->createRider();

        $ride1 = $this->createRide($rider);
        $ride2 = $this->createRide($rider, 'completed');

        Sanctum::actingAs($rider->user, ['rider']);

        $this->getJson('/api/rides')
            ->assertOk()
            ->assertJsonPath('message', 'Ride history retrieved successfully.')
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['id' => $ride1->id])
            ->assertJsonFragment(['id' => $ride2->id]);
    }

    public function test_ride_history_is_paginated(): void
    {
        $rider = $this->createRider();

        for ($i = 0; $i < 12; $i++) {
            $this->createRide($rider);
        }

        Sanctum::actingAs($rider->user, ['rider']);

        $this->getJson('/api/rides')
            ->assertOk()
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 12)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_rider_can_view_own_ride_details(): void
    {
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        Sanctum::actingAs($rider->user, ['rider']);

        $this->getJson("/api/rides/{$ride->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ride details retrieved successfully.')
            ->assertJsonPath('data.id', $ride->id)
            ->assertJsonPath('data.status', 'searching')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'fare_estimate',
                    'status_histories',
                    'assignments',
                ],
            ]);
    }

    public function test_rider_cannot_view_another_riders_ride_details(): void
    {
        $owner = $this->createRider();
        $anotherRider = $this->createRider();
        $ride = $this->createRide($owner);

        Sanctum::actingAs($anotherRider->user, ['rider']);

        $this->getJson("/api/rides/{$ride->id}")
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_view_ride_history_or_details(): void
    {
        $this->getJson('/api/rides')->assertUnauthorized();

        $this->getJson('/api/rides/999999')->assertUnauthorized();
    }

    public function test_driver_cannot_access_rider_ride_history_or_details(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        Sanctum::actingAs($driver->user, ['driver']);

        $this->getJson('/api/rides')->assertForbidden();

        $this->getJson("/api/rides/{$ride->id}")
            ->assertForbidden();
    }

}
