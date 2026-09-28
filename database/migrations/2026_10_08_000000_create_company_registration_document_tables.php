<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec E5: distinct from Policy Document Management (E4) — company-level
 * legal/compliance instruments (insurance certificates, licenses, permits),
 * restricted BY DEFAULT rather than opt-in, with Admin/HR Admin as the only
 * implicit bypass. Renewal reminders reuse the SAME shared Renewal &
 * Compliance Reminder Engine (Section 3.2) as E2/E3, registered under one
 * 'company_registration_document' type — a deliberate scoping-down of
 * spec's "reminder policy per document/category" to one org-wide,
 * Admin-editable tier set, consistent with how the engine is already
 * shared across Asset/Vehicle rather than per-instance-configurable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('company_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('document_category_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_renewable')->default(false);
            $table->timestamps();
        });

        // Immutable: never updated after creation other than by inserting a
        // new row — "every renewal is a new version...never an overwrite."
        // Column named `document_id` (short) rather than the more verbose
        // `company_registration_document_id` — that longer name pushed the
        // auto-generated FK constraint name past MySQL's 64-char identifier
        // limit (the same bug class fixed in D2's performance_reviewers and
        // feedback_responses tables).
        Schema::create('company_registration_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('company_registration_documents')->cascadeOnDelete();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Spec E5: "restricted by default...a specific document OR category
        // can be granted broader visibility" — document_id/category_id are
        // mutually exclusive by convention (App\Services\
        // CompanyDocumentService), same precedent as role_id/employee_id.
        // Table named `document_access_grants` (short) for the same reason
        // as `document_id` above — the longer `company_document_file_
        // access_grants` name left its FK constraint names at exactly
        // MySQL's 64-char limit, too fragile a margin.
        Schema::create('document_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('company_registration_documents')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_grants');
        Schema::dropIfExists('company_registration_document_versions');
        Schema::dropIfExists('company_registration_documents');
        Schema::dropIfExists('document_categories');
    }
};
