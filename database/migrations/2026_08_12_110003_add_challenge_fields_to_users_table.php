<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One users table carries auth identity, org identity and the challenge overlay.
 * No separate staff table — HANDOFF.md §1: throwaway, don't abstract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('staff');
            // An admin may run the challenge without competing in it.
            $table->boolean('is_participant')->default(true);

            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            // Set once by admin. Integer cm. Null blocks BMI but not weight tracking.
            $table->unsignedSmallInteger('height_cm')->nullable();

            $table->date('joined_at')->nullable();
            // Leavers keep every logged week; they just stop generating "missing" flags.
            $table->date('left_at')->nullable();

            // Null = hasn't passed the consent gate yet.
            $table->timestamp('consented_at')->nullable();
            // Withdrawal: name/email scrubbed, weigh-in rows kept for the team aggregate.
            $table->timestamp('anonymised_at')->nullable();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn([
                'role', 'is_participant', 'height_cm', 'joined_at',
                'left_at', 'consented_at', 'anonymised_at', 'deleted_at',
            ]);
        });
    }
};
