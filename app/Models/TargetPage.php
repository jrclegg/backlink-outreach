<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetPage extends Model
{
    protected $fillable = ['project_id', 'url', 'title', 'brief'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function researchRuns()
    {
        return $this->hasMany(ResearchRun::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
