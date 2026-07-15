<?php
/**
 * patch_welcome_readability_v3.php
 *
 * Round 3: the "Track Your Application" white card is small relative
 * to the page. Widens it and gives it more breathing room (padding),
 * plus slightly bigger corner radius to match the new scale. Pure
 * size/spacing change — no layout/structure/color change.
 *
 * Run: php patch_welcome_readability_v3.php
 */

$file = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($file)) {
    fwrite(STDERR, "ERROR: File not found: $file\n");
    fwrite(STDERR, "Edit \$file at the top of this script to point to the correct path.\n");
    exit(1);
}

$backup = $file . '.v3.bak';
if (!copy($file, $backup)) {
    fwrite(STDERR, "ERROR: Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($file);
$original = $content;

$targets = [
    [
        [
            "  .track-box {\n    background: rgba(255,255,255,.97);\n    border: none;\n    border-radius: 16px;\n    padding: 32px 36px;\n    max-width: 580px; width: 100%;\n    box-shadow: 0 12px 48px rgba(0,0,0,.3);\n    margin-bottom: 28px;\n  }",
        ],
        "  .track-box {\n    background: rgba(255,255,255,.97);\n    border: none;\n    border-radius: 18px;\n    padding: 44px 52px;\n    max-width: 700px; width: 100%;\n    box-shadow: 0 12px 48px rgba(0,0,0,.3);\n    margin-bottom: 28px;\n  }",
        'track-box size (padding/max-width)',
    ],
];

$missing = [];
foreach ($targets as [$candidates, $new, $label]) {
    $matched = false;
    foreach ($candidates as $old) {
        $count = substr_count($content, $old);
        if ($count === 1) {
            $content = str_replace($old, $new, $content);
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $missing[] = $label;
    }
}

if (!empty($missing)) {
    fwrite(STDERR, "ABORTED: the following targets were not found (candidate string didn't match exactly once):\n");
    foreach ($missing as $m) {
        fwrite(STDERR, "  - $m\n");
    }
    fwrite(STDERR, "No changes written. Backup at $backup is unchanged (identical to original).\n");
    exit(1);
}

if ($content === $original) {
    fwrite(STDERR, "No changes were necessary (content already matches target).\n");
    exit(0);
}

file_put_contents($file, $content);

echo "Patched successfully: $file\n";
echo "Backup saved at: $backup\n";
echo "Track box is now wider (700px) with more padding.\n";
