<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayGradeBand extends Model
{
    protected $fillable = ['pay_grade_id', 'currency', 'min_salary', 'max_salary'];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
        ];
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }
}
