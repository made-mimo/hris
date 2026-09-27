<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkWeekDay extends Model
{
    public const TYPES = ['full', 'half', 'non_working'];

    protected $fillable = ['weekday', 'day_type'];
}
