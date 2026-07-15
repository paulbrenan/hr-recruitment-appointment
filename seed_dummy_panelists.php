<?php
/**
 * seed_dummy_panelists.php
 *
 * Creates dummy panelists (with emails) for testing the scheduling email
 * flow, and attaches them to a job posting so they show up in the
 * scheduling modal's panelist checklist.
 *
 * REQUIRES:
 *   - 2026_07_15_010000_add_email_to_panelists.php already migrated
 *   - fix_panelist_email_notifications.php already applied
 *
 * USAGE:
 *   php seed_dummy_panelists.php <job_posting_id> [count]
 *
 * Examples:
 *   php seed_dummy_panelists.php 4        -> creates 3 panelists (default), attaches to posting 4
 *   php seed_dummy_panelists.php 4 6      -> creates 6 panelists, attaches to posting 4
 *
 * Emails are dummy@ addresses -- point your MAIL_* .env config at
 * Mailtrap (or whatever sandbox you're already using) before running a
 * real schedule so nothing goes out to a live inbox.
 *
 * This script is idempotent-ish: re-running with the same posting ID
 * just attaches the newly created panelists too (won't duplicate
 * panelists already attached from a prior run, since each run creates
 * fresh names).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\JobPosting;
use App\Models\Panelist;

$postingId = $argv[1] ?? null;
$count     = isset($argv[2]) ? (int) $argv[2] : 3;

if (!$postingId) {
    echo "❌ Usage: php seed_dummy_panelists.php <job_posting_id> [count=3]\n";
    echo "   Run 'php artisan tinker --execute=\"JobPosting::pluck('title','id')\"' to see posting IDs.\n";
    exit(1);
}

$count = max(1, min(6, $count));

$posting = JobPosting::find($postingId);

if (!$posting) {
    echo "❌ Job posting #{$postingId} not found.\n";
    exit(1);
}

$pool = [
    ['name' => 'Dr. Ramon Santos',      'email' => 'ramon.santos.panelist@example.com'],
    ['name' => 'Ms. Corazon Villareal', 'email' => 'corazon.villareal.panelist@example.com'],
    ['name' => 'Mr. Ferdinand Cruz',    'email' => 'ferdinand.cruz.panelist@example.com'],
    ['name' => 'Dr. Angelica Reyes',    'email' => 'angelica.reyes.panelist@example.com'],
    ['name' => 'Mr. Bienvenido Torres', 'email' => 'bienvenido.torres.panelist@example.com'],
    ['name' => 'Ms. Leonora Dimaano',   'email' => 'leonora.dimaano.panelist@example.com'],
];

$selected = array_slice($pool, 0, $count);

echo "\nCreating {$count} dummy panelist(s) and attaching to posting #{$postingId} ({$posting->title})...\n\n";

$ids = [];
foreach ($selected as $data) {
    $panelist = Panelist::create($data);
    $ids[] = $panelist->id;
    echo "  [+] {$panelist->name} <{$panelist->email}> (id {$panelist->id})\n";
}

$posting->panelists()->syncWithoutDetaching(
    array_fill_keys($ids, ['is_available' => true])
);

echo "\n✅ Done. Attached to posting #{$postingId}.\n\n";
echo "NEXT STEPS TO TEST:\n";
echo "  1. Open the pipeline for posting #{$postingId} -> Open Ranking & Scheduling.\n";
echo "  2. Create a new schedule, check some/all of these panelists in the checklist.\n";
echo "  3. Submit -- each checked panelist with an email should now receive an\n";
echo "     invitation email (check your Mailtrap inbox / whatever MAIL_MAILER\n";
echo "     you have configured in .env).\n\n";
echo "DELETE this script after use (it's a dev/testing tool, not app code).\n";
