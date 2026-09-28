<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec F4: Helpdesk/Support Ticketing, extended with a Grievance/
 * Whistleblower category "implemented deliberately as a specialized
 * category on top of the existing Helpdesk model rather than a separate
 * module." A confidential category's visibility is restricted to a small,
 * Admin-designated handler list — not automatically every HR Admin/Officer
 * — via `helpdesk_category_handlers`, the same dual-nullable role-or-
 * employee grant pattern already used for E4/E5 restricted-file access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();
        });

        Schema::create('helpdesk_category_handlers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description');
            $table->foreignId('helpdesk_category_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open')->comment('open|in_progress|resolved|closed');
            $table->foreignId('raised_by_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('helpdesk_category_handlers');
        Schema::dropIfExists('helpdesk_categories');
    }
};
