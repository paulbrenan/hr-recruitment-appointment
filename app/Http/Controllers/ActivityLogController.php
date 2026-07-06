<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user->name ?? 'System',
                    'action' => $log->action,
                    'subject_label' => $log->subject_label,
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('M d, Y g:i A'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }
}
