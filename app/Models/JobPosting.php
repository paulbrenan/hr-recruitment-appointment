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
        'memo_pdf_path',
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
        if ($this->mandatory_requirements) {
            return $this->splitRequirementLines($this->mandatory_requirements);
        }

        // RequirementsExtractor always returns the same static
        // DepEd-standard text regardless of source document -- used as
        // the display fallback for every posting (imported or manual)
        // that hasn't had this column filled in, instead of duplicating
        // the same static text into every row at creation time.
        return (new \App\Services\RequirementsExtractor())->extract([])['mandatory'];
    }

    public function additionalRequirementsList(): array
    {
        if ($this->additional_requirements) {
            return $this->splitRequirementLines($this->additional_requirements);
        }

        return $this->splitRequirementLines(
            (new \App\Services\RequirementsExtractor())->extract([])['additional']
        );
    }

    /**
     * Public URL to the original memo PDF this posting was imported from
     * (if any), so applicants can view the exact source document listing
     * what's required. Null for postings created manually or imported
     * before this feature existed.
     */
    public function memoPdfUrl(): ?string
    {
        return $this->memo_pdf_path
            ? asset('storage/' . $this->memo_pdf_path)
            : null;
    }

    private function splitRequirementLines(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));
    }
    public function locations(): HasMany
    {
        return $this->hasMany(JobPostingLocation::class);
    }

    /**
     * Locations under this posting that still have at least one open
     * (unhired) slot. Empty for legacy postings with no location rows --
     * use hasOpenLegacyVacancy() for those instead.
     */
    public function openLocations()
    {
        return $this->locations->reject(fn ($loc) => $loc->isFilled())->values();
    }

    /**
     * For legacy postings (created before job_posting_locations existed,
     * so they have no location rows): checks the single `vacancies`
     * column on the posting itself against hired applications that have
     * no specific location attached.
     */
    public function hasOpenLegacyVacancy(): bool
    {
        if ($this->locations->isNotEmpty()) {
            return false; // not legacy -- handled per-location instead
        }

        $hired = $this->applications()->where('status', 'hired')->count();

        return $hired < max(1, (int) $this->vacancies);
    }

    /**
     * True if this posting still has room anywhere -- a place of
     * assignment with an open slot, or (for legacy postings) the single
     * vacancies column not yet fully hired. Used to decide whether the
     * position should still show up on the public register page and
     * whether the posting should auto-close after a hire.
     */
    public function hasAnyOpenVacancy(): bool
    {
        return $this->locations->isNotEmpty()
            ? $this->openLocations()->isNotEmpty()
            : $this->hasOpenLegacyVacancy();
    }

    public function panelists()
    {
        return $this->belongsToMany(Panelist::class, 'job_posting_panelist')
                    ->withPivot('is_available')
                    ->withTimestamps();
    }
}