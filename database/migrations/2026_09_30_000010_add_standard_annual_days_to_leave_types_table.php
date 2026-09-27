<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec C1's accrual engine computes a new-hire's prorated batch as
 * `standard_annual_entitlement × (months remaining / 12)` and grants the
 * next year's ordinary batch at the same standard amount — both need a
 * configured "standard annual days" figure per type, which didn't
 * previously exist (each employee's entitlement was seeded ad hoc). Values
 * backfilled from this project's own seeded amounts (Annual 20, Sick 10,
 * Compassionate 5, Study/Exam 5) so the engine has a sane default the
 * moment it runs against existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->decimal('standard_annual_days', 5, 1)->default(0)->after('minimum_tenure_months');
        });

        foreach (['annual' => 20, 'sick' => 10, 'compassionate' => 5, 'study-exam' => 5] as $slug => $days) {
            DB::table('leave_types')->where('slug', $slug)->update(['standard_annual_days' => $days]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('standard_annual_days');
        });
    }
};
