<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2: "an employee's current termination status should be a derived fact ... while historical termination records are retained." Employee::isTerminated() derives from whether any row exists here — this project doesn't model a "rehire" event, so a simple existence check is the honest scope for now (see PLAN.md). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_terminations');
    }
};
