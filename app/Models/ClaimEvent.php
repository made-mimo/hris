<?php

namespace App\Models;

use Database\Factories\ClaimEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimEvent extends Model
{
    /** @use HasFactory<ClaimEventFactory> */
    use HasFactory;

    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
