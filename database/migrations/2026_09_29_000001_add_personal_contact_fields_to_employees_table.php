<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec B2 Personal details + Contact details tabs — flat columns since both are small, fixed field sets (no repeating structure), unlike Emergency Contacts/Dependents which get their own tables below. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('preferred_name');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->string('marital_status')->nullable()->after('gender');
            $table->foreignId('nationality_id')->nullable()->after('marital_status')->constrained('master_list_items')->nullOnDelete();
            $table->string('government_id_type')->nullable()->after('nationality_id');
            $table->text('government_id_number')->nullable()->after('government_id_type');
            $table->string('driving_license_number')->nullable()->after('government_id_number');

            $table->text('home_address')->nullable()->after('driving_license_number');
            $table->string('phone_home')->nullable()->after('home_address');
            $table->string('phone_mobile')->nullable()->after('phone_home');
            $table->string('personal_email')->nullable()->after('phone_mobile');
            $table->string('work_email')->nullable()->after('personal_email');

            $table->foreignId('employment_status_id')->nullable()->after('work_email')->constrained('master_list_items')->nullOnDelete();
            $table->foreignId('job_category_id')->nullable()->after('employment_status_id')->constrained('master_list_items')->nullOnDelete();
            $table->date('contract_start_date')->nullable()->after('job_category_id');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nationality_id');
            $table->dropConstrainedForeignId('employment_status_id');
            $table->dropConstrainedForeignId('job_category_id');
            $table->dropColumn([
                'preferred_name', 'date_of_birth', 'gender', 'marital_status',
                'government_id_type', 'government_id_number', 'driving_license_number',
                'home_address', 'phone_home', 'phone_mobile', 'personal_email', 'work_email',
                'contract_start_date', 'contract_end_date',
            ]);
        });
    }
};
