<?php

/**
 * fix_expander_ternary_syntax.php
 * Fixes the unparenthesized ternary on line 70 of PositionBlockExpander.php
 */

define('ROOT', __DIR__);

$path = ROOT . '/app/Services/PositionBlockExpander.php';
if (!file_exists($path)) { echo "❌ File not found.\n"; exit(1); }

$content = file_get_contents($path);

$old = "'place_of_assignment_parsed' => \$isUnrecoverable ? null : (\$schoolRow['school'] ?? null),";
$new = "'place_of_assignment_parsed' => (\$isUnrecoverable ? null : (\$schoolRow['school'] ?? null)),";

$count = substr_count($content, $old);
if ($count === 0) {
    // Try without trailing comma
    $old = "'place_of_assignment_parsed' => \$isUnrecoverable ? null : (\$schoolRow['school'] ?? null)";
    $new = "'place_of_assignment_parsed' => (\$isUnrecoverable ? null : (\$schoolRow['school'] ?? null))";
    $count = substr_count($content, $old);
}

if ($count === 0) {
    echo "❌ Could not find the line. Check line 70 of PositionBlockExpander.php manually.\n";
    echo "   It should read:\n";
    echo "   'place_of_assignment_parsed' => \$isUnrecoverable ? null : (\$schoolRow['school'] ?? null)\n";
    echo "   Change it to:\n";
    echo "   'place_of_assignment_parsed' => (\$isUnrecoverable ? null : (\$schoolRow['school'] ?? null))\n";
    exit(1);
}

$bak = $path . '.bak';
$i = 2;
while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
copy($path, $bak);

file_put_contents($path, str_replace($old, $new, $content));
echo "  [ok ] Ternary parenthesized — syntax error fixed.\n";
echo "\nNow restart queue worker and re-upload the PDF:\n";
echo "  php artisan queue:work\n";
echo "\nDelete this script after running.\n";
