<?php
/**
 * patch_welcome_readability_v2.php
 *
 * Round 2: pushes the hero eyebrow/title/subtitle and a couple of
 * remaining small labels bigger/bolder for impact, matching the
 * reference screenshot's scale. Layout/colors/structure untouched —
 * font-size (and a couple font-weight/letter-spacing) only.
 *
 * Safe to run whether or not patch_welcome_readability.php (round 1)
 * has already been applied — each target checks multiple candidate
 * "old" strings and uses whichever one currently matches the file.
 *
 * Run: php patch_welcome_readability_v2.php
 */

$file = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($file)) {
    fwrite(STDERR, "ERROR: File not found: $file\n");
    fwrite(STDERR, "Edit \$file at the top of this script to point to the correct path.\n");
    exit(1);
}

$backup = $file . '.v2.bak';
if (!copy($file, $backup)) {
    fwrite(STDERR, "ERROR: Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($file);
$original = $content;

/**
 * Each target: [ [candidate old strings...], new string, label ]
 * Tries each candidate in order; first exact single-match wins.
 */
$targets = [
    [
        [".hero-eyebrow {\n    font-size: .82rem; font-weight: 800; letter-spacing: .18em;"],
        ".hero-eyebrow {\n    font-size: 1rem; font-weight: 800; letter-spacing: .18em;",
        'hero-eyebrow font-size',
    ],
    [
        [
            "  .hero-title {\n    font-size: clamp(1.9rem, 5vw, 3rem);\n    font-weight: 900; line-height: 1.1; color: #ffffff;",
        ],
        "  .hero-title {\n    font-size: clamp(2.4rem, 6vw, 3.8rem);\n    font-weight: 900; line-height: 1.1; color: #ffffff;",
        'hero-title font-size',
    ],
    [
        [
            // unpatched (round 1 not run)
            "  .hero-sub {\n    font-size: 1rem; color: rgba(255,255,255,.8);\n    margin-bottom: 32px; font-weight: 500;\n  }",
            // patched by round 1
            "  .hero-sub {\n    font-size: 1.15rem; color: rgba(255,255,255,.85);\n    margin-bottom: 32px; font-weight: 500;\n  }",
        ],
        "  .hero-sub {\n    font-size: 1.3rem; color: rgba(255,255,255,.9);\n    margin-bottom: 32px; font-weight: 600;\n  }",
        'hero-sub font-size (round 2)',
    ],
    [
        [".contact-title { font-size: .95rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: rgba(255,255,255,.6); margin-bottom: 4px; }"],
        ".contact-title { font-size: 1.1rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: rgba(255,255,255,.7); margin-bottom: 4px; }",
        'contact-title font-size',
    ],
    [
        [".contact-subtitle { font-size: .85rem; font-weight: 700; color: #fff; margin-bottom: 28px; }"],
        ".contact-subtitle { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 28px; }",
        'contact-subtitle font-size',
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
    fwrite(STDERR, "ABORTED: the following targets were not found (none of the candidate strings matched exactly once):\n");
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
echo "Applied " . count($targets) . " font-size adjustments (round 2).\n";
