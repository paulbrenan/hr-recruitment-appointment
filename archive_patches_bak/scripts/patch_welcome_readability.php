<?php
/**
 * patch_welcome_readability.php
 *
 * Bumps up several small font sizes on the public welcome/landing page
 * (nav brand text, hero subtitle, track box, contact info, footer copy)
 * so the page reads more comfortably at normal viewing distance.
 *
 * Run: php patch_welcome_readability.php
 */

$file = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($file)) {
    fwrite(STDERR, "ERROR: File not found: $file\n");
    fwrite(STDERR, "Edit \$file at the top of this script to point to the correct path.\n");
    exit(1);
}

$backup = $file . '.bak';
if (!copy($file, $backup)) {
    fwrite(STDERR, "ERROR: Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($file);
$original = $content;

/**
 * Each replacement: [old, new, label]
 * Aborts the whole script if any anchor is not found (exact match required).
 */
$replacements = [
    // Top nav brand text
    [
        ".topnav-text .org { font-size: .68rem; font-weight: 700; color: rgba(255,255,255,.7); letter-spacing: .1em; text-transform: uppercase; }",
        ".topnav-text .org { font-size: .78rem; font-weight: 700; color: rgba(255,255,255,.7); letter-spacing: .1em; text-transform: uppercase; }",
        'topnav .org font-size',
    ],
    [
        ".topnav-text .sys { font-size: .92rem; font-weight: 800; color: #ffffff; line-height: 1.15; }",
        ".topnav-text .sys { font-size: 1.05rem; font-weight: 800; color: #ffffff; line-height: 1.15; }",
        'topnav .sys font-size',
    ],
    // Nav clock
    [
        ".topnav-clock .clock-date { font-size: .75rem; font-weight: 600; color: rgba(255,255,255,.65); }",
        ".topnav-clock .clock-date { font-size: .85rem; font-weight: 600; color: rgba(255,255,255,.65); }",
        'clock-date font-size',
    ],
    [
        ".topnav-clock .clock-time { font-size: .9rem; font-weight: 800; color: #fff; letter-spacing: .02em; font-variant-numeric: tabular-nums; }",
        ".topnav-clock .clock-time { font-size: 1.05rem; font-weight: 800; color: #fff; letter-spacing: .02em; font-variant-numeric: tabular-nums; }",
        'clock-time font-size',
    ],
    // Hero subtitle
    [
        "  .hero-sub {\n    font-size: 1rem; color: rgba(255,255,255,.8);\n    margin-bottom: 32px; font-weight: 500;\n  }",
        "  .hero-sub {\n    font-size: 1.15rem; color: rgba(255,255,255,.85);\n    margin-bottom: 32px; font-weight: 500;\n  }",
        'hero-sub font-size',
    ],
    // Track box heading/subtext/input/button
    [
        ".track-box h2 { font-size: 1rem; font-weight: 800; color: var(--dark); margin-bottom: 4px; }",
        ".track-box h2 { font-size: 1.15rem; font-weight: 800; color: var(--dark); margin-bottom: 4px; }",
        'track-box h2 font-size',
    ],
    [
        ".track-box > p { font-size: .82rem; color: var(--muted); margin-bottom: 18px; }",
        ".track-box > p { font-size: .95rem; color: var(--muted); margin-bottom: 18px; }",
        'track-box p font-size',
    ],
    [
        "    flex: 1; padding: 13px 16px;\n    border: 1.5px solid #c5d0e6; border-radius: 8px;\n    font-size: .92rem; font-family: 'Inter', sans-serif;",
        "    flex: 1; padding: 14px 16px;\n    border: 1.5px solid #c5d0e6; border-radius: 8px;\n    font-size: 1rem; font-family: 'Inter', sans-serif;",
        'track-input font-size',
    ],
    [
        "    padding: 13px 22px; border-radius: 8px;\n    background: var(--blue); color: #fff;\n    font-size: .88rem; font-weight: 800;",
        "    padding: 14px 24px; border-radius: 8px;\n    background: var(--blue); color: #fff;\n    font-size: .95rem; font-weight: 800;",
        'btn-track font-size',
    ],
    // Contact section
    [
        "  .contact-place { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 28px; letter-spacing: .01em; }",
        "  .contact-place { font-size: 1.2rem; font-weight: 800; color: #fff; margin-bottom: 28px; letter-spacing: .01em; }",
        'contact-place font-size',
    ],
    [
        "  .contact-item i { font-size: 1.6rem; color: #ffd700; margin-bottom: 8px; display: block; }",
        "  .contact-item i { font-size: 1.9rem; color: #ffd700; margin-bottom: 8px; display: block; }",
        'contact-item icon font-size',
    ],
    [
        "  .contact-item .contact-label { font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 6px; }",
        "  .contact-item .contact-label { font-size: .92rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 6px; }",
        'contact-label font-size',
    ],
    [
        "  .contact-item .contact-value { font-size: .8rem; color: rgba(255,255,255,.75); line-height: 1.5; }",
        "  .contact-item .contact-value { font-size: .95rem; color: rgba(255,255,255,.85); line-height: 1.6; }",
        'contact-value font-size',
    ],
    // Location block
    [
        "  .location-block i { font-size: 1.8rem; color: #ffd700; margin-bottom: 6px; display: block; }",
        "  .location-block i { font-size: 2.1rem; color: #ffd700; margin-bottom: 6px; display: block; }",
        'location-block icon font-size',
    ],
    [
        "  .location-label { font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 16px; }",
        "  .location-label { font-size: .92rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 16px; }",
        'location-label font-size',
    ],
    // Footer
    [
        "  .footer-copy {\n    font-size: .7rem; color: rgba(255,255,255,.65);\n    letter-spacing: .06em; text-transform: uppercase;\n  }",
        "  .footer-copy {\n    font-size: .82rem; color: rgba(255,255,255,.7);\n    letter-spacing: .06em; text-transform: uppercase;\n  }",
        'footer-copy font-size',
    ],
];

$missing = [];
foreach ($replacements as [$old, $new, $label]) {
    if (strpos($content, $old) === false) {
        $missing[] = $label;
        continue;
    }
    $count = 0;
    $content = str_replace($old, $new, $content, $count);
    if ($count !== 1) {
        $missing[] = "$label (matched $count times, expected 1)";
    }
}

if (!empty($missing)) {
    fwrite(STDERR, "ABORTED: the following anchors were not found or matched incorrectly:\n");
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
echo "Applied " . count($replacements) . " font-size adjustments.\n";
