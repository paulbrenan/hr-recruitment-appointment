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
        'qualification_education',
        'qualification_training',
        'qualification_experience',
        'qualification_eligibility',
        'mandatory_requirements',
        'additional_requirements',
        'place_of_assignment',
        'employment_type',
        'salary_grade',
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

    /**
     * Helper: split a newline-delimited requirements column into a clean array,
     * dropping empty lines. Used by the form to render existing items and by
     * the show page to list them.
     */
    public function mandatoryRequirementsList(): array
    {
        return $this->splitRequirementLines($this->mandatory_requirements);
    }

    public function additionalRequirementsList(): array
    {
        return $this->splitRequirementLines($this->additional_requirements);
    }

    private function splitRequirementLines(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));
    }
}