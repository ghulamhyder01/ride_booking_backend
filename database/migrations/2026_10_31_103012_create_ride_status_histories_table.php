<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')
                ->constrained('rides')
                ->restrictOnDelete();

            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);

            $table->string('changed_by_type', 20);
            $table->unsignedBigInteger('changed_by_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['ride_id', 'created_at']);
            $table->index(['changed_by_type', 'changed_by_id']);
        });

        DB::statement("
            ALTER TABLE ride_status_histories
            ADD CONSTRAINT ride_history_new_status_check
            CHECK (new_status IN (
                'searching', 'accepted', 'in_progress',
                'completed', 'cancelled'
            ))
        ");

        DB::statement("
            ALTER TABLE ride_status_histories
            ADD CONSTRAINT ride_history_old_status_check
            CHECK (
                old_status IS NULL OR old_status IN (
                    'searching', 'accepted', 'in_progress',
                    'completed', 'cancelled'
                )
            )
        ");

        DB::statement("
            ALTER TABLE ride_status_histories
            ADD CONSTRAINT ride_history_actor_type_check
            CHECK (changed_by_type IN ('rider', 'driver', 'system'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_status_histories');
    }
};