<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screen extends Model
{
    protected $fillable = ['key', 'label', 'nav_group', 'sort_order', 'module_key', 'help_tag'];
}
