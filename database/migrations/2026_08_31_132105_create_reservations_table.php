<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->comment('The person responsible for the reservation')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_series_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status')->default(ReservationStatus::Confirmed->value);
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index(['room_id', 'status']);
            $table->index('starts_at');
        });

        // Postgres: a partial index covering only active reservations keeps the overlap
        // lookup tight, since cancelled rows are never treated as a conflict.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                "CREATE INDEX reservations_active_window_idx ON reservations (room_id, starts_at, ends_at) WHERE status = '%s'",
                ReservationStatus::Confirmed->value,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
