<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Appointment;
use App\Notifications\AppointmentLetterNotification;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with(['application.candidate', 'application.jobPosting'])
            ->latest()
            ->get();

        // Eligible = offer accepted, and not already appointed.
        $eligibleApplications = Application::with(['candidate', 'jobPosting'])
            ->where('status', 'offer_accepted')
            ->whereDoesntHave('appointment')
            ->get();

        return view('appointments.index', compact('appointments', 'eligibleApplications'));
    }

    /**
     * Manual creation â€” HR fills in all fields themselves. Replaces the
     * earlier one-click "generate" flow, since this project is HR-side
     * only for now (no candidate self-service / auto-generation).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id' => ['required', 'exists:applications,id', 'unique:appointments,application_id'],
            'position_title' => ['required', 'string', 'max:255'],
            'item_number' => ['nullable', 'string', 'max:255'],
            'appointment_status' => ['required', 'in:permanent,temporary,provisional,casual,job_order,ojt'],
            'appointment_date' => ['nullable', 'date'],
            'onboarding_date' => ['nullable', 'date'],
        ]);

        $appointment = Appointment::create($validated);

        $application = Application::find($validated['application_id']);
        $application->update(['status' => 'hired']);

        // Reload with relations so the notification has candidate/jobPosting
        // available without extra queries inside the Notification class.
        $appointment->load(['application.candidate', 'application.jobPosting']);

        // Deliver the appointment letter to the candidate immediately.
        $appointment->application->candidate->notify(new AppointmentLetterNotification($appointment));

        $appointment->update(['letter_sent_at' => now()]);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment created successfully. Appointment letter emailed.');
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'position_title' => ['required', 'string', 'max:255'],
            'item_number' => ['nullable', 'string', 'max:255'],
            'appointment_status' => ['required', 'in:permanent,temporary,provisional,casual,job_order,ojt'],
            'appointment_date' => ['nullable', 'date'],
            'onboarding_date' => ['nullable', 'date'],
        ]);

        $appointment->update($validated);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment deleted successfully.');
    }
}
