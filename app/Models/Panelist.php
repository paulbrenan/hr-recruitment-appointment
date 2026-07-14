<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Panelist extends Model
{
    protected $fillable = ['name'];

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class, 'job_posting_panelist')
                    ->withPivot('is_available')
                    ->withTimestamps();
    }
    public function interviewSchedules()
    {
        return $this->belongsToMany(\App\Models\InterviewSchedule::class, 'interview_schedule_panelist')
                    ->withTimestamps();
    }
}