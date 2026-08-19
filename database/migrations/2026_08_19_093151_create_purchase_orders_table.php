<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_quotation_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->string('order_number')->unique();

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('delivery_fee', 15, 2)->default(0);

            $table->decimal('total_amount', 15, 2)->default(0);

            $table->char('currency', 3)->default('TZS');

            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'delivered',
                'completed',
                'cancelled',
            ])->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
