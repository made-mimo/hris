<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec review: "Home Address should have a separate text box for City/State, Country (drop down default to Nigeria)." */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('home_city_state')->nullable()->after('home_address');
            $table->foreignId('home_country_id')->nullable()->after('home_city_state')
                ->constrained('master_list_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_country_id');
            $table->dropColumn('home_city_state');
        });
    }
};
