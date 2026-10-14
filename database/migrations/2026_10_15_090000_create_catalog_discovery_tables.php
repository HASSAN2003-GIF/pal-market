<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search aliases (English/Swahili names, trade names, common misspellings)
 * and supplier proposals for products that are not in the catalog yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_sw')->nullable()->after('name');
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 120);
            $table->timestamps();

            $table->unique(['product_id', 'alias']);
        });

        Schema::create('product_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('name_sw', 120)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_proposals');
        Schema::dropIfExists('product_aliases');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('name_sw');
        });
    }
};
