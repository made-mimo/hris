<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PolicyDocumentResource;
use App\Models\PolicyDocument;
use App\Services\PolicyService;
use Illuminate\Http\Request;

/** Spec F6: "policy acknowledgement." Mirrors ⚡policy-manager.blade.php's own browse query — every active document the caller can access, restricted categories filtered by PolicyService::canAccessFile(). */
class PolicyDocumentController extends ApiController
{
    public function index(Request $request, PolicyService $policy)
    {
        $documents = PolicyDocument::where('is_active', true)
            ->with(['category', 'versions'])
            ->get()
            ->filter(fn (PolicyDocument $doc) => $doc->currentVersion() && $policy->canAccessFile($doc->currentVersion(), $request->user()));

        if ($request->boolean('outstanding_only')) {
            $employee = $request->user()->employee;
            abort_unless($employee, 422, 'This account has no employee record.');
            $outstandingIds = $policy->outstandingFor($employee)->pluck('id');
            $documents = $documents->whereIn('id', $outstandingIds);
        }

        return $this->success(PolicyDocumentResource::collection($documents->values()));
    }

    public function acknowledge(Request $request, PolicyDocument $policyDocument, PolicyService $policy)
    {
        $version = $policyDocument->currentVersion();
        abort_unless($version, 404, 'This document has no published version.');
        abort_unless($policy->canAccessFile($version, $request->user()), 403, 'You do not have access to this document.');

        $policy->acknowledge($version, $request->user(), 'click_to_sign', $request->ip(), $request->userAgent());

        return $this->success(new PolicyDocumentResource($policyDocument->fresh(['category', 'versions'])));
    }
}
