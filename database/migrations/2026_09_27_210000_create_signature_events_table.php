<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section A8's E-Signature & Digital Consent Service: "a Signature
 * Event record per (document/record, signer, purpose) — the exact content/
 * version signed (a hash...), signer identity, timestamp, IP address and
 * user-agent at signing time, the signature method used, and — where the
 * method is a drawn signature — the captured image." Built ahead of its
 * consumers (Policy Documents E4, offer letters D1, review sign-off D2,
 * expense-claim attestations E1 — none exist in this prototype yet) as a
 * shared service every future module calls into, per the spec's own reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_events', function (Blueprint $table) {
            $table->id();
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');
            $table->foreignId('signer_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->string('content_hash');
            $table->string('method'); // click_to_sign | drawn
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at');

            $table->index(['signable_type', 'signable_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_events');
    }
};
