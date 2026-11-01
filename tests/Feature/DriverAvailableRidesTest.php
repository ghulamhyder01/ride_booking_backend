<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Driver;
use App\Models\Rider;
use App\Models\Ride;
use App\Models\FareEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverAvailableRidesTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_economy_driver_sees_only_searching_economy_rides(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'phone' => '03000000111',
            'vehicle_category' => 'economy',
            'availability' => 'available',
        ]);

        $riderUser = User::factory()->create([
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'phone' => '03000000112',
        ]);

        $economyRide = Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Liberty Market',
            'destination_address' => 'Railway Station',
            'pickup_latitude' => 31.5100,
            'pickup_longitude' => 74.3440,
            'destination_latitude' => 31.5497,
            'destination_longitude' => 74.3436,
            'category' => 'economy',
            'status' => 'searching',
        ]);

        Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Gulberg',
            'destination_address' => 'DHA',
            'pickup_latitude' => 31.5200,
            'pickup_longitude' => 74.3500,
            'destination_latitude' => 31.4700,
            'destination_longitude' => 74.3800,
            'category' => 'comfort',
            'status' => 'searching',
        ]);

        Sanctum::actingAs($user, ['driver']);

        $response = $this->getJson('/api/driver/rides/available');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $economyRide->id)
            ->assertJsonPath('data.0.category', 'economy')
            ->assertJsonPath('data.0.status', 'searching');
    }

    public function test_comfort_driver_sees_only_comfort_rides(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        Driver::create([
            'user_id' => $user->id,
            'phone' => '03000000211',
            'vehicle_category' => 'comfort',
            'availability' => 'available',
        ]);

        $riderUser = User::factory()->create([
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'phone' => '03000000212',
        ]);

        $comfortRide = Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Gulberg',
            'destination_address' => 'DHA',
            'pickup_latitude' => 31.5200,
            'pickup_longitude' => 74.3500,
            'destination_latitude' => 31.4700,
            'destination_longitude' => 74.3800,
            'category' => 'comfort',
            'status' => 'searching',
        ]);

        Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Liberty Market',
            'destination_address' => 'Railway Station',
            'pickup_latitude' => 31.5100,
            'pickup_longitude' => 74.3440,
            'destination_latitude' => 31.5497,
            'destination_longitude' => 74.3436,
            'category' => 'economy',
            'status' => 'searching',
        ]);

        Sanctum::actingAs($user, ['driver']);

        $response = $this->getJson('/api/driver/rides/available');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $comfortRide->id)
            ->assertJsonPath('data.0.category', 'comfort');
    }

    public function test_driver_sees_only_searching_rides(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        Driver::create([
            'user_id' => $user->id,
            'phone' => '03000000311',
            'vehicle_category' => 'economy',
            'availability' => 'available',
        ]);

        $riderUser = User::factory()->create([
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'phone' => '03000000312',
        ]);

        $searchingRide = Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Gulberg',
            'destination_address' => 'DHA',
            'pickup_latitude' => 31.5200,
            'pickup_longitude' => 74.3500,
            'destination_latitude' => 31.4700,
            'destination_longitude' => 74.3800,
            'category' => 'economy',
            'status' => 'searching',
        ]);

        Ride::create([
            'rider_id' => $rider->id,
            'pickup_address' => 'Liberty Market',
            'destination_address' => 'Railway Station',
            'pickup_latitude' => 31.5100,
            'pickup_longitude' => 74.3440,
            'destination_latitude' => 31.5497,
            'destination_longitude' => 74.3436,
            'category' => 'economy',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($user, ['driver']);

        $response = $this->getJson('/api/driver/rides/available');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $searchingRide->id)
            ->assertJsonPath('data.0.status', 'searching');
    }

    public function test_rider_cannot_access_available_rides(): void
    {
        $user = User::factory()->create([
            'role' => 'rider',
        ]);

        Sanctum::actingAs($user, ['rider']);

        $response = $this->getJson('/api/driver/rides/available');

        $response->assertForbidden()
            ->assertJson([
                'message' => 'You are not authorized to access this resource.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_available_rides(): void
    {
        $response = $this->getJson('/api/driver/rides/available');

        $response->assertUnauthorized();
    }

    public function test_available_rides_are_paginated(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
        ]);

        Driver::create([
            'user_id' => $user->id,
            'phone' => '03000000411',
            'vehicle_category' => 'economy',
            'availability' => 'available',
        ]);

        $riderUser = User::factory()->create([
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'phone' => '03000000412',
        ]);

        for ($i = 0; $i < 12; $i++) {
            Ride::create([
                'rider_id' => $rider->id,
                'pickup_address' => 'Pickup ' . $i,
                'destination_address' => 'Destination ' . $i,
                'pickup_latitude' => 31.5200,
                'pickup_longitude' => 74.3500,
                'destination_latitude' => 31.4700,
                'destination_longitude' => 74.3800,
                'category' => 'economy',
                'status' => 'searching',
            ]);
        }

        Sanctum::actingAs($user, ['driver']);

        $response = $this->getJson('/api/driver/rides/available');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2);
    }
}