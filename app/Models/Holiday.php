<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'is_recurring_annual', 'length'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring_annual' => 'boolean',
        ];
    }

    /** True if $date falls on this holiday — exact match for a one-off, month+day match for a recurring-annual one. */
    public function occursOn(Carbon $date): bool
    {
        if ($this->is_recurring_annual) {
            return $this->date->month === $date->month && $this->date->day === $date->day;
        }

        return $this->date->isSameDay($date);
    }
}
