<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Record an activity log entry for a model event.
     */
    public static function recordFor($model, string $action, ?string $labelField = null): void
    {
        $className = class_basename($model);
        $readable = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));

        $label = ($labelField && isset($model->{$labelField}) && $model->{$labelField} !== null)
            ? $model->{$labelField}
            : ('#' . $model->getKey());

        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'subject_label' => $readable . ': ' . $label,
            'description' => ucfirst($action) . ' ' . $readable . ' (' . $label . ')',
        ]);
    }
}
