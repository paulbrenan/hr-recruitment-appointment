<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalentPool extends Model
{
    protected $fillable = [
        'application_id',
        'candidate_id',
        'full_name',
        'email',
        'phone',
        'position_applied',
        'skills',
        'notes',
        'status',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    /** Turns "Excel, Payroll, SQL" into ['Excel','Payroll','SQL'] for display */
    public function skillsArray(): array
    {
        if (!$this->skills) return [];
        return array_filter(array_map('trim', explode(',', $this->skills)));
    }
}