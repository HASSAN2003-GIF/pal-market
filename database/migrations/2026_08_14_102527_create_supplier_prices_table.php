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
    Schema::create('supplier_prices', function (Blueprint $table) {
        $table->id();

        $table->foreignId('supplier_product_id')
            ->constrained('supplier_products')
            ->cascadeOnDelete();

        $table->decimal('price', 15, 2);

        $table->string('currency', 3)->default('TZS');

        $table->string('unit')->default('piece');

        $table->boolean('is_active')->default(true);

        $table->timestamp('effective_from')->nullable();

        $table->timestamp('effective_until')->nullable();

        $table->timestamps();

        $table->index([
            'supplier_product_id',
            'is_active',
        ]);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_prices');
    }
};
