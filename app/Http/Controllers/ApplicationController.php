<?php

namespace App\Http\Controllers;

use App\Models\Application;
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

        // NOTE: Application Documents and Interview Scheduling modules are
        // not wired to real data yet. These dummy collections are left in
        // place intentionally and should be replaced once those modules'
        // controllers/migrations are wired up (application_documents and
        // interview_schedules tables already exist).
        $documents = collect([
            (object) ['document_type' => 'Resume'],
            (object) ['document_type' => 'Transcript of Records'],
            (object) ['document_type' => 'Valid ID'],
        ]);

        $schedules = collect([
            (object) ['type' => 'interview', 'scheduled_at' => '2026-06-20 10:00:00', 'location' => 'HR Conference Room', 'status' => 'scheduled'],
        ]);

        return view('applications.show', compact('application', 'documents', 'schedules'));
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