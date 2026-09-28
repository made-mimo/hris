<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec E4: acknowledgements are NOT a separate table — each one IS a
 * SignatureEvent (Section A8) against a PolicyDocumentVersion with purpose
 * 'policy_acknowledgement', giving every acknowledgement a tamper-evident,
 * exact-version-hashed evidence record for free. "Outstanding
 * acknowledgements" (including new-hire enrollment) is a live computation —
 * every active employee minus those with a valid signature against the
 * document's current version — never a materialized/backfilled row, so
 * there is nothing to retroactively enroll when a new hire activates or a
 * new version publishes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_restricted')->default(false);
            $table->timestamps();
        });

        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('policy_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Immutable: never updated after creation other than by inserting a
        // new row. The file itself lives in media-library storage (private
        // disk — see App\Models\PolicyDocumentVersion), not inline here.
        Schema::create('policy_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->text('change_notes')->nullable();
            $table->date('effective_date');
            $table->timestamps();
        });

        // Spec E4: "a restricted-category document...access checked by
        // role or by named employee." Nullable role_id/employee_id are
        // mutually exclusive by convention (enforced in PolicyService), not
        // a DB constraint — same precedent as Vehicle's dual assignment.
        Schema::create('policy_file_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_file_access_grants');
        Schema::dropIfExists('policy_document_versions');
        Schema::dropIfExists('policy_documents');
        Schema::dropIfExists('policy_categories');
    }
};
