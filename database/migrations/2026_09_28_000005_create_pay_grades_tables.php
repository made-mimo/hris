<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B1: "Pay Grades with per-currency min/max salary bands" and
 * "Pay grade editor with a nested currency-band sub-editor (multiple
 * currencies per grade, each with its own min/max salary)."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pay_grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_grade_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('min_salary', 14, 2);
            $table->decimal('max_salary', 14, 2);
            $table->timestamps();

            $table->unique(['pay_grade_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_grade_bands');
        Schema::dropIfExists('pay_grades');
    }
};
