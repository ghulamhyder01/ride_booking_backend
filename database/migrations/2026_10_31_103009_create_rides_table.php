<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->string('pickup_address');
            $table->string('destination_address');

            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);

            $table->decimal('destination_latitude', 10, 7);
            $table->decimal('destination_longitude', 10, 7);

            $table->string('category', 20);

            $table->string('status', 20)->default('searching');

            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['rider_id', 'created_at']);
        });

        DB::statement("
            ALTER TABLE rides
            ADD CONSTRAINT rides_category_check
            CHECK (category IN ('economy', 'comfort'))
        ");

        DB::statement("
            ALTER TABLE rides
            ADD CONSTRAINT rides_status_check
            CHECK (status IN (
                'searching',
                'accepted',
                'in_progress',
                'completed',
                'cancelled'
            ))
        ");

        DB::statement("
            ALTER TABLE rides
            ADD CONSTRAINT rides_coordinates_check
            CHECK (
                pickup_latitude BETWEEN -90 AND 90
                AND pickup_longitude BETWEEN -180 AND 180
                AND destination_latitude BETWEEN -90 AND 90
                AND destination_longitude BETWEEN -180 AND 180
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};