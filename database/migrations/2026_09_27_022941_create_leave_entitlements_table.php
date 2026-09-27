<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 5, 1);
            $table->string('batch_type')->default('standard')
                ->comment('standard | new_hire_prorated | carried_over — spec C1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }
};
