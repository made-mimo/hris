<?php

namespace App\Http\Controllers;

use App\Models\CompanyRegistrationDocumentVersion;
use App\Services\CompanyDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Spec E5: "restricted by default...checked server-side on every file request...every upload, view, download, and renewal action...is written to the audit log." */
class CompanyDocumentFileController extends Controller
{
    public function __invoke(CompanyRegistrationDocumentVersion $version, CompanyDocumentService $documents): BinaryFileResponse
    {
        abort_unless($documents->canAccessFile($version->document, auth()->user()), 403);

        $media = $version->getFirstMedia('file');
        abort_unless($media, 404);

        $documents->log($version->document, auth()->user(), 'downloaded');

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type]);
    }
}
