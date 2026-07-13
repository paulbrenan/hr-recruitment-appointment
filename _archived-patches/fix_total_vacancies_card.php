<?php

/**
 * fix_total_vacancies_card.php
 * Fixes the mangled arrow function on the Total vacancies card in index.blade.php
 */

define('ROOT', __DIR__);

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

if (!file_exists($indexPath)) {
    echo "❌ File not found: $indexPath\n";
    exit(1);
}

$content = file_get_contents($indexPath);

// Find and replace the broken line regardless of exact mangling
$fixed = preg_replace(
    '/\{\{.*?fn\(\).*?sum.*?vacancies.*?\}\}/',
    '{{ $postings->sum(fn($p) => $p->locations->sum(\'vacancies\') ?: $p->vacancies) }}',
    $content
);

if ($fixed === $content) {
    echo "❌ Could not find the broken line. Please paste the exact content of line 58.\n";
    exit(1);
}

// Backup
$bak = $indexPath . '.bak';
$i = 2;
while (file_exists($bak)) { $bak = $indexPath . '.bak' . $i++; }
copy($indexPath, $bak);
echo "  [bak] $bak\n";

file_put_contents($indexPath, $fixed);
echo "  [ok ] Total vacancies card fixed.\n\nDone. Delete this script.\n";
