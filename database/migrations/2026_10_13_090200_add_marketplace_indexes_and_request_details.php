<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * - Indexes for the status-filtered listings used by the market feed,
 *   dashboards and the expiry jobs.
 * - Delivery details on buyer requests: suppliers cannot price transport
 *   without knowing where materials go and by when.
 * - submitted_at on quotations so buyers can see when a bid arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->string('delivery_region')->nullable()->after('description');
            $table->string('delivery_address', 500)->nullable()->after('delivery_region');
            $table->date('needed_by')->nullable()->after('delivery_address');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['status', 'expires_at']);
        });

        Schema::table('supplier_quotations', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->index(['status', 'valid_until']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropIndex(['status']));

        Schema::table('supplier_quotations', function (Blueprint $table) {
            $table->dropIndex(['status', 'valid_until']);
            $table->dropColumn('submitted_at');
        });

        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['delivery_region', 'delivery_address', 'needed_by', 'published_at']);
        });
    }
};
