<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec C1: "discrete allocation batches per employee/type with an
 * effective date range... allowing multiple batches to be active at once."
 * Adds the real effective-date-range fields (backfilled from the existing
 * `year` column as Jan 1 – Dec 31 of that year, so no existing batch loses
 * meaning) and `expires_at`, used by carried-over batches' Q1 expiry.
 * `year` itself stays — cheap to keep, several places already query by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->date('effective_start_date')->nullable()->after('year');
            $table->date('effective_end_date')->nullable()->after('effective_start_date');
            $table->date('expires_at')->nullable()->after('effective_end_date');
        });

        DB::table('leave_entitlements')->whereNull('effective_start_date')->get(['id', 'year'])->each(function ($row) {
            DB::table('leave_entitlements')->where('id', $row->id)->update([
                'effective_start_date' => "{$row->year}-01-01",
                'effective_end_date' => "{$row->year}-12-31",
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->dropColumn(['effective_start_date', 'effective_end_date', 'expires_at']);
        });
    }
};
