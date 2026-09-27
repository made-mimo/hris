<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screen/page permissions (spec Section 3.2, tier 1 of 3): "which menu items
 * and pages a role can see." One row per route this app actually has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // matches a route name, e.g. "leave.apply"
            $table->string('label');
            $table->string('nav_group')->nullable(); // Workspace / My Team / Company / Admin
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
