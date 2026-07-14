<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    protected $fillable = [
        'talent_pool_id',
        'job_posting_id',
        'stage',
        'notes',
    ];

    public function talentPool()
    {
        return $this->belongsTo(TalentPool::class);
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    /** All possible stages in order */
    public static function stages(): array
    {
        return ['contacted', 'interested', 'interviewing', 'placed', 'dropped'];
    }
}