<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The generic workflow-state-machine engine (spec Section 3.2): one reusable
 * table of the shape "(workflow, state, role, action) → resulting state"
 * backing every approval process, so a policy change (e.g. who may approve
 * what) is a row edit here, not new code. `actor` is either a real Role slug
 * (spec's three-tier RBAC roles) or one of two situational tags resolved
 * live against the record — "owner" (the employee the record belongs to) or
 * "supervisor" (that specific employee's supervisor) — the same live
 * resolution pattern PermissionService already uses for the situational
 * Supervisor role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('workflow'); // e.g. "leave_request", "expense_claim"
            $table->string('from_state');
            $table->string('actor'); // "owner" | "supervisor" | a roles.slug
            $table->string('action'); // e.g. "approve", "reject"
            $table->string('label'); // button text, e.g. "Give final approval"
            $table->string('to_state');
            $table->timestamps();
            $table->unique(['workflow', 'from_state', 'actor', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
