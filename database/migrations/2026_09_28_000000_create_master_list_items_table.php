<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B1's simple, flat master lists — Job Categories, Employment
 * Statuses, and the qualification master lists (Education, Skills,
 * Languages, Licenses, Memberships, Nationalities), plus Countries. One
 * generic, `type`-discriminated table rather than nine near-identical
 * physical tables — each `type` behaves exactly like its own table via
 * `MasterListItem::ofType()`, with none of the boilerplate. Job Titles,
 * Sub-units, Locations, Work Shifts, and Pay Grades are NOT here — each has
 * shape or behavior (an attached document, hierarchy, an address, a
 * currency-band sub-editor) a flat name list can't express, so each gets
 * its own dedicated table/model instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_list_items');
    }
};
