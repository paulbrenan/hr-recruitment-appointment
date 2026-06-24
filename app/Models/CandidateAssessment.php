<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateAssessment extends Model
{
    protected $fillable = [
        'application_id',
        'assessment_criteria_id',
        'score',
        'evaluator_remarks',
        'evaluated_by',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssessmentCriterion::class, 'assessment_criteria_id');
    }
}