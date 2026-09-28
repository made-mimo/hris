<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec E5: grants access to a restricted-by-default document category or a specific document, by role or by named employee — exactly one of (category, document) and exactly one of (role, employee) per row, by convention. */
class CompanyDocumentFileAccessGrant extends Model
{
    protected $table = 'document_access_grants';

    protected $fillable = ['document_category_id', 'document_id', 'role_id', 'employee_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyRegistrationDocument::class, 'document_id');
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
