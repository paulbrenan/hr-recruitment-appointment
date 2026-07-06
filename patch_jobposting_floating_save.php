<?php

/**
 * patch_jobposting_floating_save.php
 *
 * WHAT THIS DOES:
 *   Adds a sticky floating save bar to the job posting create/edit form so
 *   HR doesn't have to scroll to the bottom to save.
 *
 *   - A fixed bar appears at the bottom of the viewport with "Save posting"
 *     and "Cancel" — always visible while scrolling
 *   - The original inline Save/Cancel buttons at the bottom of the form are
 *     kept so the form still works naturally; the floating bar just submits
 *     the same form via JS
 *   - The bar is slightly transparent/blurred so it feels native, not jarring
 *   - On very short pages (no scroll) the bar still shows but doesn't overlap
 *     anything important
 *
 * HOW TO RUN:
 *   php patch_jobposting_floating_save.php    (from project root)
 *   No migration needed.
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_jobposting_floating_save.php ===\n\n";

$bladePath = ROOT . '/resources/views/job-postings/form.blade.php';

// 1. Give the form an ID so the floating bar can target it
apply_patch(
    $bladePath,
    '<form action="{{ ($posting->exists ?? false) ? route(\'job-postings.update\', $posting->id) : route(\'job-postings.store\') }}" method="POST">',
    '<form id="postingForm" action="{{ ($posting->exists ?? false) ? route(\'job-postings.update\', $posting->id) : route(\'job-postings.store\') }}" method="POST">',
    'form: add id="postingForm"'
);

// 2. Add floating bar + bottom padding right before @push('scripts')
apply_patch(
    $bladePath,
    '@push(\'scripts\')
<script>',
    '{{-- ── Floating save bar ──────────────────────────────────────────────── --}}
<div id="floatingSaveBar" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border-top: 1px solid #dee2e6;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
">
    <span class="small text-muted">
        @if ($posting->exists ?? false)
            Editing: <strong>{{ $posting->title ?? \'Job posting\' }}</strong>
        @else
            New job posting
        @endif
    </span>
    <div class="d-flex gap-2">
        <a href="{{ route(\'job-postings.index\') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button type="button" id="floatingSaveBtn" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-floppy me-1"></i> Save posting
        </button>
    </div>
</div>
{{-- Push page content up so the floating bar doesn\'t cover the bottom buttons --}}
<div style="height: 64px;"></div>

@push(\'scripts\')
<script>',
    'blade: floating save bar + spacer'
);

// 3. Wire the floating save button to submit the form
apply_patch(
    $bladePath,
    '    (function () {
        const titles = @json($jobTitles ?? []);',
    '    // Floating save bar → submit the posting form
    document.getElementById(\'floatingSaveBtn\').addEventListener(\'click\', function () {
        document.getElementById(\'postingForm\').requestSubmit();
    });

    (function () {
        const titles = @json($jobTitles ?? []);',
    'JS: floating save button submits form'
);

echo <<<TEXT

✅ Done. Hard-refresh the page (Ctrl+Shift+R).

HOW IT WORKS:
  - A fixed bar is pinned to the bottom of the viewport on the form page.
  - Left side shows the posting title (or "New job posting").
  - Right side has Cancel (links back to index) + Save posting (submits the form).
  - Uses requestSubmit() so HTML5 validation still fires before submit.
  - A 64px spacer div pushes the page content up so the original inline
    Save/Cancel buttons at the bottom aren't hidden behind the bar.
  - The bar has a frosted-glass backdrop so it doesn't feel heavy.

DELETE this script after running.

TEXT;
