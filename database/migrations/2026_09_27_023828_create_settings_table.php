<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Systems Intelligenz');
            $table->string('logo_path')->nullable();
            $table->boolean('two_factor_enabled')->default(false)
                ->comment('Admin-configurable, project-wide — spec A1/A5. Defaults OFF for local dev per explicit request.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
