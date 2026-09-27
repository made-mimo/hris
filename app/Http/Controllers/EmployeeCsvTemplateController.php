<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Spec B2: "CSV bulk import with a downloadable sample template." */
class EmployeeCsvTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['first_name', 'last_name', 'hire_date', 'job_title', 'sub_unit', 'location']);
            fputcsv($out, ['Ada', 'Lovelace', '2026-01-15', 'Software Engineer', 'Engineering', 'Lagos']);
            fclose($out);
        }, 'employee-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
