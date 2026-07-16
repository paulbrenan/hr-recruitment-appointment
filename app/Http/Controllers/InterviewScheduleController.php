<?php
namespace App\Http\Controllers;
use App\Models\Application;
use App\Models\InterviewSchedule;
use App\Models\JobPosting;
use App\Models\Panelist;
use App\Notifications\ScheduleInvitationNotification;
use App\Notifications\InterviewerInvitationNotification;
use App\Notifications\RankingResultWithScheduleNotification;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
class InterviewScheduleController extends Controller
{
    public function __construct(private RankingService $rankingService)
    {
    }

    private function createRules(): array
    {
        return [
            'application_id'  => ['required', 'exists:applications,id'],
            'type'            => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'    => ['required', 'date', 'after_or_equal:now'],
            'location'        => ['nullable', 'string', 'max:255'],
            // Legacy single-interviewer fields — kept nullable so old data survives
            'interviewer_name'  => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
            'panelist_ids'    => ['nullable', 'array'],
            'panelist_ids.*'  => ['exists:panelists,id'],
        ];
    }
    private function updateRules(): array
    {
        return [
            'application_id'    => ['required', 'exists:applications,id'],
            'type'              => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'      => ['required', 'date'],
            'location'          => ['nullable', 'string', 'max:255'],
            'interviewer_name'  => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
            'panelist_ids'      => ['nullable', 'array'],
            'panelist_ids.*'    => ['exists:panelists,id'],
            'status'            => ['required', 'in:scheduled,completed,cancelled,no_show'],
            'remarks'           => ['nullable', 'string'],
        ];
    }
    // index() removed -- the old standalone Scheduling page is gone.
    // Schedules are now created and managed directly inside the
    // job-postings pipeline's "Open Ranking & Scheduling" step.

    public function store(Request $request)
    {
        $validated = $request->validate($this->createRules());
        $validated['status'] = 'scheduled';
        // Remove pivot fields from validated before create (not real columns)
        $panelistIds = array_map('intval', $request->input('panelist_ids', []));
        unset($validated['panelist_ids']);
        $schedule = InterviewSchedule::create($validated);
        if (!empty($panelistIds)) {
            $schedule->panelists()->sync($panelistIds);
        }

        $schedule->load(['application.candidate', 'application.jobPosting']);
        $application = $schedule->application;

        $combinedSent = false;

        if ($application->status !== 'ranking_sent') {
            $rankings = $this->rankingService->computeRankings($application->jobPosting);
            $ranked = $rankings->firstWhere('application_id', $application->id);

            if ($ranked && $ranked['passed']) {
                try {
                    $application->candidate->notify(
                        new RankingResultWithScheduleNotification($ranked, $application->jobPosting, $schedule)
                    );
                    $application->update(['status' => 'ranking_sent']);
                    $combinedSent = true;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send combined ranking+schedule invitation: ' . $e->getMessage());
                }
            }
        }

        if (! $combinedSent) {
            try {
                $application->candidate->notify(new ScheduleInvitationNotification($schedule));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send candidate schedule invitation: ' . $e->getMessage());
            }
        }

        if ($schedule->interviewer_email) {
            try {
                Notification::route('mail', $schedule->interviewer_email)
                    ->notify(new InterviewerInvitationNotification($schedule));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send interviewer schedule invitation: ' . $e->getMessage());
            }
        }

        // Email every panelist selected on the checklist (1-6), not just
        // the legacy single interviewer_email field above. Panelists
        // without an email on file are silently skipped rather than
        // failing the whole request.
        foreach ($schedule->panelists as $panelist) {
            if (empty($panelist->email)) {
                continue;
            }
            try {
                Notification::route('mail', $panelist->email)
                    ->notify(new InterviewerInvitationNotification($schedule));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to send panelist schedule invitation to {$panelist->email}: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Schedule created successfully. Invitation email sent.');
    }
    public function update(Request $request, $id)
    {
        $schedule = InterviewSchedule::findOrFail($id);
        $validated = $request->validate($this->updateRules());
        $panelistIds = array_map('intval', $request->input('panelist_ids', []));
        unset($validated['panelist_ids']);
        $schedule->update($validated);
        $schedule->panelists()->sync($panelistIds);
        return back()->with('success', 'Schedule updated successfully.');
    }
    /**
     * GET /interviews/panelists-for-posting/{jobPostingId}
     * Returns JSON list of panelists assigned to a job posting with availability flag.
     * Used by the scheduling modal to populate the checklist when an application is selected.
     */
    public function panelistsForPosting($jobPostingId)
    {
        $posting = JobPosting::findOrFail($jobPostingId);
        $panelists = $posting->panelists()->orderBy('name')->get()->map(function ($p) {
            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'is_available' => (bool) $p->pivot->is_available,
            ];
        });
        return response()->json($panelists);
    }

    /**
     * Create one InterviewSchedule per qualified applicant on a given posting.
     * Called from the pipeline dashboard's Step 3 'New schedule' modal.
     * If a job_posting_location_id is provided, only applicants assigned to
     * that location are scheduled; otherwise all qualified applicants on the
     * posting are scheduled.
     */
    public function storeForPosting(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date', 'after_or_equal:now'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
        ]);

        $panelistIds = array_map('intval', $request->input('panelist_ids', []));

        $query = Application::where('job_posting_id', $validated['job_posting_id'])
            ->whereIn('status', ['qualified', 'interview_scheduled', 'ranked']);

        if (!empty($validated['job_posting_location_id'])) {
            $query->where('job_posting_location_id', $validated['job_posting_location_id']);
        }

        $applications = $query->with(['candidate', 'jobPosting'])->get();

        if ($applications->isEmpty()) {
            return redirect()->back()->with('error', 'No qualified applicants found for this posting/location.');
        }

        $created = 0;
        $schedulesByApplication = [];     // application_id => ['application' => Application, 'schedules' => []]
        $assignmentsByPanelistEmail = []; // panelist email => array of assignment rows
        $hasNewByApplication = [];        // application_id => bool, true if at least one schedule was newly created

        foreach ($applications as $application) {
            foreach ($validated['type'] as $type) {
                // Guard against duplicate submissions (e.g. a double-click
                // or resubmitted form): if this applicant already has an
                // identical schedule -- same type, date/time, and location
                // -- reuse it instead of creating another one.
                $schedule = $application->interviewSchedules()
                    ->where('type', $type)
                    ->where('scheduled_at', $validated['scheduled_at'])
                    ->where('location', $validated['location'] ?? null)
                    ->first();

                $isNew = ! $schedule;

                if ($isNew) {
                    $schedule = InterviewSchedule::create([
                        'application_id' => $application->id,
                        'type'           => $type,
                        'scheduled_at'   => $validated['scheduled_at'],
                        'location'       => $validated['location'] ?? null,
                        'status'         => 'scheduled',
                    ]);
                    $created++;
                    $hasNewByApplication[$application->id] = true;
                }

                if (!empty($panelistIds)) {
                    $schedule->panelists()->sync($panelistIds);
                }
                $schedule->load('panelists');

                if (!isset($schedulesByApplication[$application->id])) {
                    $schedulesByApplication[$application->id] = [
                        'application' => $application,
                        'schedules'   => [],
                    ];
                }
                $schedulesByApplication[$application->id]['schedules'][] = $schedule;

                // Don't re-notify panelists about a schedule that already
                // existed -- only newly created schedules get an email.
                if (! $isNew) {
                    continue;
                }

                // Collect one assignment row per panelist -- emailed once
                // per panelist below instead of once per schedule type.
                foreach ($schedule->panelists as $panelist) {
                    if (empty($panelist->email)) {
                        continue;
                    }
                    if (!isset($assignmentsByPanelistEmail[$panelist->email])) {
                        $assignmentsByPanelistEmail[$panelist->email] = [];
                    }
                    $assignmentsByPanelistEmail[$panelist->email][] = [
                        'schedule'   => $schedule,
                        'candidate'  => $application->candidate,
                        'jobPosting' => $application->jobPosting,
                    ];
                }
            }
        }

        // One combined email per candidate listing every schedule type
        // just created for them (Open Ranking / Exam / Interview, etc.),
        // instead of one separate "You're Invited" email per type.
        foreach ($schedulesByApplication as $applicationId => $entry) {
            if (empty($hasNewByApplication[$applicationId])) {
                continue;
            }
            try {
                $application = $entry['application'];
                $allSchedules = $application->interviewSchedules()->orderBy('scheduled_at')->get();
                $application->candidate->notify(
                    new \App\Notifications\QualifiedScheduleBundleNotification($application, $allSchedules)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send candidate schedule bundle: ' . $e->getMessage());
            }
        }

        // One combined email per panelist listing every assignment they
        // received in this batch (possibly across multiple candidates
        // and schedule types), instead of one email per schedule.
        foreach ($assignmentsByPanelistEmail as $email => $assignments) {
            try {
                \Illuminate\Support\Facades\Notification::route('mail', $email)
                    ->notify(new \App\Notifications\PanelistScheduleBundleNotification(collect($assignments)));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to send panelist schedule bundle to {$email}: " . $e->getMessage());
            }
        }

        // Redirect back to the job posting pipeline (Step 3)
        return redirect()
            ->route('job-postings.show', $validated['job_posting_id'])
            ->with('success', "Scheduled {$created} applicant(s) and sent invitations.");
    }

    public function destroy($id)
    {
        $schedule = InterviewSchedule::findOrFail($id);
        $schedule->delete();
        return back()->with('success', 'Schedule deleted successfully.');
    }
}