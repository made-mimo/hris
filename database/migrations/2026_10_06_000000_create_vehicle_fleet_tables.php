<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec E3: vehicles carry current assignment to EITHER an employee OR a
 * sub-unit (department) — mutually exclusive, enforced in App\Services\
 * VehicleService, not at the DB level (this app has no CHECK-constraint
 * precedent and SQLite/MariaDB parity is safer without one). Assignment
 * history and custom fields reuse E2's shared AssignmentHistory/
 * CustomFieldValue infrastructure via the HasAssignmentHistory/
 * HasCustomFields traits — no new tables needed for either. Vehicle
 * Renewals deliberately has no unique constraint: "re-renewing creates a
 * new row rather than overwriting the old one, preserving history."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vin')->unique();
            $table->string('engine_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('color')->nullable();
            $table->string('status')->default('active')->comment('active|in_service|retired');
            $table->foreignId('current_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('current_sub_unit_id')->nullable()->constrained('sub_units')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('provider')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->text('notes')->nullable();
            $table->unsignedInteger('mileage_interval')->nullable()->comment('e.g. service due every N miles/km, alongside the calendar-based renewal');
            $table->unsignedInteger('due_at_mileage')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('odometer_reading');
            $table->decimal('fuel_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Spec E3: "assigning driving license... an explicit, logged
        // override" — generic enough to live on the shared history table
        // (Asset assignments simply never populate these two columns).
        Schema::table('assignment_histories', function (Blueprint $table) {
            $table->text('override_reason')->nullable()->after('ended_at');
            $table->foreignId('overridden_by_id')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by_id');
            $table->dropColumn('override_reason');
        });

        Schema::dropIfExists('vehicle_fuel_logs');
        Schema::dropIfExists('vehicle_renewals');
        Schema::dropIfExists('vehicles');
    }
};
