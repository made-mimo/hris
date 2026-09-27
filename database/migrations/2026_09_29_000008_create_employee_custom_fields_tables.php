<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2: "up to 10 organization-configurable custom fields (label, type — free text or select — and which tab they render on)." The "up to 10" cap is enforced in the manager component, not the schema. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('field_type')->default('text');
            $table->json('options')->nullable();
            $table->string('tab')->default('personal');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('employee_custom_field_definitions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_custom_field_values');
        Schema::dropIfExists('employee_custom_field_definitions');
    }
};
