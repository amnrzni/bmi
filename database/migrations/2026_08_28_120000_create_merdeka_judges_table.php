<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The judging panel for the Merdeka corner contest — a designation list, not an
 * identity. Who a judge *is* stays in `users`, because SSO matches on the email
 * there and nowhere else.
 *
 * Separate from `role` on purpose: a founder judging the contest may or may not
 * also be an admin, and `role` is single-valued so it can't carry both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merdeka_judges', function (Blueprint $table) {
            $table->id();
            // Unique: a person sits on the panel once. Cascade removes the seat
            // if the roster row goes; their submitted scores survive it, since
            // those hang off users directly.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // "Pengasas", "Pengasas Bersama" — shown under their name on the
            // scoring screen. Free text; the panel is two people.
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merdeka_judges');
    }
};
