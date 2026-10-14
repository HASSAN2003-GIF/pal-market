<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order documents are rendered from a frozen snapshot of the order, so later
 * catalog or profile edits never change a document that was already issued.
 * Order lines also keep the product name they were ordered under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
        });

        DB::table('purchase_order_items')
            ->whereNull('product_name')
            ->update([
                'product_name' => DB::table('products')
                    ->select('name')
                    ->whereColumn('products.id', 'purchase_order_items.product_id')
                    ->limit(1),
            ]);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('fingerprint', 64);
            $table->string('reason', 40);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
