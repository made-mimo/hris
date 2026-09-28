<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec E4: grants access to a restricted PolicyCategory's files, either by role or by named employee — never both on the same row. */
class PolicyFileAccessGrant extends Model
{
    protected $fillable = ['policy_category_id', 'role_id', 'employee_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PolicyCategory::class, 'policy_category_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
