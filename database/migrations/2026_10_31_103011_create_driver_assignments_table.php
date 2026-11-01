<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')
                ->constrained('rides')
                ->restrictOnDelete();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->string('status', 20)->default('active');

            $table->timestamp('accepted_at');
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });

        // One active driver assignment per ride.
        DB::statement("
            CREATE UNIQUE INDEX unique_active_ride_assignment
            ON driver_assignments (ride_id)
            WHERE status = 'active'
        ");

        // One active ride per driver.
        DB::statement("
            CREATE UNIQUE INDEX unique_active_driver_assignment
            ON driver_assignments (driver_id)
            WHERE status = 'active'
        ");

        DB::statement("
            ALTER TABLE driver_assignments
            ADD CONSTRAINT driver_assignments_status_check
            CHECK (status IN ('active', 'completed', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE driver_assignments
            ADD CONSTRAINT driver_assignments_dates_check
            CHECK (ended_at IS NULL OR ended_at >= accepted_at)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};