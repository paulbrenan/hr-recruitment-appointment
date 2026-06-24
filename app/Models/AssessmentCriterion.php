<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCriterion extends Model
{
    protected $table = 'assessment_criteria';

    protected $fillable = [
        'job_posting_id',
        'name',
        'weight_percentage',
        'description',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidateAssessments(): HasMany
    {
        return $this->hasMany(CandidateAssessment::class, 'assessment_criteria_id');
    }
}