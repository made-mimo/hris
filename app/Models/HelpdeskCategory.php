<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Spec F4: "Grievance / Whistleblower category (simplified, per SI's request): a seeded, confidential ticket category with visibility restricted to a small, Admin-designated handler list." */
class HelpdeskCategory extends Model
{
    protected $fillable = ['name', 'is_active', 'is_confidential'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_confidential' => 'boolean'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function handlers(): HasMany
    {
        return $this->hasMany(HelpdeskCategoryHandler::class);
    }
}
