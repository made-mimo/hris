<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec F2: "'who's on leave today' (with a configurable scope: everyone vs. only employees the viewer has access to)." */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('dashboard_who_is_out_scope')->default('scoped')->comment('everyone|scoped');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_who_is_out_scope');
        });
    }
};
