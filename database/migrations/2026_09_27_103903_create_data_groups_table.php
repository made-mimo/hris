<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-group permissions (spec Section 3.2, tier 2 of 3): "CRUD grants on a
 * named logical resource (e.g., 'employee personal details,' 'buzz post'),
 * each grant optionally scoped to self." One row per logical resource this
 * app's modules actually expose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. "compensation", "leave_requests"
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_groups');
    }
};
