<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B1: "Module enable/disable toggles at the system level, with dependent UI ... automatically hiding when a module is disabled." */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_toggles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_toggles');
    }
};
