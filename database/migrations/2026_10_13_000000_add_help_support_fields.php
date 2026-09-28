<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec F5: "a thin, swappable integration layer...per-screen contextual help
 * via a label/tag mapped to help-center search." `help_tag` is nullable —
 * a screen with no tag simply falls back to the provider's default URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->string('help_tag')->nullable()->after('module_key');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('help_provider_base_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('help_tag');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('help_provider_base_url');
        });
    }
};
