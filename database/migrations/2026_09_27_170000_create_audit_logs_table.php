<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section 3.2's Audit Logging platform service: "all entity mutations
 * should be captured automatically... not by manual instrumentation in each
 * service" — one consolidated mechanism (the spec explicitly calls out the
 * reference system's two overlapping audit mechanisms as a mistake not to
 * repeat), capturing entity, action, actor, timestamp, and field-level
 * before/after values. See App\Traits\Auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action'); // created | updated | deleted
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Denormalized so the log still reads sensibly after the actor is deleted.
            $table->string('actor_label')->nullable();
            // For 'updated': {field: [old, new]}. For 'created'/'deleted': the full attribute set.
            $table->json('changes')->nullable();
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
