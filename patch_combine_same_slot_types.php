<?php
/**
 * Patch: when a candidate's schedule types for a panelist all share the
 * same date/time and location (the normal case -- created together in
 * one batch), collapse them into ONE line listing every applicable
 * type, instead of repeating the same date/time/location for each type.
 *
 * BEFORE (per candidate box):
 *   Type: Open Ranking Session   Date & Time: Fri Jul 31, 9:45 AM   Location: Trece
 *   Type: Interview               Date & Time: Fri Jul 31, 9:45 AM   Location: Trece
 *   Type: Examination             Date & Time: Fri Jul 31, 9:45 AM   Location: Trece
 *
 * AFTER (per candidate box):
 *   Type: Open Ranking Session, Interview, Examination
 *   Date & Time: Friday, July 31, 2026 at 9:45 AM
 *   Location: Trece
 *
 * If a candidate's types ever DO have different dates/locations (edge
 * case), the per-type breakdown is kept for that candidate only.
 *
 * Requires patch_group_panelist_bundle_by_candidate.php to have already
 * been run (this patches the grouped version it created).
 *
 * Run from your project root: php patch_combine_same_slot_types.php
 */

$root = __DIR__;

$notifFile = $root . '/app/Notifications/PanelistScheduleBundleNotification.php';
$viewFile  = $root . '/resources/views/mail/panelist-schedule-bundle.blade.php';

foreach ([$notifFile, $viewFile] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "ABORT: required file not found: $f\n");
        exit(1);
    }
}

// ────────────────────────────────────────────────────────────────────
// 1. Patch the notification's grouping to collapse same-slot types
// ────────────────────────────────────────────────────────────────────
$notifBackup = $notifFile . '.bak2';
if (!copy($notifFile, $notifBackup)) {
    fwrite(STDERR, "Failed to create backup at $notifBackup\n");
    exit(1);
}

$notifContent = file_get_contents($notifFile);

$oldGroupMap = <<<'PHP'
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
PHP;

$newGroupMap = <<<'PHP'
        $groups = $this->assignments
            ->sortBy(fn ($a) => $a['schedule']->scheduled_at)
            ->groupBy(fn ($a) => $a['schedule']->application_id)
            ->map(function ($assignments) {
                $first = $assignments->first();

                $items = $assignments
                    ->map(function ($a) {
                        return [
                            'type_label' => $this->typeLabel($a['schedule']->type),
                            'when'       => $a['schedule']->scheduled_at->format('l, F j, Y \a\t g:i A'),
                            'location'   => $a['schedule']->location,
                        ];
                    })
                    ->values();

                // If every schedule type for this candidate shares the
                // same date/time and location (the normal case -- all
                // created together in one batch), collapse into a
                // single line listing every applicable type instead of
                // repeating the same date/time/location per type.
                $uniqueSlots = $items->map(fn ($i) => $i['when'] . '|' . $i['location'])->unique();

                if ($uniqueSlots->count() === 1) {
                    return [
                        'candidate'   => $first['candidate']->full_name,
                        'position'    => $first['jobPosting']->title,
                        'combined'    => true,
                        'type_labels' => $items->pluck('type_label')->implode(', '),
                        'when'        => $items->first()['when'],
                        'location'    => $items->first()['location'],
                    ];
                }

                return [
                    'candidate' => $first['candidate']->full_name,
                    'position'  => $first['jobPosting']->title,
                    'combined'  => false,
                    'items'     => $items->all(),
                ];
            })
            ->values()
            ->all();
PHP;

if (strpos($notifContent, $oldGroupMap) === false) {
    fwrite(STDERR, "ABORT: grouping anchor not found in $notifFile.\n");
    fwrite(STDERR, "This patch expects patch_group_panelist_bundle_by_candidate.php to have run first. No changes written.\n");
    exit(1);
}
$notifContent = str_replace($oldGroupMap, $newGroupMap, $notifContent);
file_put_contents($notifFile, $notifContent);
echo "Patched: $notifFile\n";

// ────────────────────────────────────────────────────────────────────
// 2. Patch the Blade view to render the combined vs per-type cases
// ────────────────────────────────────────────────────────────────────
$viewBackup = $viewFile . '.bak2';
if (!copy($viewFile, $viewBackup)) {
    fwrite(STDERR, "Failed to create backup at $viewBackup\n");
    exit(1);
}

$viewContent = file_get_contents($viewFile);

$oldLoop = <<<'BLADE'
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

$newLoop = <<<'BLADE'
    @foreach ($groups as $group)
    <div class="sched-card">
      <div class="type">{{ $group['candidate'] }} &mdash; {{ $group['position'] }}</div>
      @if ($group['combined'])
      <div class="detail-row"><span class="lbl">Type</span><span class="val">{{ $group['type_labels'] }}</span></div>
      <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $group['when'] }}</span></div>
      @if ($group['location'])
      <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $group['location'] }}</span></div>
      @endif
      @else
      @foreach ($group['items'] as $i => $item)
      <div class="assignment-item @if ($i > 0) assignment-item-sep @endif">
        <div class="detail-row"><span class="lbl">Type</span><span class="val">{{ $item['type_label'] }}</span></div>
        <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $item['when'] }}</span></div>
        @if ($item['location'])
        <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $item['location'] }}</span></div>
        @endif
      </div>
      @endforeach
      @endif
    </div>
    @endforeach
BLADE;

if (strpos($viewContent, $oldLoop) === false) {
    fwrite(STDERR, "ABORT: view loop anchor not found in $viewFile.\n");
    fwrite(STDERR, "This patch expects patch_group_panelist_bundle_by_candidate.php to have run first. No changes written.\n");
    exit(1);
}
$viewContent = str_replace($oldLoop, $newLoop, $viewContent);
file_put_contents($viewFile, $viewContent);
echo "Patched: $viewFile\n";

echo "\nBackups saved at:\n  $notifBackup\n  $viewBackup\n";
