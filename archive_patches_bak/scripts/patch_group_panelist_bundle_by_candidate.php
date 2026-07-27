<?php
/**
 * Patch: group the panelist schedule-bundle email by CANDIDATE instead
 * of by schedule type.
 *
 * BEFORE: one box per (candidate, type) pair -- a panelist assigned to
 * a candidate for Open Ranking + Interview + Exam saw that candidate's
 * name repeated in 3 separate boxes.
 *
 * AFTER: one box per candidate, with each schedule type/date/location
 * listed as a sub-row inside that single box.
 *
 * Run from your project root: php patch_group_panelist_bundle_by_candidate.php
 */

$root = __DIR__;

$notifFile = $root . '/app/Notifications/PanelistScheduleBundleNotification.php';
$viewFile  = $root . '/resources/views/mail/panelist-schedule-bundle.blade.php';

foreach ([$notifFile, $viewFile] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "ABORT: required file not found: $f\n");
        fwrite(STDERR, "Edit the path variables at the top of this script if your project layout differs.\n");
        exit(1);
    }
}

// ────────────────────────────────────────────────────────────────────
// 1. Patch the notification's toMail() to group by application_id
// ────────────────────────────────────────────────────────────────────
$notifBackup = $notifFile . '.bak';
if (!copy($notifFile, $notifBackup)) {
    fwrite(STDERR, "Failed to create backup at $notifBackup\n");
    exit(1);
}

$notifContent = file_get_contents($notifFile);

$oldToMail = <<<'PHP'
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
PHP;

$newToMail = <<<'PHP'
    public function toMail($notifiable): MailMessage
    {
        // Group by candidate (application_id) so a panelist assigned to
        // the same candidate for multiple schedule types (Open Ranking +
        // Interview + Exam) sees ONE box for that candidate, with each
        // type listed as a sub-row -- instead of one box per type.
        $groups = $this->assignments
            ->sortBy(fn ($a) => $a['schedule']->scheduled_at)
            ->groupBy(fn ($a) => $a['schedule']->application_id)
            ->map(function ($assignments) {
                $first = $assignments->first();
                return [
                    'candidate' => $first['candidate']->full_name,
                    'position'  => $first['jobPosting']->title,
                    'items'     => $assignments
                        ->map(function ($a) {
                            return [
                                'type_label' => $this->typeLabel($a['schedule']->type),
                                'when'       => $a['schedule']->scheduled_at->format('l, F j, Y \a\t g:i A'),
                                'location'   => $a['schedule']->location,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $itemCount = $this->assignments->count();
        $candidateCount = count($groups);

        $subject = $candidateCount === 1
            ? "Schedule Assignment: {$groups[0]['candidate']} — {$groups[0]['position']}"
            : "Schedule Assignments ({$candidateCount} candidates) — DepEd Cavite Recruitment";

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.panelist-schedule-bundle', [
                'groups'     => $groups,
                'itemCount'  => $itemCount,
            ]);
    }
PHP;

if (strpos($notifContent, $oldToMail) === false) {
    fwrite(STDERR, "ABORT: toMail() anchor not found in $notifFile. No changes written.\n");
    exit(1);
}
$notifContent = str_replace($oldToMail, $newToMail, $notifContent);
file_put_contents($notifFile, $notifContent);
echo "Patched: $notifFile\n";

// ────────────────────────────────────────────────────────────────────
// 2. Patch the Blade view to render grouped-by-candidate cards
// ────────────────────────────────────────────────────────────────────
$viewBackup = $viewFile . '.bak';
if (!copy($viewFile, $viewBackup)) {
    fwrite(STDERR, "Failed to create backup at $viewBackup\n");
    exit(1);
}

$viewContent = file_get_contents($viewFile);

$oldHeader = <<<'BLADE'
    <span class="check-icon">&#128203;</span>
    <h1>Schedule Assignment{{ count($rows) > 1 ? 's' : '' }}</h1>
BLADE;

$newHeader = <<<'BLADE'
    <span class="check-icon">&#128203;</span>
    <h1>Schedule Assignment{{ $itemCount > 1 ? 's' : '' }}</h1>
BLADE;

$oldIntro = <<<'BLADE'
    <p>
      You have been assigned to the following recruitment schedule{{ count($rows) > 1 ? 's' : '' }}:
    </p>
BLADE;

$newIntro = <<<'BLADE'
    <p>
      You have been assigned to the following recruitment schedule{{ $itemCount > 1 ? 's' : '' }}:
    </p>
BLADE;

$oldLoop = <<<'BLADE'
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
BLADE;

$newLoop = <<<'BLADE'
    @foreach ($groups as $group)
    <div class="sched-card">
      <div class="type">{{ $group['candidate'] }} &mdash; {{ $group['position'] }}</div>
      @foreach ($group['items'] as $i => $item)
      <div class="assignment-item @if ($i > 0) assignment-item-sep @endif">
        <div class="detail-row"><span class="lbl">Type</span><span class="val">{{ $item['type_label'] }}</span></div>
        <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $item['when'] }}</span></div>
        @if ($item['location'])
        <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $item['location'] }}</span></div>
        @endif
      </div>
      @endforeach
    </div>
    @endforeach
BLADE;

$oldCss = <<<'CSS'
  .detail-row .val { font-weight:500; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
CSS;

$newCss = <<<'CSS'
  .detail-row .val { font-weight:500; }
  .assignment-item { padding:6px 0; }
  .assignment-item-sep { border-top:1px dashed #e3e8ec; margin-top:6px; padding-top:10px; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
CSS;

foreach ([[$oldHeader, $newHeader, 'header'], [$oldIntro, $newIntro, 'intro'], [$oldLoop, $newLoop, 'loop'], [$oldCss, $newCss, 'css']] as [$old, $new, $label]) {
    if (strpos($viewContent, $old) === false) {
        fwrite(STDERR, "ABORT: '$label' anchor not found in $viewFile. Restoring from backup, no changes written.\n");
        copy($viewBackup, $viewFile);
        exit(1);
    }
}

$viewContent = str_replace($oldHeader, $newHeader, $viewContent);
$viewContent = str_replace($oldIntro, $newIntro, $viewContent);
$viewContent = str_replace($oldLoop, $newLoop, $viewContent);
$viewContent = str_replace($oldCss, $newCss, $viewContent);

file_put_contents($viewFile, $viewContent);
echo "Patched: $viewFile\n";

echo "\nBackups saved at:\n  $notifBackup\n  $viewBackup\n";
