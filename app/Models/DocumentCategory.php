<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec E5: "Insurance, License, Permit, Certificate, and any others HR Admin defines" — an HR-Admin-managed master list, the same pattern as PolicyCategory (E4) but a distinct table since this domain is deliberately separate. */
class DocumentCategory extends Model
{
    protected $fillable = ['name', 'description', 'sort_order'];

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyRegistrationDocument::class);
    }
}
