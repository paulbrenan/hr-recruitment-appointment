<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryGrade extends Model
{
    protected $fillable = ['budget_circular_id', 'grade', 'step', 'amount'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function budgetCircular(): BelongsTo
    {
        return $this->belongsTo(BudgetCircular::class);
    }

    /**
     * Drop-in replacement for config('salary_grades.table').
     * Returns [grade => [step1, step2, ..., step8]] (0-indexed steps,
     * matching how JobOfferController / offers/index.blade.php already
     * index into it: $sgTable[$grade][$step - 1]).
     *
     * Falls back to the old config array if no circular has been
     * imported/confirmed yet, so nothing breaks on a fresh install.
     */
    public static function currentTableArray(): array
    {
        $circular = BudgetCircular::current()->first();

        if (!$circular) {
            return config('salary_grades.table', []);
        }

        $table = [];
        foreach (
            static::where('budget_circular_id', $circular->id)
                ->orderBy('grade')->orderBy('step')->get()
            as $row
        ) {
            $table[$row->grade][$row->step - 1] = (float) $row->amount;
        }

        return $table;
    }
}
