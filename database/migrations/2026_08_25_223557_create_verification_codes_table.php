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
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('channel', 20);

            $table->string('destination');

            $table->string('code', 6);

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')
                ->nullable();

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->timestamps();

            $table->index([
                'user_id',
                'channel',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};