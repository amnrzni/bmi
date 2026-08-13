<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments are permanent org identity and a collection bucket for the PIC.
 * They are explicitly NOT a competitive unit — that's the team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // No DB-level FK: departments.pic_user_id and users.department_id
            // reference each other, and SQLite can't add a constraint after the fact.
            $table->unsignedBigInteger('pic_user_id')->nullable()->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
