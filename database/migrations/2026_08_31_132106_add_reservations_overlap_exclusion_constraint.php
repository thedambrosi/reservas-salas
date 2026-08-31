<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Database-level guarantee that a room never holds two overlapping confirmed
     * reservations. This is the last line of defence: the application also checks
     * for conflicts inside a locking transaction, but the exclusion constraint
     * closes the race window between two concurrent requests.
     *
     * Uses half-open ranges `[starts_at, ends_at)` so back-to-back reservations
     * (one ends exactly when the next begins) do not collide.
     *
     * Only runs on Postgres; SQLite (used by the test suite) relies on the
     * application-level check.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(sprintf(
            "ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap
                EXCLUDE USING gist (
                    room_id WITH =,
                    tstzrange(starts_at, ends_at, '[)') WITH &&
                ) WHERE (status = '%s')",
            ReservationStatus::Confirmed->value,
        ));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
    }
};
