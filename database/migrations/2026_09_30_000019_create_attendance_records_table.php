<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec C3: "Attendance records — punch-in and punch-out timestamps captured
 * both in UTC and the client's local time plus an explicit client-declared
 * timezone (no IP-address or GPS geolocation capture...)." Reuses the same
 * client-declared-timezone mechanism already built for Home's greeting line
 * (User::displayTimezone(), fed by the browser's own Intl API via
 * TimezoneController) rather than inventing a second capture path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('punch_in_at_utc');
            $table->timestamp('punch_in_at_local');
            $table->string('punch_in_timezone');
            $table->timestamp('punch_out_at_utc')->nullable();
            $table->timestamp('punch_out_at_local')->nullable();
            $table->string('punch_out_timezone')->nullable();
            $table->boolean('is_proxy_punch')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
