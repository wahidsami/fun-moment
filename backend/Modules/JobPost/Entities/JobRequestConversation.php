<?php

namespace Modules\JobPost\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobRequestConversation extends Model
{
    use HasFactory;

    protected $table = 'job_request_conversations';

    protected $fillable = [
        'job_request_id',
        'type',
        'message',
        'attachment',
        'notify',
    ];

    public function job_request()
    {
        return $this->belongsTo(JobRequest::class, 'job_request_id', 'id');
    }

    public function request()
    {
        return $this->belongsTo(JobRequest::class, 'job_request_id', 'id');
    }
}
