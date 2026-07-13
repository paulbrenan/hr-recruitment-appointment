<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        } else {
            $query->limit(100);
        }

        $logs = $query->get()->map(function ($log) {
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