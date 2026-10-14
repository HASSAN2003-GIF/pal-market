<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Account-level controls for administrators:
 *  - suspended_at blocks sign-in and every authenticated API call.
 *  - last_login_at gives admins basic activity visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('phone_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('suspended_at');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['suspended_at', 'last_login_at']);
        });
    }
};
