<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_inventories', function (Blueprint $table) {
    $table->id();

    $table->foreignId('supplier_product_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('supplier_location_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->unsignedInteger('quantity')->default(0);

    $table->unsignedInteger('low_stock_threshold')->default(0);

    $table->boolean('is_available')->default(true);

    $table->timestamps();

    $table->unique(
    ['supplier_product_id', 'supplier_location_id'],
    'supplier_inventory_product_location_unique'
);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_inventories');
    }
};
