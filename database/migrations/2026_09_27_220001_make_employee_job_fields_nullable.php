<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B2: "Minimal-friction creation (only name required at
 * creation) followed by a tabbed profile editor" — job_title/department/
 * location were NOT NULL from this prototype's very first iteration
 * (pre-dating this session), which made that impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable()->change();
            $table->string('department')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable(false)->change();
            $table->string('department')->nullable(false)->change();
        });
    }
};
