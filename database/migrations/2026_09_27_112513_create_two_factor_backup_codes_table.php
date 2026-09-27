<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec A1: "10 single-use backup codes are generated at enrollment... each
 * is consumed on use." Stored hashed, never in plain text once shown once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_backup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_backup_codes');
    }
};
