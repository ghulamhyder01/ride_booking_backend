<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->cascadeOnDelete();

            $table->string('key', 100);
            $table->string('request_hash', 64);

            $table->foreignId('ride_id')
                ->nullable()
                ->constrained('rides')
                ->nullOnDelete();

            $table->timestamp('expires_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['rider_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};