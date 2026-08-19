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
        Schema::create('buyer_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('request_number')->unique();

            $table->string('title');

            $table->text('description')->nullable();

            $table->enum('status', [
                'draft',
                'open',
                'closed',
                'cancelled',
            ])->default('draft');

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_requests');
    }
};
