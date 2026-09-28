<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec D1's offer-letter e-signature: the signer is a Candidate, not yet a
 * system User at that point in the pipeline. signer_id stays the identity
 * for every internal-employee signature (reviews, policy acknowledgements,
 * disciplinary outcomes); signer_name/signer_email capture an external
 * signer's identity when signer_id is null. Raw ALTER (not ->change(),
 * which needs doctrine/dbal) to drop/restore the FK around the nullability
 * change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_events', function (Blueprint $table) {
            $table->dropForeign(['signer_id']);
        });

        DB::statement('ALTER TABLE signature_events MODIFY signer_id BIGINT UNSIGNED NULL');

        Schema::table('signature_events', function (Blueprint $table) {
            $table->foreign('signer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('signer_name')->nullable()->after('signer_id');
            $table->string('signer_email')->nullable()->after('signer_name');
        });
    }

    public function down(): void
    {
        Schema::table('signature_events', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'signer_email']);
            $table->dropForeign(['signer_id']);
        });

        DB::statement('ALTER TABLE signature_events MODIFY signer_id BIGINT UNSIGNED NOT NULL');

        Schema::table('signature_events', function (Blueprint $table) {
            $table->foreign('signer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
