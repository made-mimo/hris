<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    use Auditable;

    protected $fillable = ['name', 'hours_per_day', 'start_time', 'end_time', 'is_active'];

    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
