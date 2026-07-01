<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Candidate extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'phone',
        'address',
        'age',
        'sex',
        'civil_status',
        'religion',
        'disability',
        'ethnic_group',
        'education',
        'training_hours',
        'years_experience',
        'eligibility',
        'resume_path',
        'photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function routeNotificationForVonage(): string
    {
        return $this->phone; // e.g. "+639171234567"
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function talentPool(): HasOne
    {
        return $this->hasOne(TalentPool::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}