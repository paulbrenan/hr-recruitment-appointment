<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    protected $fillable = [
        'title',
        'description',
        'duties_responsibilities',
        'qualification_standards',
        'place_of_assignment',
        'employment_type',
        'vacancies',
        'posted_at',
        'closes_at',
        'status',
    ];

    protected $casts = [
        'posted_at' => 'date',
        'closes_at' => 'date',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function assessmentCriteria(): HasMany
    {
        return $this->hasMany(AssessmentCriterion::class);
    }
}