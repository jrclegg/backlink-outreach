<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerLead extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['deadline' => 'datetime', 'first_seen' => 'datetime'];
}
