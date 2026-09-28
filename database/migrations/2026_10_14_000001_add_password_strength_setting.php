<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A1's password policy mentions "length and character-class
 * rules" plus entropy alongside them; only the former existed. Adds a real
 * strength check via zxcvbn (bjeavons/zxcvbn-php) — a 0-4 score, not just
 * character-class boxes a "P@ssw0rd1!" can tick while still being guessable
 * in seconds. Null (default) means disabled — an existing install's policy
 * doesn't change until an Admin opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('password_min_zxcvbn_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('password_min_zxcvbn_score');
        });
    }
};
