<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The point of the whole app. One row per staff per week, never overwritten
 * across weeks — the weekly series IS the product.
 *
 * Height is deliberately NOT snapshotted here: correcting a height recomputes
 * historical BMIs (DECISIONS.md §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weigh_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The Monday. Never a bare week number — HANDOFF.md §2.
            $table->date('week_start_date');

            $table->decimal('weight_kg', 5, 2);
            // Convenience column for cheap reads. Computed server-side on save,
            // never trusted from the client. Null when height is unknown.
            $table->decimal('bmi', 5, 2)->nullable();

            $table->string('photo_path')->nullable();

            // Provenance. Staff don't self-log, so the user-facing view shows
            // who recorded their number and when.
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->string('notes')->nullable();

            $table->timestamps();

            // The key updateOrCreate writes against.
            $table->unique(['user_id', 'week_start_date']);
            $table->index('week_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weigh_ins');
    }
};
