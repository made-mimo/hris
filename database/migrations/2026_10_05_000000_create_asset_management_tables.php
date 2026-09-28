<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec E2 Asset Management. `custom_field_definitions`/`custom_field_values`
 * and `assignment_histories` are built generic/polymorphic here rather than
 * Asset-only, since spec explicitly asks for the identical mechanism on
 * Vehicle Fleet Management (E3) — "matching Vehicle...for consistency
 * between the two registers" / "the same pattern used for Assets" — one
 * shared implementation both registers attach to, not two near-identical
 * table pairs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('status')->default('available')
                ->comment('available | assigned | in_repair | retired');
            $table->foreignId('current_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->text('description');
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('contract_number')->nullable();
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Shared by Asset (E2) and Vehicle (E3).
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['asset', 'vehicle']);
            $table->string('label');
            $table->string('field_type')->default('text');
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->string('valuable_type');
            $table->unsignedBigInteger('valuable_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'valuable_type', 'valuable_id'], 'custom_field_value_unique');
            $table->index(['valuable_type', 'valuable_id']);
        });

        // Shared by Asset (E2) and Vehicle (E3) — a fully gapless log of every
        // employee/department that has held the item and when.
        Schema::create('assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('sub_unit_id')->nullable()->constrained('sub_units')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['assignable_type', 'assignable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_histories');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('asset_warranties');
        Schema::dropIfExists('asset_maintenance_logs');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
