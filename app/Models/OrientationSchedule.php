<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrientationSchedule extends Model
{
    protected $fillable = [
        'application_id',
        'scheduled_at',
        'scheduled_time',
        'place',
        'email_sent_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'date',
        // TIME column -- Eloquent parses "HH:MM:SS" into a Carbon instance
        // (anchored to today's date, which is fine since only the time
        // portion is ever read via ->format()).
        'scheduled_time' => 'datetime:H:i',
        'email_sent_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
