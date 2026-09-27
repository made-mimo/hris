<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeIdSequence extends Model
{
    protected $fillable = ['scope_key', 'next_value'];
}
