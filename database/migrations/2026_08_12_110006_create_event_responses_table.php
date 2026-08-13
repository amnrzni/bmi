<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An RSVP is a promise. Attendance is a fact, and lives in its own table —
 * only attendance feeds the team score (DECISIONS.md §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response', 8); // yes | no
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_responses');
    }
};
