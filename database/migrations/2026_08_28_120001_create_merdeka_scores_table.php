<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One submitted scoresheet: one judge, one department's corner.
 *
 * Keyed on `user_id` rather than `merdeka_judges.id` so that removing someone
 * from the panel never deletes the marks they already filed — a submitted score
 * is a record of what happened, and the panel list is just current membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merdeka_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            // criterion id => 1..5, e.g. {"tema":5,"kreativiti":4,...}. The
            // scale is what the judge chose; everything else is derived.
            $table->json('scales');
            // Derived from `scales` server-side on every write and never read
            // from the client (DECISIONS.md §4). Stored so the admin summary
            // and any later export agree without recomputing.
            $table->decimal('total', 5, 2);

            $table->text('ulasan')->nullable();
            // PNG data URL from the signature canvas. Inline rather than on a
            // disk: ~14 rows of a few KB each, and it backs up with the DB.
            $table->longText('signature');

            $table->timestamp('submitted_at');
            $table->timestamps();

            // Scores lock on submit, so this is also what enforces "once only".
            $table->unique(['user_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merdeka_scores');
    }
};
