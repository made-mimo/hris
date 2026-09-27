<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "Holidays (with recurring-annual support and full/half-day length)." A recurring holiday stores just month+day of its original `date` and is matched by month/day in any year — see HolidayCalendarService. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->boolean('is_recurring_annual')->default(true);
            $table->string('length')->default('full')->comment('full | half');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
