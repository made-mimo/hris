<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B2's Employee ID Auto-Generation: "Format template, Admin-
 * configurable... an ordered sequence of literal text and tokens — `{YY}`,
 * `{MM}`, and `{SEQ:n}`... with SI's current format, `SIL{YY}{MM}{SEQ}`,
 * seeded as the default template." The counter's scope is itself
 * Admin-configurable (Global/Per-Year/Per-Month) even though the shipped
 * default is the single continuous count spec confirms as SI's actual
 * policy — `employee_id_sequences` supports all three without a schema
 * change if that policy ever changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('employee_id_format')->default('SIL{YY}{MM}{SEQ:3}');
            $table->string('employee_id_sequence_scope')->default('global'); // global | per_year | per_month
        });

        Schema::create('employee_id_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->unique(); // 'global', '2026', or '2026-09'
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['employee_id_format', 'employee_id_sequence_scope']);
        });

        Schema::dropIfExists('employee_id_sequences');
    }
};
