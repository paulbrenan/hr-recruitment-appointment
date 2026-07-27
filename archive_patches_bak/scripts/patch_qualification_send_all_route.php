<?php

/**
 * patch_qualification_send_all_route.php
 *
 * WHAT THIS DOES:
 *   Adds the missing route for the bulk "Send all qualified/disqualified
 *   mail" buttons added by patch_qualification_send_all.php:
 *
 *     POST /job-postings/{id}/qualification-notices/send-all
 *     -> ApplicationController::sendAllQualificationNotices
 *     name: applications.qualification-notices.send-all
 *
 *   Inserted directly after the existing
 *   'applications.qualification-notice' route line.
 *
 * HOW TO RUN:
 *   php patch_qualification_send_all_route.php    (from project root)
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

echo "\n=== patch_qualification_send_all_route.php ===\n\n";

$routesPath = ROOT . '/routes/web.php';

apply_patch(
    $routesPath,
    "Route::post('/applications/{id}/qualification-notice', [ApplicationController::class, 'sendQualificationNotice'])->name('applications.qualification-notice');",
    "Route::post('/applications/{id}/qualification-notice', [ApplicationController::class, 'sendQualificationNotice'])->name('applications.qualification-notice');\nRoute::post('/job-postings/{id}/qualification-notices/send-all', [ApplicationController::class, 'sendAllQualificationNotices'])->name('applications.qualification-notices.send-all');",
    "web.php: add applications.qualification-notices.send-all route"
);

echo <<<TEXT

✅ Done.

Route added:
  POST /job-postings/{id}/qualification-notices/send-all
  -> ApplicationController@sendAllQualificationNotices
  name: applications.qualification-notices.send-all

Run:
  php artisan route:list --name=qualification
to confirm it registered, then hard-refresh the job posting page.

DELETE this script after running.

TEXT;
