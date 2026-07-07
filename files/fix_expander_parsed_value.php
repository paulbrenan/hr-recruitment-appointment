<?php

/**
 * fix_expander_parsed_value.php
 *
 * The previous patch set 'place_of_assignment_parsed' => $isUnrecoverable
 * (a boolean) instead of the actual school name. This fixes it.
 */

define('ROOT', __DIR__);

$path = ROOT . '/app/Services/PositionBlockExpander.php';

if (!file_exists($path)) {
    echo "❌ File not found: $path\n";
    exit(1);
}

$content = file_get_contents($path);

// Fix the wrong value
$old = "'place_of_assignment_parsed' => \$isUnrecoverable";
$new = "'place_of_assignment_parsed' => \$isUnrecoverable ? null : (\$schoolRow['school'] ?? null)";

$count = substr_count($content, $old);
if ($count === 0) {
    echo "❌ Could not find the broken line. Paste current content of PositionBlockExpander.php.\n";
    exit(1);
}

$bak = $path . '.bak';
$i = 2;
while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
copy($path, $bak);
echo "  [bak] $bak\n";

file_put_contents($path, str_replace($old, $new, $content));
echo "  [ok ] Fixed: place_of_assignment_parsed now returns actual school name\n";
echo "\nNow re-upload the PDF through the import form to reprocess it.\nThe location rows will pre-fill with school names on the review screen.\n\nDelete this script after running.\n";
