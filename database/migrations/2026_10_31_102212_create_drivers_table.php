<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('phone', 20)->unique();

            $table->string('vehicle_category', 20);

            $table->string('availability', 20)
                ->default('offline');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['availability', 'vehicle_category']);
        });

        DB::statement("
            ALTER TABLE drivers
            ADD CONSTRAINT drivers_vehicle_category_check
            CHECK (vehicle_category IN ('economy', 'comfort'))
        ");

        DB::statement("
            ALTER TABLE drivers
            ADD CONSTRAINT drivers_availability_check
            CHECK (availability IN ('available', 'busy', 'offline'))
        ");

        DB::statement("
            ALTER TABLE drivers
            ADD CONSTRAINT drivers_latitude_check
            CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)
        ");

        DB::statement("
            ALTER TABLE drivers
            ADD CONSTRAINT drivers_longitude_check
            CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};