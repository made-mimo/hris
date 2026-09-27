<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    use Auditable;

    /** See Employee::$auditExcept — same reasoning: encrypted fields must not leak decrypted plaintext into audit_logs.changes. */
    protected array $auditExcept = ['amount', 'bank_account_number'];

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id', 'pay_grade_id', 'name', 'amount', 'currency', 'pay_period',
        'bank_name', 'bank_account_number', 'bank_account_name', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }
}
