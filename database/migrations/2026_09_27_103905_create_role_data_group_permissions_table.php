<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Role Permission Matrix (spec A2) — "the actual runtime source of truth
 * read by the guard/interceptor layer." One row per (role, data group): a
 * scope (who this grant covers) and a level (what they can do to it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_data_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_group_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['none', 'self', 'self_subordinates', 'all'])->default('none');
            $table->enum('level', ['none', 'view', 'view_edit', 'view_edit_delete'])->default('none');
            $table->timestamps();
            $table->unique(['role_id', 'data_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_group_permissions');
    }
};
