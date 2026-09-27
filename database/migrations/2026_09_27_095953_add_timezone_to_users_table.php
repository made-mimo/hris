<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Client-declared IANA timezone (e.g. "Africa/Lagos", "Europe/London") —
            // read from the browser's own Intl API, never IP/GPS geolocation (spec
            // Section C3 explicitly excludes location capture). Display-only: every
            // stored instant stays anchored to config('app.timezone') (GMT+1 fixed);
            // this column only controls how that instant is *rendered* to this user.
            $table->string('timezone', 64)->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
