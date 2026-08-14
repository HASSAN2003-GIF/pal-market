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
    Schema::create('buyer_profiles', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnDelete()
            ->unique();

        $table->string('business_name');
        $table->string('business_type')->nullable();
        $table->string('tin_number')->nullable();
        $table->text('description')->nullable();

        $table->enum('status', [
            'active',
            'suspended',
        ])->default('active');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_profiles');
    }
};
