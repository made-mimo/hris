<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "Leave Period configuration (fixed to the calendar year per SI policy) with a history of past configurations so a future change doesn't retroactively alter already-closed periods." One row per year, created by the year-end carryover job (or on demand) — a closed (past) year's row is never edited once its `starts_on`/`ends_on` have passed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_periods');
    }
};
