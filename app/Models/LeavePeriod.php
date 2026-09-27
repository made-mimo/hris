<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePeriod extends Model
{
    protected $fillable = ['year', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public static function forYear(int $year): self
    {
        return static::firstOrCreate(
            ['year' => $year],
            ['starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31"]
        );
    }
}
