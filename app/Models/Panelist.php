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
}