<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Adds the "expired" status to buyer_requests.status.
 *
 * Originally written as a raw MySQL "ALTER TABLE ... MODIFY ENUM" statement,
 * which made the schema unusable on any other driver (including the SQLite
 * test database). Schema::change() produces the identical MySQL column
 * definition and also works on SQLite/PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'closed', 'cancelled', 'expired'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'closed', 'cancelled'])
                ->default('draft')
                ->change();
        });
    }
};
