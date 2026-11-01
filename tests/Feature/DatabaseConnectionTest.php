<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_phpunit_uses_postgresql_test_database(): void
    {
        $this->assertSame(
            'pgsql',
            DB::connection()->getDriverName()
        );

        $this->assertSame(
            'ride_booking_test',
            DB::connection()->getDatabaseName()
        );
    }
}