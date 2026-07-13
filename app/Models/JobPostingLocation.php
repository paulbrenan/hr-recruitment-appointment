<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPostingLocation extends Model
{
    protected $fillable = ['job_posting_id', 'place_of_assignment', 'vacancies'];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * How many candidates have been HIRED for this specific place of
     * assignment. Uses the `hired_count` withCount alias when eager
     * loaded (see JobPosting::openLocations callers) to avoid N+1
     * queries; falls back to a live count otherwise.
     */
    public function hiredCount(): int
    {
        if (array_key_exists('hired_count', $this->attributes)) {
            return (int) $this->attributes['hired_count'];
        }

        return $this->applications()->where('status', 'hired')->count();
    }

    public function remainingVacancies(): int
    {
        return max(0, (int) $this->vacancies - $this->hiredCount());
    }

    public function isFilled(): bool
    {
        return $this->remainingVacancies() <= 0;
    }
}