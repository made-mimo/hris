<?php

namespace App\Services;

use App\Models\SignatureEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec Section A8's E-Signature & Digital Consent Service: a reusable
 * consent/signature mechanism every future module calls into (Policy
 * Documents E4, offer letters D1, review sign-off D2, expense-claim
 * attestations E1 — none exist in this prototype yet) rather than each
 * building its own. Two methods per spec: click-to-sign (typed consent +
 * checkbox, sufficient for internal HR acknowledgements) and drawn
 * signature (canvas pad, for higher-formality documents like offer letters).
 *
 * Tamper-evidence: the caller passes the exact content that was presented
 * to the signer (e.g. a policy document's body + its version number); this
 * service hashes it and stores only the hash. A later edit to the source
 * document changes its content going forward, but never retroactively
 * alters what a prior SignatureEvent's hash proves was signed.
 */
class SignatureService
{
    /**
     * @param  Model  $signable  The document/record being signed.
     * @param  User|null  $signer  Null for an external signer who has no
     *                             system account yet (spec D1: a Candidate
     *                             signing their offer letter) — pass
     *                             $externalSignerName/$externalSignerEmail
     *                             in that case instead.
     * @param  string  $content  The exact content signed — e.g. a document's
     *                           body plus its version identifier — hashed,
     *                           never stored in plain form.
     * @param  string  $method  'click_to_sign' | 'drawn'
     * @param  string|null  $drawnImageBase64  Required when $method is 'drawn' — a data: URL from a canvas signature pad.
     */
    public function sign(
        Model $signable,
        ?User $signer,
        string $purpose,
        string $content,
        string $method,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $drawnImageBase64 = null,
        ?string $externalSignerName = null,
        ?string $externalSignerEmail = null,
    ): SignatureEvent {
        $event = SignatureEvent::create([
            'signable_type' => get_class($signable),
            'signable_id' => $signable->getKey(),
            'signer_id' => $signer?->id,
            'signer_name' => $signer ? null : $externalSignerName,
            'signer_email' => $signer ? null : $externalSignerEmail,
            'purpose' => $purpose,
            'content_hash' => hash('sha256', $content),
            'method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'signed_at' => now(),
        ]);

        if ($method === 'drawn' && $drawnImageBase64) {
            $event->addMediaFromBase64($drawnImageBase64)->toMediaCollection('signature_image');
        }

        return $event;
    }

    /**
     * True only if the signer has a signature on record for this exact
     * content — spec: "prior signatures remain valid evidence only for that
     * prior version." A signature against an older revision doesn't count.
     */
    public function hasValidSignature(Model $signable, User $signer, string $purpose, string $currentContent): bool
    {
        return SignatureEvent::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->getKey())
            ->where('signer_id', $signer->id)
            ->where('purpose', $purpose)
            ->where('content_hash', hash('sha256', $currentContent))
            ->exists();
    }

    /** Every signature on record for this document/purpose, latest first — the verification/audit view's data source. */
    public function signaturesFor(Model $signable, string $purpose): Collection
    {
        return SignatureEvent::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->getKey())
            ->where('purpose', $purpose)
            ->with('signer')
            ->latest('signed_at')
            ->get();
    }
}
