<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE buyer_requests
            MODIFY status ENUM(
                'draft',
                'open',
                'closed',
                'cancelled',
                'expired'
            ) NOT NULL DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE buyer_requests
            MODIFY status ENUM(
                'draft',
                'open',
                'closed',
                'cancelled'
            ) NOT NULL DEFAULT 'draft'
        ");
    }
};
