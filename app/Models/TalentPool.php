<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentPool extends Model
{
    protected $fillable = [
        'candidate_id',
        'tags',
        'notes',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'date',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}