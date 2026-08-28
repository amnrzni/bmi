<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cut the contest loose from app auth.
 *
 * Judges no longer sign in through QCXIS, so they no longer need a `users` row:
 * the panel is a list of names and emails, and typing a listed email is the
 * whole door. Scores therefore key on the panel seat rather than on a user.
 *
 * Both tables are recreated rather than altered. They are days old, the panel
 * was never populated in anger, and a half-migrated shape with nullable columns
 * standing in for the old ones would outlive the contest itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Accounts that only ever existed to seat a judge go with the old shape.
        // Left behind, each is a working QCXIS login for whoever owns that
        // mailbox, pointing at a challenge they were never part of.
        $seatedUserIds = Schema::hasTable('merdeka_judges')
            ? DB::table('merdeka_judges')->pluck('user_id')
            : collect();

        Schema::dropIfExists('merdeka_scores');

        if ($seatedUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $seatedUserIds)
                ->where('is_participant', false)
                ->where('role', 'staff')
                ->whereNull('last_login_at')
                ->delete();
        }

        Schema::dropIfExists('merdeka_judges');

        Schema::create('merdeka_judges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The entire credential. Stored lowercased so it can never diverge
            // from the comparison the sign-in screen makes.
            $table->string('email')->unique();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('merdeka_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merdeka_judge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            // criterion id => 1..5. The bands are what the judge chose;
            // everything else is derived.
            $table->json('scales');
            // Derived server-side on every write, never read from the client
            // (DECISIONS.md §4).
            $table->decimal('total', 5, 2);

            $table->text('ulasan')->nullable();
            $table->longText('signature');

            $table->timestamp('submitted_at');
            $table->timestamps();

            // Sheets lock on submit, so this also enforces "once only".
            $table->unique(['merdeka_judge_id', 'department_id']);
        });
    }

    public function down(): void
    {
        // Restores the shape, not the rows — the old tables keyed on users that
        // this migration deleted.
        Schema::dropIfExists('merdeka_scores');
        Schema::dropIfExists('merdeka_judges');

        Schema::create('merdeka_judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('merdeka_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->json('scales');
            $table->decimal('total', 5, 2);
            $table->text('ulasan')->nullable();
            $table->longText('signature');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['user_id', 'department_id']);
        });
    }
};
