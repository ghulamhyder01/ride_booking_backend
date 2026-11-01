<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Ride;
use App\Models\Rider;
use App\Models\RideStatusHistory;
use App\Models\User;
use App\Services\DriverRideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class DriverRideServiceTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Successful acceptance
    |--------------------------------------------------------------------------
    */

    public function test_driver_can_accept_matching_searching_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $result = app(DriverRideService::class)->acceptRide(
            $driver,
            $ride->id
        );

        $this->assertSame('accepted', $result->status);

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'busy',
        ]);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('ride_status_histories', [
            'ride_id' => $ride->id,
            'old_status' => 'searching',
            'new_status' => 'accepted',
            'changed_by_type' => 'driver',
            'changed_by_id' => $driver->id,
        ]);

        $this->assertSame(
            1,
            DriverAssignment::where('ride_id', $ride->id)->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ride validation
    |--------------------------------------------------------------------------
    */

    public function test_driver_cannot_accept_ride_that_is_not_searching(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider, 'accepted');

        $this->assertConflict(fn () =>
            app(DriverRideService::class)->acceptRide($driver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);

        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_driver_cannot_accept_ride_with_different_vehicle_category(): void
    {
        $driver = $this->createDriver('economy');
        $rider = $this->createRider();
        $ride = $this->createRide($rider, 'searching', 'comfort');

        $this->assertConflict(fn () =>
            app(DriverRideService::class)->acceptRide($driver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);

        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_accepting_nonexistent_ride_returns_not_found_exception(): void
    {
        $driver = $this->createDriver();

        $this->expectException(ModelNotFoundException::class);

        app(DriverRideService::class)->acceptRide($driver, 999999);
    }

    /*
    |--------------------------------------------------------------------------
    | Driver availability
    |--------------------------------------------------------------------------
    */

    public function test_unavailable_driver_cannot_accept_ride(): void
    {
        $driver = $this->createDriver('economy', 'offline');
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $this->assertConflict(fn () =>
            app(DriverRideService::class)->acceptRide($driver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'offline',
        ]);

        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_driver_cannot_accept_another_ride_while_busy(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();

        $firstRide = $this->createRide($rider);
        $secondRide = $this->createRide($rider);

        $service = app(DriverRideService::class);

        $service->acceptRide($driver, $firstRide->id);

        $this->assertConflict(fn () =>
            $service->acceptRide($driver, $secondRide->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $firstRide->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('rides', [
            'id' => $secondRide->id,
            'status' => 'searching',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'busy',
        ]);

        $this->assertSame(
            1,
            DriverAssignment::where('driver_id', $driver->id)
                ->where('status', 'active')
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Multiple drivers competing for one ride
    |--------------------------------------------------------------------------
    */

    public function test_second_driver_cannot_accept_already_accepted_ride(): void
    {
        $firstDriver = $this->createDriver();
        $secondDriver = $this->createDriver();

        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(DriverRideService::class);

        $service->acceptRide($firstDriver, $ride->id);

        $this->assertConflict(fn () =>
            $service->acceptRide($secondDriver, $ride->id)
        );

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $firstDriver->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $secondDriver->id,
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $firstDriver->id,
            'availability' => 'busy',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $secondDriver->id,
            'availability' => 'available',
        ]);

        $this->assertSame(
            1,
            DriverAssignment::where('ride_id', $ride->id)
                ->where('status', 'active')
                ->count()
        );
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

    private function assertConflict(callable $callback): void
    {
        try {
            $callback();

            $this->fail('Expected a 409 Conflict exception.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_assigned_driver_can_start_accepted_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $service->acceptRide($driver, $ride->id);

        $startedRide = $service->startRide($driver, $ride->id);

        $this->assertSame('in_progress', $startedRide->status);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'busy',
        ]);

        $this->assertDatabaseHas('ride_status_histories', [
            'ride_id' => $ride->id,
            'old_status' => 'accepted',
            'new_status' => 'in_progress',
            'changed_by_type' => 'driver',
            'changed_by_id' => $driver->id,
        ]);
    }

    public function test_another_driver_cannot_start_someone_elses_ride(): void
    {
        $driver = $this->createDriver();
        $anotherDriver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $service->acceptRide($driver, $ride->id);

        $this->assertConflict(
            fn () => $service->startRide($anotherDriver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'accepted',
        ]);
    }

    public function test_driver_cannot_start_ride_that_is_not_accepted(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $this->assertConflict(
            fn () => $service->startRide($driver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'searching',
        ]);
    }

    public function test_driver_cannot_start_accepted_ride_without_active_assignment(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $ride = $this->createRide($rider);

        $ride->update(['status' => 'accepted']);

        $service = app(\App\Services\DriverRideService::class);

        $this->assertConflict(
            fn () => $service->startRide($driver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'accepted',
        ]);
    }

    public function test_starting_nonexistent_ride_returns_not_found_exception(): void
    {
        $driver = $this->createDriver();

        $service = app(\App\Services\DriverRideService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->startRide($driver, 999999);
    }

    public function test_assigned_driver_can_complete_in_progress_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $service->acceptRide($driver, $ride->id);
        $service->startRide($driver, $ride->id);

        $completedRide = $service->completeRide($driver, $ride->id);

        $this->assertSame('completed', $completedRide->status);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);

        $this->assertDatabaseHas('ride_status_histories', [
            'ride_id' => $ride->id,
            'old_status' => 'in_progress',
            'new_status' => 'completed',
            'changed_by_type' => 'driver',
            'changed_by_id' => $driver->id,
        ]);
    }

    public function test_another_driver_cannot_complete_ride(): void
    {
        $driver = $this->createDriver();
        $anotherDriver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $service->acceptRide($driver, $ride->id);
        $service->startRide($driver, $ride->id);

        $this->assertConflict(
            fn () => $service->completeRide($anotherDriver, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_driver_cannot_complete_ride_that_is_not_in_progress(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\DriverRideService::class);

        $this->assertConflict(
            fn () => $service->completeRide($driver, $ride->id)
        );
    }

    public function test_driver_cannot_complete_ride_without_active_assignment(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $ride->update(['status' => 'in_progress']);

        $service = app(\App\Services\DriverRideService::class);

        $this->assertConflict(
            fn () => $service->completeRide($driver, $ride->id)
        );
    }

    public function test_completing_nonexistent_ride_returns_not_found_exception(): void
    {
        $driver = $this->createDriver();

        $service = app(\App\Services\DriverRideService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->completeRide($driver, 999999);
    }

    public function test_rider_can_cancel_searching_ride(): void
    {
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\RiderRideService::class);

        $cancelledRide = $service->cancelRide($rider, $ride->id);

        $this->assertSame('cancelled', $cancelledRide->status);

        $this->assertDatabaseHas('ride_status_histories', [
            'ride_id' => $ride->id,
            'old_status' => 'searching',
            'new_status' => 'cancelled',
            'changed_by_type' => 'rider',
            'changed_by_id' => $rider->id,
        ]);
    }

    public function test_rider_can_cancel_accepted_ride_and_driver_is_released(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        app(\App\Services\DriverRideService::class)
            ->acceptRide($driver, $ride->id);

        $service = app(\App\Services\RiderRideService::class);

        $cancelledRide = $service->cancelRide($rider, $ride->id);

        $this->assertSame('cancelled', $cancelledRide->status);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'availability' => 'available',
        ]);
    }

    public function test_rider_cannot_cancel_another_riders_ride(): void
    {
        $rider = $this->createRider();
        $anotherRider = $this->createRider();
        $ride = $this->createRide($rider);

        $service = app(\App\Services\RiderRideService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->cancelRide($anotherRider, $ride->id);
    }

    public function test_rider_cannot_cancel_in_progress_ride(): void
    {
        $driver = $this->createDriver();
        $rider = $this->createRider();
        $ride = $this->createRide($rider);

        $driverService = app(\App\Services\DriverRideService::class);
        $driverService->acceptRide($driver, $ride->id);
        $driverService->startRide($driver, $ride->id);

        $service = app(\App\Services\RiderRideService::class);

        $this->assertConflict(
            fn () => $service->cancelRide($rider, $ride->id)
        );

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_cancelling_nonexistent_ride_returns_not_found_exception(): void
    {
        $rider = $this->createRider();

        $service = app(\App\Services\RiderRideService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->cancelRide($rider, 999999);
    }
}
