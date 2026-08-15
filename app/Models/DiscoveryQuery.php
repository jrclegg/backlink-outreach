<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscoveryQuery extends Model
{
    protected $fillable = ['project_id', 'keyword', 'footprint_pattern', 'status', 'results_found', 'error'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function results()
    {
        return $this->hasMany(DiscoveryResult::class);
    }
}
