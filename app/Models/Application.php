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
        'job_posting_location_id',
        'status',
        'applied_at',
        'notes',
        'ranking_notified_at',
        'qualification_check',
        'qualification_result',
        'qualification_checked_at',
        'qualification_notified_at',
        'schedule_notice_sent_at', // NEW — tracks Step 3 "Send all emails" button
    ];

    protected $casts = [
        'applied_at' => 'date',
        'ranking_notified_at' => 'datetime',
        'qualification_check' => 'array',
        'qualification_checked_at' => 'datetime',
        'qualification_notified_at' => 'datetime',
        'schedule_notice_sent_at' => 'datetime', // NEW
    ];

    public function talentPool()
    {
        return $this->hasOne(\App\Models\TalentPool::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function jobPostingLocation(): BelongsTo
    {
        return $this->belongsTo(JobPostingLocation::class);
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
     * Generate the official Application Code, assigned by Records after
     * they've checked the applicant's submitted requirements.
     *
     * Format: SDO-YYYY-#### (e.g. SDO-2026-0001), sequential and resetting
     * to 0001 at the start of each year. Applications don't get this at
     * registration anymore — transaction_number stays null until Records
     * calls this from RecordsController::assignCode().
     *
     * Wrap the caller in DB::transaction() (RecordsController already
     * does) so the lockForUpdate() below actually protects against two
     * staff assigning codes at the same moment. Note: locking only kicks
     * in once at least one code exists for the current year — the very
     * first assignment of a new year has a (very unlikely, low-traffic)
     * race window. If that ever becomes a real concern, move to a
     * dedicated per-year counters table instead.
     */
    public static function generateTransactionNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "SDO-{$year}-";

        $last = static::where('transaction_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $nextSeq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}