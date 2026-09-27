<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSubscription extends Model
{
    protected $fillable = ['category', 'email'];
}
