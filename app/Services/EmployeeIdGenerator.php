<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeIdSequence;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Spec Section B2's Employee ID Auto-Generation: an Admin-configurable
 * format template (`{YY}`, `{MM}`, `{SEQ:n}` tokens over literal text,
 * default `SIL{YY}{MM}{SEQ:3}`) plus a serial that — per SI's confirmed
 * policy — is "one continuous count" that "never resets by month or year,"
 * regardless of which date tokens also appear in the format string. The
 * counter's scope (global/per_year/per_month) is itself Admin-configurable
 * only as a forward-looking option should that policy ever change.
 */
class EmployeeIdGenerator
{
    /** Generates and reserves the next ID for a hire on this date — the ID is spent the moment this returns, even if the caller never persists it. */
    public function generate(Carbon $hireDate): string
    {
        $settings = Setting::current();
        $scopeKey = $this->scopeKeyFor($settings->employee_id_sequence_scope, $hireDate);

        $next = DB::transaction(function () use ($scopeKey) {
            $sequence = EmployeeIdSequence::where('scope_key', $scopeKey)->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = EmployeeIdSequence::create(['scope_key' => $scopeKey, 'next_value' => 1]);
            }

            $value = $sequence->next_value;
            $sequence->increment('next_value');

            return $value;
        });

        return $this->render($settings->employee_id_format, $hireDate, $next);
    }

    /** Admin/HR Admin manual override (spec B2) must still pass the same uniqueness check as an auto-generated ID. */
    public function isAvailable(string $candidateId, ?int $ignoringEmployeeId = null): bool
    {
        return ! Employee::where('employee_id', $candidateId)
            ->when($ignoringEmployeeId, fn ($q) => $q->where('id', '!=', $ignoringEmployeeId))
            ->exists();
    }

    /** A live preview of what the next ID would look like, without spending the sequence — for the format-editor UI. */
    public function preview(string $format, ?Carbon $hireDate = null, int $exampleSeq = 1): string
    {
        return $this->render($format, $hireDate ?? now(), $exampleSeq);
    }

    private function scopeKeyFor(string $scope, Carbon $hireDate): string
    {
        return match ($scope) {
            'per_year' => $hireDate->format('Y'),
            'per_month' => $hireDate->format('Y-m'),
            default => 'global',
        };
    }

    private function render(string $format, Carbon $hireDate, int $seq): string
    {
        return preg_replace_callback('/\{(YY|MM|SEQ)(?::(\d+))?\}/', function ($matches) use ($hireDate, $seq) {
            return match ($matches[1]) {
                'YY' => $hireDate->format('y'),
                'MM' => $hireDate->format('m'),
                'SEQ' => str_pad((string) $seq, isset($matches[2]) ? (int) $matches[2] : 3, '0', STR_PAD_LEFT),
            };
        }, $format);
    }
}
