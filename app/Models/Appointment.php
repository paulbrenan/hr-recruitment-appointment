<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'application_id',
        'position_title',
        'item_number',
        'appointment_status',
        'appointment_date',
        'onboarding_date',
        'appointment_paper_path',
        'letter_sent_at',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'onboarding_date' => 'date',
        'letter_sent_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
