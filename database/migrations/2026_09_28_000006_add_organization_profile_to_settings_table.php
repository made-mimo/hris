<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B1: "Organization profile (single record: name, tax ID,
 * registration number, address, contact)." `company_name` already exists
 * on this singleton `Setting` row from the Branding card (spec A5) — the
 * rest of the profile joins it here rather than a separate single-row table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'registration_number', 'address', 'contact_email', 'contact_phone']);
        });
    }
};
