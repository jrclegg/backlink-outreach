<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = ['target_page_id', 'research_run_id', 'url', 'title', 'type', 'relevance', 'attainability', 'angle', 'sources', 'contact_email', 'contact_url', 'status', 'notes', 'placement_url', 'link_status', 'checked_at', 'next_check_at'];

    protected $casts = ['sources' => 'array', 'checked_at' => 'datetime', 'next_check_at' => 'datetime'];

    public function targetPage()
    {
        return $this->belongsTo(TargetPage::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
