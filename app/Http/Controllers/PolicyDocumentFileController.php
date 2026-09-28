<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocumentVersion;
use App\Services\PolicyService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Spec E4: "documents in a restricted category are not resolvable by
 * direct URL to anyone outside an explicit File Access Grant list...access
 * is checked server-side on every file request, not only at the listing
 * layer, and every access to a restricted file is written to the audit
 * log." Every version's file lives on the private disk (see
 * PolicyDocumentVersion) and is served exclusively through this route.
 */
class PolicyDocumentFileController extends Controller
{
    public function __invoke(PolicyDocumentVersion $version, PolicyService $policy): BinaryFileResponse
    {
        abort_unless($policy->canAccessFile($version, auth()->user()), 403);

        $media = $version->getFirstMedia('file');
        abort_unless($media, 404);

        if ($version->document->category->is_restricted) {
            $policy->logRestrictedAccess($version, auth()->user());
        }

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
    }
}
