<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec C3: three independently toggleable, Admin-configurable permissions — all off by default, so only an Admin has edit/delete/proxy rights until explicitly relaxed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('attendance_allow_backdate')->default(false);
            $table->boolean('attendance_allow_self_edit')->default(false);
            $table->boolean('attendance_allow_supervisor_proxy')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['attendance_allow_backdate', 'attendance_allow_self_edit', 'attendance_allow_supervisor_proxy']);
        });
    }
};
