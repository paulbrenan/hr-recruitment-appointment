<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PdfImportBatch
 *
 * A transient holding area for parsed PDF import results, between the
 * OCR+parse request (JobPostingImportController::extract) and the final
 * confirm request (JobPostingImportController::confirm), which bulk-creates
 * real JobPosting rows from whichever candidates the user checked.
 *
 * The `candidates` column is a JSON array. Each candidate row looks like:
 *   [
 *       'title' => string,
 *       'salary_grade' => string,
 *       'qualification_education' => ?string,
 *       'qualification_training' => ?string,
 *       'qualification_experience' => ?string,
 *       'qualification_eligibility' => ?string,
 *       'duties_responsibilities' => ?string,
 *       'vacancies' => int,
 *       'place_of_assignment' => string,
 *       'group_key' => string, // groups schools belonging to the same detected position block, for the review screen's collapsible sections
 *   ]
 *
 * Rows expire after 24 hours (see expires_at) -- a scheduled cleanup or
 * manual artisan command can purge expired batches. Confirmed/rejected
 * batches are deleted immediately after the confirm request completes,
 * regardless of expires_at.
 */
class PdfImportBatch extends Model
{
    protected $fillable = [
        'original_filename',
        'candidates',
        'requirements',
        'newly_registered_titles',
        'expires_at',
        'status',
        'error_message',
        'pdf_path',
        'memo_pdf_path',
    ];

    protected $casts = [
        'candidates' => 'array',
        'requirements' => 'array',
        'newly_registered_titles' => 'array',
        'expires_at' => 'datetime',
    ];
}