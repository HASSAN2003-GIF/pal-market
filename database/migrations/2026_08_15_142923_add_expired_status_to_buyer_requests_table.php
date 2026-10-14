<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Adds the "expired" status to buyer_requests.status.
 *
 * Originally written as a raw MySQL "ALTER TABLE ... MODIFY ENUM" statement,
 * which made the schema unusable on any other driver. Schema::change()
 * produces the identical MySQL column definition and works on SQLite.
 * PostgreSQL stores enums as a varchar with a CHECK constraint, which
 * Schema::change() cannot alter, so the constraint is replaced directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setStatuses(['draft', 'open', 'closed', 'cancelled', 'expired']);
    }

    public function down(): void
    {
        $this->setStatuses(['draft', 'open', 'closed', 'cancelled']);
    }

    private function setStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", $statuses));
            DB::statement('alter table buyer_requests drop constraint if exists buyer_requests_status_check');
            DB::statement("alter table buyer_requests add constraint buyer_requests_status_check check (status in ({$list}))");

            return;
        }

        Schema::table('buyer_requests', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('draft')->change();
        });
    }
};
