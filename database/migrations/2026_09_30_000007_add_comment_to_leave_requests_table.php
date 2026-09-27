<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "comments at both the request level and the individual-day level" — day-level comment already added on leave_request_days; this is the request-level one (distinct from `reason`, which is set once at submission — a comment can be added later by anyone with access, e.g. HR noting context at approval time). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('hr_comment')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('hr_comment');
        });
    }
};
