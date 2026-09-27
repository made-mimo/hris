<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B1: "Sub-units (the department/org-chart tree — modeled as a
 * proper hierarchical tree... so subtree queries such as 'everyone under
 * this department' are efficient)." A plain adjacency list (`parent_id`)
 * rather than the nested-set/closure-table spec suggests — at this
 * prototype's scale (a handful of departments) a recursive walk is more
 * than fast enough, and adjacency lists are far simpler to keep correct
 * under edits (add/rename/move). Revisit if a real deployment's org chart
 * ever gets deep/wide enough for that tradeoff to flip. Session notes
 * (project start) called for sub-units to be *displayed* as "Departments" —
 * this is that same concept, now a real hierarchical entity instead of a
 * plain string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sub_units')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_units');
    }
};
