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
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('quotation_number')->unique();

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('delivery_fee', 15, 2)->default(0);

            $table->decimal('total_amount', 15, 2)->default(0);

            $table->char('currency', 3)->default('TZS');

            $table->enum('status', [
                'draft',
                'submitted',
                'accepted',
                'rejected',
                'expired',
                'cancelled',
            ])->default('draft');

            $table->timestamp('valid_until')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['buyer_request_id', 'supplier_id'],
                'supplier_quotation_request_supplier_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quotations');
    }
};
