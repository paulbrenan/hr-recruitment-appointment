<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCircular extends Model
{
    protected $fillable = [
        'circular_no',
        'subject',
        'effective_date',
        'source_type',
        'source_file_path',
        'original_filename',
        'status',
        'error_message',
        'is_current',
        'imported_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function salaryGrades(): HasMany
    {
        return $this->hasMany(SalaryGrade::class)->orderBy('grade')->orderBy('step');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Grade => [step1amount, step2amount, ...] for this specific circular
     * (used on the review page, before it's confirmed as current).
     */
    public function tableArray(): array
    {
        $table = [];
        foreach ($this->salaryGrades as $row) {
            $table[$row->grade][$row->step - 1] = (float) $row->amount;
        }
        ksort($table);
        return $table;
    }
}
