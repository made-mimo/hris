<?php

namespace App\Support;

/**
 * PIM/HRIS alignment item 14 — the shared SI date convention, confirmed by
 * the user: tables/lists/forms/CSV exports use DD/MM/YYYY (DATE) or
 * DD/MM/YYYY HH:mm in 24-hour time (DATE_TIME); headings, summaries, and
 * sentences keep the existing "28 Sep 2026" style (LONG). Never 12-hour/
 * AM-PM anywhere. Mirrors PIM's own App\Support\Dates constants so both
 * apps document the same rule from the same kind of place, without forcing
 * every call site in this codebase to switch to it at once — existing
 * ->format('j M Y') calls in heading/summary contexts are intentionally
 * left as literal strings rather than migrated to LONG, since this class
 * exists to fix new/changing call sites consistently, not to mass-rewrite
 * ones that were already correct.
 */
class Dates
{
    public const DATE = 'd/m/Y';

    public const DATE_TIME = 'd/m/Y H:i';

    public const LONG = 'j M Y';
}
