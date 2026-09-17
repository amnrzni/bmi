<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sports-day tournaments: teams picked from the roster, a round-robin group
 * played once per division, and a final between each division's top two.
 *
 * Group pairings are shared by both divisions — the men's and women's games of
 * match 3 are the same two teams — so a fixture holds the pairing and a score
 * row holds one division's result against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Set once at creation and never re-derived, so a rename can't
            // break a link that has already been shared.
            $table->string('slug')->unique();
            $table->dateTime('starts_at')->nullable();
            $table->string('location')->nullable();
            // [{time, activity, detail, kind}] — the running order, display only.
            $table->json('programme')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tournament_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Also the last standings tiebreak, after points, difference and scored.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'name']);
        });

        Schema::create('tournament_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('division', 10);
            $table->boolean('is_captain')->default(false);
            $table->boolean('is_out')->default(false);
            $table->timestamps();

            // One team and one division per person per tournament.
            $table->unique(['tournament_id', 'user_id']);
        });

        Schema::create('tournament_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('home_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tournament_id', 'number']);
        });

        Schema::create('tournament_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_fixture_id')->constrained()->cascadeOnDelete();
            $table->string('division', 10);
            $table->unsignedSmallInteger('home_score');
            $table->unsignedSmallInteger('away_score');
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['tournament_fixture_id', 'division']);
        });

        Schema::create('tournament_finals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('division', 10);
            // Locked in when the final is first saved, so correcting a group
            // score afterwards can't silently swap who played in it.
            $table->foreignId('home_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('home_score');
            $table->unsignedSmallInteger('away_score');
            // Only when the score is level; null otherwise.
            $table->unsignedSmallInteger('home_penalties')->nullable();
            $table->unsignedSmallInteger('away_penalties')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['tournament_id', 'division']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_finals');
        Schema::dropIfExists('tournament_scores');
        Schema::dropIfExists('tournament_fixtures');
        Schema::dropIfExists('tournament_players');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournaments');
    }
};
