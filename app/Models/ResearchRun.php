<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchRun extends Model
{
    protected $fillable = ['target_page_id', 'instructions', 'status', 'report', 'sources', 'error'];

    protected $casts = ['sources' => 'array'];

    public function targetPage()
    {
        return $this->belongsTo(TargetPage::class);
    }
}
