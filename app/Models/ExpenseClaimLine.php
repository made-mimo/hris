<?php

namespace App\Models;

use Database\Factories\ExpenseClaimLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaimLine extends Model
{
    /** @use HasFactory<ExpenseClaimLineFactory> */
    use HasFactory;

    protected $fillable = [
        'expense_claim_id',
        'expense_type_id',
        'date',
        'note',
        'amount',
        'flagged',
        'justification',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'flagged' => 'boolean',
        ];
    }

    public function expenseClaim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class);
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }
}
