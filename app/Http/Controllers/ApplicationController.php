<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InterviewSchedule;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Application::with(['candidate', 'jobPosting'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $applications = $query->get();

        return view('applications.index', compact('applications'));
    }

    public function show($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);

        // Real interview schedules from the database
        $schedules = InterviewSchedule::where('application_id', $id)
            ->orderBy('scheduled_at')
            ->get();

        return view('applications.show', compact('application', 'schedules'));
    }

    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:submitted,screening,shortlisted,interview_scheduled,assessed,ranked,offer_sent,offer_accepted,offer_declined,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $application->update($validated);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Application status updated successfully.');
    }

    public function destroy($id)
    {
        $application = Application::findOrFail($id);
        $application->delete();

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application deleted successfully.');
    }
}