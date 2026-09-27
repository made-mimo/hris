<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseType extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'active', 'default_cap'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'default_cap' => 'decimal:2',
        ];
    }
}
