<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in health.
 *
 * Email is the sole key matching a QCXIS identity to a roster row, and a typo
 * produces no error anyone sees — the person is simply rejected at sign-in.
 * "Never signed in" is the only reliable signal that a row's email is wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
