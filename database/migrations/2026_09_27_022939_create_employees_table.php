<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_id')->unique()->comment('Auto-generated, permanent, never reassigned — see spec Section B2');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('initials', 4);
            $table->string('job_title');
            $table->string('department');
            $table->string('location')->nullable();
            $table->date('hire_date');
            $table->boolean('clocked_in')->default(false);
            $table->timestamp('clocked_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
