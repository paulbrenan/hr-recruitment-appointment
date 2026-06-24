<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOffer extends Model
{
    protected $fillable = [
        'application_id',
        'compensation',
        'benefits',
        'terms',
        'offer_sent_at',
        'response_deadline',
        'status',
    ];

    protected $casts = [
        'offer_sent_at' => 'date',
        'response_deadline' => 'date',
        'compensation' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}