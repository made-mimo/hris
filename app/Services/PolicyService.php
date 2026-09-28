<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\PolicyFileAccessGrant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Spec E4: acknowledgement, compliance reporting, and restricted-file
 * access are all live computations against SignatureEvent (Section A8) and
 * PolicyFileAccessGrant — see PolicyDocumentVersion's own doc comment for
 * why there is no separate "enrollment" record to backfill.
 */
class PolicyService
{
    public function __construct(private SignatureService $signatures) {}

    public function addVersion(PolicyDocument $document, string $versionLabel, ?string $changeNotes, Carbon $effectiveDate, UploadedFile $file): PolicyDocumentVersion
    {
        return DB::transaction(function () use ($document, $versionLabel, $changeNotes, $effectiveDate, $file) {
            $version = PolicyDocumentVersion::create([
                'policy_document_id' => $document->id,
                'version_label' => $versionLabel,
                'change_notes' => $changeNotes,
                'effective_date' => $effectiveDate,
            ]);

            $version->addMedia($file)->toMediaCollection('file');

            return $version;
        });
    }

    /** Spec E4: "click-to-sign/typed-consent method by default" — the caller passes 'drawn' only when the category is configured for it. */
    public function acknowledge(PolicyDocumentVersion $version, User $user, string $method, ?string $ipAddress, ?string $userAgent, ?string $drawnImageBase64 = null): void
    {
        abort_if($version->isAcknowledgedBy($user, $this->signatures), 422, 'This version has already been acknowledged.');

        $this->signatures->sign(
            $version,
            $user,
            PolicyDocumentVersion::PURPOSE,
            $version->acknowledgementContent(),
            $method,
            $ipAddress,
            $userAgent,
            $drawnImageBase64,
        );
    }

    /** @return Collection<int, PolicyDocument> active documents whose current version this employee has not yet acknowledged. */
    public function outstandingFor(Employee $employee): Collection
    {
        if (! $employee->user) {
            return collect();
        }

        return PolicyDocument::where('is_active', true)
            ->with('category', 'versions')
            ->get()
            ->filter(fn (PolicyDocument $doc) => $doc->currentVersion()
                && $this->canAccessFile($doc->currentVersion(), $employee->user)
                && ! $doc->currentVersion()->isAcknowledgedBy($employee->user, $this->signatures));
    }

    /** Spec E4: compliance reporting "against every currently active employee...terminated and GDPR-purged employees are excluded." */
    public function complianceReport(PolicyDocumentVersion $version): array
    {
        $activeEmployees = Employee::whereDoesntHave('terminations')->where('is_gdpr_purged', false)->with('user')->get();

        $acknowledged = $activeEmployees->filter(fn (Employee $e) => $e->user && $version->isAcknowledgedBy($e->user, $this->signatures));

        return [
            'total' => $activeEmployees->count(),
            'acknowledged' => $acknowledged->count(),
            'outstanding' => $activeEmployees->count() - $acknowledged->count(),
            'outstandingEmployees' => $activeEmployees->diff($acknowledged)->values(),
        ];
    }

    public function canAccessFile(PolicyDocumentVersion $version, User $user): bool
    {
        $category = $version->document->category;

        if (! $category->is_restricted) {
            return true;
        }

        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        $employee = $user->employee;

        return PolicyFileAccessGrant::where('policy_category_id', $category->id)
            ->where(function ($q) use ($user, $employee) {
                $q->where('role_id', $user->role_id);
                if ($employee) {
                    $q->orWhere('employee_id', $employee->id);
                }
            })
            ->exists();
    }

    public function logRestrictedAccess(PolicyDocumentVersion $version, User $user): void
    {
        AuditLog::create([
            'auditable_type' => PolicyDocumentVersion::class,
            'auditable_id' => $version->id,
            'action' => 'restricted_file_accessed',
            'actor_id' => $user->id,
            'actor_label' => $user->name,
            'changes' => null,
        ]);
    }

    public function grantAccess(int $categoryId, ?int $roleId, ?int $employeeId): PolicyFileAccessGrant
    {
        return PolicyFileAccessGrant::firstOrCreate([
            'policy_category_id' => $categoryId,
            'role_id' => $roleId,
            'employee_id' => $employeeId,
        ]);
    }
}
