<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C1: "Two entry paths: self-service Apply (always requires approval) vs. admin/supervisor Assign (books leave directly, bypassing approval, optionally bypassing the balance check)." Flags which path created this request — purely informational (both paths write the same columns), but keeps the two flows distinguishable in reports/audit review. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('is_assigned')->default(false)->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('is_assigned');
        });
    }
};
