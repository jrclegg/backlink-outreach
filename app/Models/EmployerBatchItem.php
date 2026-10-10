<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerBatchItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['auto_reviewed_at' => 'datetime'];

    public function batch()
    {
        return $this->belongsTo(EmployerBatch::class, 'employer_batch_id');
    }

    public function contact()
    {
        return $this->belongsTo(EmployerContact::class, 'employer_contact_id');
    }

    public function message()
    {
        return $this->belongsTo(OutreachMessage::class, 'outreach_message_id');
    }
}
