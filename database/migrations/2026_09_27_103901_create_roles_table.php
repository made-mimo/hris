<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A2: "every role — system or custom — is just a row in the
 * Role table plus a set of rows in the Role Permission Matrix; there is no
 * hard-coded 'if role === HR_OFFICER' logic anywhere in the guard layer."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // The four system roles (Admin, HR Admin, HR Officer, ESS) plus
            // the situational Supervisor role are seeded with this flag set
            // and can never be edited or deleted (spec: "keep a known-good
            // baseline always available"). A custom role has it false.
            $table->boolean('is_system_role')->default(false);
            // Supervisor is "computed automatically from the reporting-line
            // graph, not separately assigned" (spec A2) — flags the one role
            // a user never holds as their base role, only ever layered on
            // top of it when they actually have subordinates.
            $table->boolean('is_situational')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
