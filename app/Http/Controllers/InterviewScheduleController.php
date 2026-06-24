<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InterviewSchedule;
use Illuminate\Http\Request;

class InterviewScheduleController extends Controller
{
    /**
     * Validation rules for creating a schedule. Status is intentionally
     * excluded here — it always defaults to 'scheduled' on creation,
     * matching the existing "New schedule" modal which has no status
     * field.
     */
    private function createRules(): array
    {
        return [
            'application_id' => ['required', 'exists:applications,id'],
            'type' => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interviewer_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validation rules for updating a schedule. Includes status and
     * remarks, which are only editable after creation.
     */
    private function updateRules(): array
    {
        return [
            'application_id' => ['required', 'exists:applications,id'],
            'type' => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interviewer_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:scheduled,completed,cancelled,no_show'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function index()
    {
        $schedules = InterviewSchedule::with(['application.candidate', 'application.jobPosting'])
            ->latest('scheduled_at')
            ->get();

        // For the "New schedule" / "Edit schedule" modal's Application dropdown.
        $applications = Application::with(['candidate', 'jobPosting'])->get();

        return view('interviews.index', compact('schedules', 'applications'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->createRules());
        $validated['status'] = 'scheduled';

        InterviewSchedule::create($validated);

        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule created successfully.');
    }

    public function update(Request $request, $id)
    {
        $schedule = InterviewSchedule::findOrFail($id);

        $validated = $request->validate($this->updateRules());

        $schedule->update($validated);

        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule updated successfully.');
    }

    public function destroy($id)
    {
        $schedule = InterviewSchedule::findOrFail($id);
        $schedule->delete();

        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule deleted successfully.');
    }
}