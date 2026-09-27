<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section 3.5: file attachments now go through spatie/laravel-medialibrary
 * (see the `media` table) instead of a raw path column + Storage::disk('public')
 * call. Both columns are confirmed empty in the seeded demo data at the time of
 * this migration, so there's nothing to backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });
    }
};
