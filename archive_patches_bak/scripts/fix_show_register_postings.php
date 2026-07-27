<?php

/**
 * fix_show_register_postings.php
 * Passes $openPostings to the portal register view.
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
    if ($count === 0) { echo "\n❌ Pattern not found in $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ Pattern found $count times in $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_show_register_postings.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/CandidateAuthController.php';

// Add JobPosting use statement
apply_patch(
    $controllerPath,
    "use App\Models\Application;\nuse App\Models\Candidate;",
    "use App\Models\Application;\nuse App\Models\Candidate;\nuse App\Models\JobPosting;\nuse App\Models\JobPostingLocation;",
    'CandidateAuthController: add JobPosting + JobPostingLocation use'
);

// Fix showRegister() to pass open postings with their locations
$oldShow = <<<'PHP'
    public function showRegister()
    {
        return view('portal.register');
    }
PHP;

$newShow = <<<'PHP'
    public function showRegister()
    {
        $openPostings = JobPosting::with('locations')
            ->where('status', 'open')
            ->orderBy('title')
            ->get();

        return view('portal.register', compact('openPostings'));
    }
PHP;

apply_patch($controllerPath, $oldShow, $newShow, 'CandidateAuthController: showRegister() passes $openPostings');

echo "\nDone. Delete this script.\n";
