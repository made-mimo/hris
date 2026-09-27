<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec A6: GDPR purge — anonymizes an employee's PII while deliberately preserving the Employee ID's "used" status (never reassigned, spec B2). `is_gdpr_purged` + `gdpr_purged_at` are the durable "this ID is retired for good" markers that survive the anonymization itself. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_gdpr_purged')->default(false);
            $table->timestamp('gdpr_purged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['is_gdpr_purged', 'gdpr_purged_at']);
        });
    }
};
