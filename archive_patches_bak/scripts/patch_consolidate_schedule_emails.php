<?php
/**
 * Patch: consolidate schedule emails.
 *
 * PROBLEM: InterviewScheduleController::storeForPosting() loops over
 * every selected schedule type (Open Ranking / Exam / Interview) and
 * sends a SEPARATE email per type -- to the candidate AND to every
 * panelist. A candidate scheduled for all 3 types gets 3 "You're
 * Invited" emails; a panelist assigned to all 3 gets 3 emails too.
 *
 * FIX:
 *   1. Add App\Notifications\QualifiedScheduleBundleNotification --
 *      ONE email to the candidate listing every schedule type/date/
 *      location on the application, with the Qualified PDF attached.
 *   2. Add App\Notifications\PanelistScheduleBundleNotification --
 *      ONE email to each panelist listing every assignment they got
 *      in this batch (across candidates/types).
 *   3. Add the two matching mail Blade views.
 *   4. Patch InterviewScheduleController::storeForPosting() to collect
 *      schedules/assignments first, then send exactly one email per
 *      candidate and one per panelist after the loop.
 *
 * Run from your project root: php patch_consolidate_schedule_emails.php
 */

$root = __DIR__;

$controllerFile = $root . '/app/Http/Controllers/InterviewScheduleController.php';
$notifDir       = $root . '/app/Notifications';
$mailViewDir    = $root . '/resources/views/mail';

foreach ([$controllerFile] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "ABORT: required file not found: $f\n");
        fwrite(STDERR, "Edit the path variables at the top of this script if your project layout differs.\n");
        exit(1);
    }
}
foreach ([$notifDir, $mailViewDir] as $d) {
    if (!is_dir($d)) {
        fwrite(STDERR, "ABORT: expected directory not found: $d\n");
        exit(1);
    }
}

// ────────────────────────────────────────────────────────────────────
// 1. New notification: QualifiedScheduleBundleNotification
// ────────────────────────────────────────────────────────────────────
$qualifiedBundlePath = $notifDir . '/QualifiedScheduleBundleNotification.php';

if (file_exists($qualifiedBundlePath)) {
    fwrite(STDERR, "SKIP (already exists): $qualifiedBundlePath\n");
} else {
    $qualifiedBundleCode = <<<'PHP'
<?php

namespace App\Notifications;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a qualified candidate who has one or more interview/exam/open
 * ranking schedules. Combines the qualified-notice PDF with EVERY
 * schedule currently on the application into a single email, instead
 * of one email per schedule type.
 */
class QualifiedScheduleBundleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly Collection $schedules,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'open_ranking' => 'Open Ranking Session',
            'interview'    => 'Interview',
            'exam'         => 'Examination',
            default        => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;
        $candidate   = $application->candidate;
        $jobPosting  = $application->jobPosting;
        $criteriaRows = $this->buildCriteriaRows();

        $scheduleRows = $this->schedules
            ->sortBy('scheduled_at')
            ->values()
            ->map(function ($schedule) {
                $panelistNames = method_exists($schedule, 'panelists')
                    ? $schedule->panelists->pluck('name')->implode(', ')
                    : '';

                return [
                    'type_label' => $this->typeLabel($schedule->type),
                    'when'       => $schedule->scheduled_at->format('l, F j, Y \a\t g:i A'),
                    'location'   => $schedule->location,
                    'panelists'  => $panelistNames ?: $schedule->interviewer_name,
                ];
            })
            ->all();

        $pdf = Pdf::loadView('pdf.qualification-notice', [
            'application'  => $application,
            'candidate'    => $candidate,
            'jobPosting'   => $jobPosting,
            'passed'       => true,
            'criteriaRows' => $criteriaRows,
            'check'        => $application->qualification_check,
        ])->setPaper('letter');

        $filename = 'Qualified-Notice-' . $application->transaction_number . '.pdf';

        return (new MailMessage)
            ->subject("Congratulations! You're Qualified & Scheduled — {$jobPosting->title}")
            ->view('mail.qualified-schedule-bundle', [
                'application'  => $application,
                'candidate'    => $candidate,
                'jobPosting'   => $jobPosting,
                'criteriaRows' => $criteriaRows,
                'scheduleRows' => $scheduleRows,
            ])
            ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
    }

    public function toArray($notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'schedule_ids'    => $this->schedules->pluck('id')->all(),
        ];
    }

    /** Same shape as QualificationResultNotification::buildCriteriaRows(). */
    private function buildCriteriaRows(): array
    {
        $jobPosting = $this->application->jobPosting;
        $criteria = $this->application->qualification_check['criteria'] ?? [];

        $map = [
            'education'   => ['label' => 'Education', 'required' => $jobPosting->qualification_education ?? null],
            'experience'  => ['label' => 'Experience', 'required' => $jobPosting->qualification_experience ?? null],
            'training'    => ['label' => 'Training', 'required' => $jobPosting->qualification_training ?? null],
            'eligibility' => ['label' => 'Eligibility', 'required' => $jobPosting->qualification_eligibility ?? null],
        ];

        $rows = [];
        foreach ($map as $key => $meta) {
            $rows[] = [
                'label'    => $meta['label'],
                'required' => $meta['required'],
                'actual'   => $criteria[$key]['actual'] ?? null,
                'passed'   => (bool) ($criteria[$key]['passed'] ?? false),
            ];
        }

        return $rows;
    }
}
PHP;
    file_put_contents($qualifiedBundlePath, $qualifiedBundleCode);
    echo "Created: $qualifiedBundlePath\n";
}

// ────────────────────────────────────────────────────────────────────
// 2. New notification: PanelistScheduleBundleNotification
// ────────────────────────────────────────────────────────────────────
$panelistBundlePath = $notifDir . '/PanelistScheduleBundleNotification.php';

if (file_exists($panelistBundlePath)) {
    fwrite(STDERR, "SKIP (already exists): $panelistBundlePath\n");
} else {
    $panelistBundleCode = <<<'PHP'
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a single panelist/interviewer, bundling every schedule
 * assignment they were given in one batch (possibly across several
 * candidates and several schedule types) into one email, instead of
 * one email per schedule.
 *
 * $assignments is a Collection of:
 *   ['schedule' => InterviewSchedule, 'candidate' => Candidate, 'jobPosting' => JobPosting]
 */
class PanelistScheduleBundleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Collection $assignments,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'open_ranking' => 'Open Ranking Session',
            'interview'    => 'Interview',
            'exam'         => 'Examination',
            default        => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $rows = $this->assignments
            ->sortBy(fn ($a) => $a['schedule']->scheduled_at)
            ->values()
            ->map(function ($a) {
                return [
                    'type_label' => $this->typeLabel($a['schedule']->type),
                    'when'       => $a['schedule']->scheduled_at->format('l, F j, Y \a\t g:i A'),
                    'location'   => $a['schedule']->location,
                    'candidate'  => $a['candidate']->full_name,
                    'position'   => $a['jobPosting']->title,
                ];
            })
            ->all();

        $count = count($rows);
        $subject = $count === 1
            ? "Schedule Assignment: {$rows[0]['type_label']} — {$rows[0]['position']}"
            : "Schedule Assignments ({$count}) — DepEd Cavite Recruitment";

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.panelist-schedule-bundle', [
                'rows' => $rows,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'schedule_ids' => $this->assignments->pluck('schedule.id')->all(),
        ];
    }
}
PHP;
    file_put_contents($panelistBundlePath, $panelistBundleCode);
    echo "Created: $panelistBundlePath\n";
}

// ────────────────────────────────────────────────────────────────────
// 3. New Blade view: mail.qualified-schedule-bundle
// ────────────────────────────────────────────────────────────────────
$qualifiedBundleViewPath = $mailViewDir . '/qualified-schedule-bundle.blade.php';

if (file_exists($qualifiedBundleViewPath)) {
    fwrite(STDERR, "SKIP (already exists): $qualifiedBundleViewPath\n");
} else {
    $qualifiedBundleView = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#1a7d3a; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#e6ecf7; border:2px dashed #0047b3; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.15rem; font-weight:800; color:#003087; letter-spacing:.02em; }
  .result-box { border-left:4px solid #1a7d3a; background:#e9f9ef; padding:16px 20px; border-radius:6px; margin-bottom:20px; }
  .result-box p { margin:0; font-weight:700; font-size:1rem; color:#1a7d3a; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .sched-card { background:#fafbfc; border:1px solid #e3e8ec; border-radius:6px; padding:16px 20px; margin-bottom:14px; }
  .sched-card .type { font-weight:700; color:#003087; font-size:.92rem; margin-bottom:8px; }
  .detail-row { display:flex; padding:4px 0; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:110px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .crit-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-top:4px; }
  .crit-table th { text-align:left; background:#f4f6f7; color:#555; font-size:.72rem; text-transform:uppercase;
                    letter-spacing:.03em; padding:8px 10px; border-bottom:2px solid #e3e8ec; }
  .crit-table td { padding:9px 10px; border-bottom:1px solid #f0f2f4; vertical-align:top; }
  .crit-table .badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:.72rem; font-weight:700; }
  .crit-table .badge.pass { background:#e9f9ef; color:#1a7d3a; }
  .crit-table .badge.fail { background:#fdeceb; color:#b3261e; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
  .btn-wrap { text-align:center; margin:24px 0; }
  .btn { background:#003087; color:#fff; text-decoration:none; padding:12px 32px;
         border-radius:6px; font-weight:700; font-size:.9rem; display:inline-block; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon">&#10003;</span>
    <h1>You're Qualified &amp; Scheduled</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Region IV-A &bull; Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>

    <div class="txn">
      <div class="lbl">Transaction Number</div>
      <div class="num">{{ $application->transaction_number }}</div>
    </div>

    <div class="result-box">
      <p>You meet the qualification standards for this position.</p>
    </div>

    <p>
      Congratulations! Based on our review of your submitted documents against the qualification
      standards for <strong>{{ $jobPosting->title ?? 'the position' }}</strong>, your application has
      been marked <strong>Qualified</strong>. The official notice is attached to this email as a PDF.
    </p>

    <div class="section-title">
      Your Schedule{{ count($scheduleRows) > 1 ? 's' : '' }}
    </div>

    @foreach ($scheduleRows as $row)
    <div class="sched-card">
      <div class="type">{{ $row['type_label'] }}</div>
      <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $row['when'] }}</span></div>
      @if ($row['location'])
      <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $row['location'] }}</span></div>
      @endif
      @if ($row['panelists'])
      <div class="detail-row"><span class="lbl">Panel</span><span class="val">{{ $row['panelists'] }}</span></div>
      @endif
    </div>
    @endforeach

    @if (!empty($criteriaRows))
    <div class="section-title">Qualification Standards Checked</div>
    <table class="crit-table">
      <thead>
        <tr><th>Criterion</th><th>Your Qualification</th><th>Result</th></tr>
      </thead>
      <tbody>
        @foreach ($criteriaRows as $row)
        <tr>
          <td>
            <strong>{{ $row['label'] }}</strong>
            @if (!empty($row['required']))
              <div style="color:#888; font-size:.75rem; margin-top:2px;">Required: {{ $row['required'] }}</div>
            @endif
          </td>
          <td>{{ $row['actual'] ?: '—' }}</td>
          <td><span class="badge {{ $row['passed'] ? 'pass' : 'fail' }}">{{ $row['passed'] ? 'Qualified' : 'Not qualified' }}</span></td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    <div class="btn-wrap">
      <a href="{{ url('/job-postings/' . $jobPosting->id) }}" class="btn">View Job Posting</a>
    </div>

    <p>Please arrive at least 15 minutes early to each schedule above and bring any required documents.</p>

    <div class="note">
      <strong>&#128204; Reminder:</strong><br>
      If you are unable to attend any of the schedules above, please contact the Human Resource Unit
      as soon as possible.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      For inquiries, please contact the Human Resource Unit at:<br>
      &#128205; Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite<br>
      &#128222; (046) 419-1286, 412-0349<br>
      &#127760; <a href="http://www.depedcavite.com.ph" style="color:#003087;">www.depedcavite.com.ph</a><br>
      &#9993;&#65039; deped.cavite@deped.gov.ph
    </p>
  </div>

  <div class="footer">
    DepEd Schools Division Office of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply directly to this message.
  </div>
</div>
</body>
</html>
BLADE;
    file_put_contents($qualifiedBundleViewPath, $qualifiedBundleView);
    echo "Created: $qualifiedBundleViewPath\n";
}

// ────────────────────────────────────────────────────────────────────
// 4. New Blade view: mail.panelist-schedule-bundle
// ────────────────────────────────────────────────────────────────────
$panelistBundleViewPath = $mailViewDir . '/panelist-schedule-bundle.blade.php';

if (file_exists($panelistBundleViewPath)) {
    fwrite(STDERR, "SKIP (already exists): $panelistBundleViewPath\n");
} else {
    $panelistBundleView = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#003087; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .sched-card { background:#fafbfc; border:1px solid #e3e8ec; border-radius:6px; padding:16px 20px; margin-bottom:14px; }
  .sched-card .type { font-weight:700; color:#003087; font-size:.92rem; margin-bottom:8px; }
  .detail-row { display:flex; padding:4px 0; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:110px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon">&#128203;</span>
    <h1>Schedule Assignment{{ count($rows) > 1 ? 's' : '' }}</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Region IV-A &bull; Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear Panelist,</p>
    <p>
      You have been assigned to the following recruitment schedule{{ count($rows) > 1 ? 's' : '' }}:
    </p>

    <div class="section-title">Your Assignments</div>

    @foreach ($rows as $row)
    <div class="sched-card">
      <div class="type">{{ $row['type_label'] }} &mdash; {{ $row['position'] }}</div>
      <div class="detail-row"><span class="lbl">Candidate</span><span class="val">{{ $row['candidate'] }}</span></div>
      <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $row['when'] }}</span></div>
      @if ($row['location'])
      <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $row['location'] }}</span></div>
      @endif
    </div>
    @endforeach

    <div class="note">
      <strong>&#128204; Note:</strong><br>
      Please confirm your availability with the Human Resource Unit as soon as possible if there
      is any scheduling conflict.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      For inquiries, please contact the Human Resource Unit at:<br>
      &#128205; Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite<br>
      &#128222; (046) 419-1286, 412-0349<br>
      &#127760; <a href="http://www.depedcavite.com.ph" style="color:#003087;">www.depedcavite.com.ph</a><br>
      &#9993;&#65039; deped.cavite@deped.gov.ph
    </p>
  </div>

  <div class="footer">
    DepEd Schools Division Office of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply directly to this message.
  </div>
</div>
</body>
</html>
BLADE;
    file_put_contents($panelistBundleViewPath, $panelistBundleView);
    echo "Created: $panelistBundleViewPath\n";
}

// ────────────────────────────────────────────────────────────────────
// 5. Patch InterviewScheduleController::storeForPosting()
// ────────────────────────────────────────────────────────────────────
$backup = $controllerFile . '.bak';
if (!copy($controllerFile, $backup)) {
    fwrite(STDERR, "Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($controllerFile);

$oldLoop = <<<'PHP'
        $created = 0;
        foreach ($applications as $application) {
            foreach ($validated['type'] as $type) {
                $schedule = InterviewSchedule::create([
                    'application_id' => $application->id,
                    'type'           => $type,
                    'scheduled_at'   => $validated['scheduled_at'],
                    'location'       => $validated['location'] ?? null,
                    'status'         => 'scheduled',
                ]);

                if (!empty($panelistIds)) {
                    $schedule->panelists()->sync($panelistIds);

                    // Email every panelist selected on the checklist (1-6).
                    // Skipped silently if a panelist has no email on file.
                    foreach ($schedule->panelists as $panelist) {
                        if (empty($panelist->email)) {
                            continue;
                        }
                        try {
                            \Illuminate\Support\Facades\Notification::route('mail', $panelist->email)
                                ->notify(new \App\Notifications\InterviewerInvitationNotification($schedule));
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to send panelist schedule invitation to {$panelist->email}: " . $e->getMessage());
                        }
                    }
                }

                // Send invitation to candidate (one per selected type)
                try {
                    $application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification($schedule));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . $e->getMessage());
                }

                $created++;
            }
        }
PHP;

$newLoop = <<<'PHP'
        $created = 0;
        $schedulesByApplication = [];     // application_id => ['application' => Application, 'schedules' => []]
        $assignmentsByPanelistEmail = []; // panelist email => array of assignment rows

        foreach ($applications as $application) {
            foreach ($validated['type'] as $type) {
                $schedule = InterviewSchedule::create([
                    'application_id' => $application->id,
                    'type'           => $type,
                    'scheduled_at'   => $validated['scheduled_at'],
                    'location'       => $validated['location'] ?? null,
                    'status'         => 'scheduled',
                ]);

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

                $created++;
            }
        }

        // One combined email per candidate listing every schedule type
        // just created for them (Open Ranking / Exam / Interview, etc.),
        // instead of one separate "You're Invited" email per type.
        foreach ($schedulesByApplication as $entry) {
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
PHP;

if (strpos($content, $oldLoop) === false) {
    fwrite(STDERR, "ABORT: storeForPosting() loop anchor not found. No changes written (backup already saved).\n");
    exit(1);
}
if (substr_count($content, $oldLoop) > 1) {
    fwrite(STDERR, "ABORT: anchor found more than once -- refusing to guess. No changes written.\n");
    exit(1);
}
$content = str_replace($oldLoop, $newLoop, $content);

file_put_contents($controllerFile, $content);

echo "Patched: $controllerFile\n";
echo "Backup saved at: $backup\n";
echo "\nDone. Restart your queue worker if one is running (php artisan queue:restart).\n";
