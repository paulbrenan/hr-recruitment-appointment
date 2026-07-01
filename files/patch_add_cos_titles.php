<?php
/**
 * patch_add_cos_titles.php
 *
 * Adds two new titles to config/job_titles.php:
 *   - Technical Assistant I
 *   - School Sports Program Focal Person (Contract of Service)
 *
 * Drop in project root, run once: php patch_add_cos_titles.php
 * Delete after confirming the titles appear in the dropdown.
 * No migration needed.
 */

function do_backup(string $path): void {
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

$configPath = __DIR__ . '/config/job_titles.php';
if (!file_exists($configPath)) { die("ERROR: Cannot find config/job_titles.php\n"); }

$config = require $configPath;
$titles = $config['titles'] ?? [];

$toAdd = [
    'Technical Assistant I',
    'School Sports Program Focal Person (Contract of Service)',
];

$added = [];
foreach ($toAdd as $title) {
    if (!in_array($title, $titles, true)) {
        $titles[] = $title;
        $added[] = $title;
    } else {
        echo "  Already exists: $title\n";
    }
}

if (empty($added)) {
    echo "  No changes needed — both titles already in list.\n";
    exit(0);
}

sort($titles, SORT_STRING);

do_backup($configPath);

// Rebuild file preserving the same format and header comments
$lines = array_map(fn($t) => "        " . var_export($t, true) . ",", $titles);

$body = "<?php\n\n"
    . "// config/job_titles.php\n"
    . "// Canonical list of position titles for job postings, sourced from the\n"
    . "// official DepEd \"Position Applying For\" list. Used to populate the\n"
    . "// searchable title dropdown on the Job Posting create/edit form.\n"
    . "//\n"
    . "// Entries can be added automatically by JobTitleRegistrar when the PDF\n"
    . "// import detects a genuine new title variant (e.g. a Secondary/Elementary\n"
    . "// prefixed grade) that doesn't yet exist in this list.\n\n"
    . "return [\n"
    . "    'titles' => [\n"
    . implode("\n", $lines) . "\n"
    . "    ],\n"
    . "];\n";

file_put_contents($configPath, $body);

echo "\n✓ Added " . count($added) . " title(s) to config/job_titles.php:\n";
foreach ($added as $t) { echo "    + $t\n"; }
echo "\nTotal titles now: " . count($titles) . "\n";
echo "Delete this script when confirmed working.\n";
