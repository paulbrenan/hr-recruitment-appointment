<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobPostingLocation extends Model
{
    protected $fillable = ['job_posting_id', 'place_of_assignment', 'vacancies'];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }
}