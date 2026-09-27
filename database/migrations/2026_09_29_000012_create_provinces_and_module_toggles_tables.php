<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B1: "Countries/Provinces" as a hierarchical structure — countries live in master_list_items (type=country, flat, already built); provinces get their own table with a country FK for the one level of real hierarchy spec asks for. Also spec B1: "Module enable/disable toggles at the system level, with dependent UI ... automatically hiding when a module is disabled." */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('master_list_items')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

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
        Schema::dropIfExists('provinces');
    }
};
