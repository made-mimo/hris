<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CompanyDocumentFileAccessGrant;
use App\Models\CompanyRegistrationDocument;
use App\Models\CompanyRegistrationDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec E5: "restricted by default...documents...are not resolvable by
 * direct URL to anyone outside an explicit File Access Grant list" — every
 * document is gated the same way, unlike E4 where only categories marked
 * restricted are gated. Every upload/view/download/renewal is audit-logged
 * given the sensitivity of this document class.
 */
class CompanyDocumentService
{
    public function __construct(private RenewalReminderEngine $renewals) {}

    public function addVersion(CompanyRegistrationDocument $document, ?Carbon $issueDate, ?Carbon $expiryDate, UploadedFile $file, ?string $notes, User $actor): CompanyRegistrationDocumentVersion
    {
        if ($document->is_renewable) {
            if (! $issueDate || ! $expiryDate) {
                throw ValidationException::withMessages(['expiryDate' => 'Issue and expiry dates are required for a renewable document.']);
            }
            if ($expiryDate->lte($issueDate)) {
                throw ValidationException::withMessages(['expiryDate' => 'The expiry date must be after the issue date.']);
            }
        }

        return DB::transaction(function () use ($document, $issueDate, $expiryDate, $file, $notes, $actor) {
            // Spec E5: "renewing...resets the cycle" — retire the prior
            // version's reminder cycle before the new one registers fresh,
            // same pattern as Vehicle Renewals (E3).
            $prior = $document->currentVersion();
            if ($prior?->renewable && $prior->renewable->status !== 'retired') {
                $this->renewals->retire($prior->renewable);
            }

            $version = CompanyRegistrationDocumentVersion::create([
                'document_id' => $document->id,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'notes' => $notes,
            ]);

            $version->addMedia($file)->toMediaCollection('file');

            if ($document->is_renewable) {
                $this->renewals->register($version, 'company_registration_document', $expiryDate);
            }

            $this->log($document, $actor, $prior ? 'renewed' : 'uploaded');

            return $version;
        });
    }

    public function canAccessFile(CompanyRegistrationDocument $document, User $user): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        $employee = $user->employee;

        return CompanyDocumentFileAccessGrant::where(function ($q) use ($document) {
            $q->where('document_id', $document->id)
                ->orWhere('document_category_id', $document->document_category_id);
        })
            ->where(function ($q) use ($user, $employee) {
                $q->where('role_id', $user->role_id);
                if ($employee) {
                    $q->orWhere('employee_id', $employee->id);
                }
            })
            ->exists();
    }

    public function log(CompanyRegistrationDocument $document, User $actor, string $action): void
    {
        AuditLog::create([
            'auditable_type' => CompanyRegistrationDocument::class,
            'auditable_id' => $document->id,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_label' => $actor->name,
            'changes' => null,
        ]);
    }

    public function grantAccess(?int $categoryId, ?int $documentId, ?int $roleId, ?int $employeeId): CompanyDocumentFileAccessGrant
    {
        return CompanyDocumentFileAccessGrant::firstOrCreate([
            'document_category_id' => $categoryId,
            'document_id' => $documentId,
            'role_id' => $roleId,
            'employee_id' => $employeeId,
        ]);
    }
}
