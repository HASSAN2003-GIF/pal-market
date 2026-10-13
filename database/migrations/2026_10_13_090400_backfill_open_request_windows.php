<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Requests published before quoting windows existed have no expires_at, so
 * they would stay open indefinitely. Give each one the standard 14-day window
 * counted from this deployment, and record when it was published (best known
 * value: its last update).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('buyer_requests')
            ->where('status', 'open')
            ->whereNull('expires_at')
            ->update([
                'published_at' => DB::raw('COALESCE(published_at, updated_at)'),
                'expires_at' => now()->addDays(14),
            ]);
    }

    public function down(): void
    {
        // Data backfill: nothing to undo safely.
    }
};
