<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\OrientationSchedule;
use App\Notifications\OrientationScheduleNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrientationScheduleController extends Controller
{
    /**
     * POST /orientation-schedules
     * Checkbox-picked candidates + a date/place, capped at this
     * posting's remaining vacancy slots -- same shape as
     * JobOfferController::store(), but instead of drafting an offer for
     * later sending, this creates the schedule AND sends the invitation
     * email immediately (matching InterviewScheduleController's
     * create -> notify right away pattern).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id'     => 'required|exists:job_postings,id',
            'application_ids'    => 'required|array|min:1',
            'application_ids.*'  => 'exists:applications,id|distinct',
            'scheduled_at'       => 'required|date|after_or_equal:today',
            'scheduled_time'     => 'nullable|date_format:H:i',
            'place'              => 'nullable|string|max:255',
        ]);

        $posting = JobPosting::findOrFail($validated['job_posting_id']);

        // Re-enforce the vacancy cap server-side, same reasoning as
        // JobOfferController::store() -- the checkbox UI disables extra
        // boxes past the limit client-side only, never trust that alone.
        $alreadyScheduled = OrientationSchedule::whereHas('application', fn ($q) => $q->where('job_posting_id', $posting->id))
            ->where('status', 'scheduled')
            ->count();
        $limit = max(0, ((int) $posting->vacancies ?: 1) - $alreadyScheduled);

        $applicationIds = array_slice($validated['application_ids'], 0, $limit);
        if (empty($applicationIds)) {
            return back()->with('error', "No open orientation slots remain for this posting's vacancy count.");
        }

        $created = 0;
        $emailFailures = [];

        foreach ($applicationIds as $applicationId) {
            // Skip anyone who already has an active (non-cancelled)
            // orientation schedule -- picking someone twice shouldn't
            // create duplicate rows/emails.
            if (OrientationSchedule::where('application_id', $applicationId)->where('status', 'scheduled')->exists()) {
                continue;
            }

            $orientation = OrientationSchedule::create([
                'application_id' => $applicationId,
                'scheduled_at'   => $validated['scheduled_at'],
                'scheduled_time' => $validated['scheduled_time'] ?? null,
                'place'          => $validated['place'] ?? null,
                'status'         => 'scheduled',
            ]);
            $created++;

            $orientation->load(['application.candidate', 'application.jobPosting']);

            try {
                $orientation->application->candidate->notify(new OrientationScheduleNotification($orientation));
                $orientation->update(['email_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('Failed to send orientation schedule email for application ' . $applicationId . ': ' . $e->getMessage());
                $emailFailures[] = $orientation->application->candidate->full_name ?? "Application #{$applicationId}";
            }
        }

        if ($created === 0) {
            return back()->with('error', 'Selected candidate(s) already have an orientation scheduled -- nothing new was generated.');
        }

        $message = "Scheduled orientation for {$created} candidate(s).";
        if (empty($emailFailures)) {
            $message .= ' Invitation email(s) sent.';
        } else {
            $message .= ' Email failed to send for: ' . implode(', ', $emailFailures) . ' -- check mail configuration.';
        }

        return back()->with('success', $message);
    }

    /**
     * DELETE /orientation-schedules/{id}
     */
    public function destroy($id)
    {
        $orientation = OrientationSchedule::findOrFail($id);
        $orientation->delete();

        return back()->with('success', 'Orientation schedule deleted.');
    }
}
