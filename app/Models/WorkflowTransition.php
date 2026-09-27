<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransition extends Model
{
    protected $fillable = ['workflow', 'from_state', 'actor', 'action', 'label', 'to_state'];
}
