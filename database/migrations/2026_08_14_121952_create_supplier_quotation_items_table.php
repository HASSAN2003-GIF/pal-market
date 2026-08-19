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
        Schema::create('supplier_quotation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_quotation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->string('unit')->default('piece');

            $table->decimal('unit_price', 15, 2);

            $table->decimal('total_price', 15, 2);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['supplier_quotation_id', 'product_id'],
                'supplier_quotation_item_quotation_product_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quotation_items');
    }
};
