<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Registration previously required a TIN for every supplier and, because the
 * sign-up form never asked for one, fabricated a random 9-digit value. A TIN
 * is now optional at sign-up (still unique when provided) and is reviewed by
 * an administrator during supplier approval.
 *
 * Existing rows are untouched; fabricated TINs created before this change
 * cannot be distinguished automatically and should be reviewed by an admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tin_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rolling back would fail if any supplier has no TIN; only tighten the
        // column when that is safe.
        if (DB::table('suppliers')->whereNull('tin_number')->exists()) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tin_number')->nullable(false)->change();
        });
    }
};
