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
    Schema::create('buyer_request_items', function (Blueprint $table) {
        $table->id();

        $table->foreignId('buyer_request_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('product_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->unsignedInteger('quantity');

        $table->string('unit')->default('piece');

        $table->text('notes')->nullable();

        $table->timestamps();

        $table->unique(
            ['buyer_request_id', 'product_id'],
            'buyer_request_product_unique'
        );
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_request_items');
    }
};
