<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fare_estimates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')
                ->unique()
                ->constrained('rides')
                ->cascadeOnDelete();

            $table->decimal('distance_km', 8, 2);

            $table->decimal('base_fare', 10, 2);
            $table->decimal('per_km_rate', 10, 2);
            $table->decimal('category_multiplier', 5, 2);

            $table->decimal('estimated_fare', 10, 2);
            $table->decimal('final_fare', 10, 2)->nullable();

            $table->string('currency', 3)->default('PKR');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fare_estimates');
    }
};