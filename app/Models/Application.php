<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    protected $fillable = [
        'transaction_number',
        'candidate_id',
        'job_posting_id',
        'status',
        'applied_at',
        'notes',
        'ranking_notified_at' => 'datetime',
    ];

    protected $casts = [
        'applied_at' => 'date',
        'ranking_notified_at'   => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function interviewSchedules(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CandidateAssessment::class);
    }

    public function jobOffer(): HasOne
    {
        return $this->hasOne(JobOffer::class);
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    /**
     * Generate a unique, human-readable transaction number.
     * Format: APP-YYYYMMDD-XXXXXX
     *
     * Loops until a collision-free value is found (practically instant —
     * 36^6 = ~2.1 billion combinations).
     */
    public static function generateTransactionNumber(): string
    {
        do {
            $suffix = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $number = 'APP-' . date('Ymd') . '-' . $suffix;
        } while (static::where('transaction_number', $number)->exists());

        return $number;
    }
}
