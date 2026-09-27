<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "a configurable Work Week pattern (each weekday marked full/half/non-working)." weekday: 0=Sunday..6=Saturday (Carbon's own numbering, so no translation layer is needed anywhere this is read against a Carbon date). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_week_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->string('day_type')->default('full')->comment('full | half | non_working');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_week_days');
    }
};
