<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_screen_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->timestamps();
            $table->unique(['role_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_screen_permissions');
    }
};
