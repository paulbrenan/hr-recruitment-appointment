<?php
// patch_welcome.php — run once from project root, then delete
// Usage: php patch_welcome.php

$target = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($target)) {
    die("❌  File not found: $target\n");
}

function apply_patch(string $file, string $old, string $new, string $label): void {
    $src = file_get_contents($file);
    $count = substr_count($src, $old);
    if ($count === 0) die("❌  ABORT [{$label}]: old content not found. No changes written.\n");
    if ($count  > 1) die("❌  ABORT [{$label}]: old content matched {$count} times (expected 1). No changes written.\n");

    // Backup
    $bak = $file . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $file . '.bak' . $i++; }
    file_put_contents($bak, $src);

    file_put_contents($file, str_replace($old, $new, $src));
    echo "✅  [{$label}] patched. Backup → $bak\n";
}

// ── 1. Remove red border-bottom from .topnav ─────────────────────────────────
apply_patch($target,
    '    border-bottom: 2px solid var(--red);',
    '    border-bottom: none;',
    'Remove red nav border'
);

// ── 2. Hero eyebrow → "Department of Education" styled like reference image ──
apply_patch($target,
    '  .hero-eyebrow {
    font-size: .72rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: #fff;
    background: rgba(206,17,38,.85); padding: 5px 16px;
    border-radius: 20px; margin-bottom: 18px; display: inline-block;
  }',
    '  .hero-eyebrow {
    font-size: .82rem; font-weight: 800; letter-spacing: .18em;
    text-transform: uppercase; color: #ffd700;
    background: transparent; padding: 0;
    border-radius: 0; margin-bottom: 14px; display: block;
    text-shadow: 0 1px 6px rgba(0,0,0,.4);
  }',
    'Hero eyebrow style'
);

// ── 3. Eyebrow text → Department of Education ────────────────────────────────
apply_patch($target,
    '  <span class="hero-eyebrow">DepEd Cavite Province</span>',
    '  <span class="hero-eyebrow">Department of Education</span>',
    'Eyebrow text'
);

// ── 4. Contact section sub-heading → match eyebrow style (gold, no pill) ─────
apply_patch($target,
    '    <h1 class="hero-eyebrow">Schools Division Office of Cavite Province</h1>',
    '    <p class="contact-place">Schools Division Office of Cavite Province</p>',
    'Contact sub-heading element'
);

// ── 5. Add .contact-place style (insert before .contact-title) ───────────────
apply_patch($target,
    '  .contact-title { font-size: .95rem;',
    '  .contact-place { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 28px; letter-spacing: .01em; }
  .contact-title { font-size: .95rem;',
    'contact-place CSS'
);

// ── 6. Footer → blue background ───────────────────────────────────────────────
apply_patch($target,
    '  footer {
    background: rgba(255,255,255,0.97);
    border-top: 3px solid #d0d8e8;
    padding: 22px 32px 16px;
    position: relative;
    z-index: 1;
  }',
    '  footer {
    background: rgba(0, 48, 135, 0.96);
    border-top: 3px solid rgba(255,215,0,.6);
    padding: 22px 32px 16px;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(8px);
  }',
    'Footer blue background'
);

// ── 7. Footer divider → white/translucent ─────────────────────────────────────
apply_patch($target,
    '  .footer-divider {
    width: 1px; height: 52px; background: #c8d0dc;
  }',
    '  .footer-divider {
    width: 1px; height: 52px; background: rgba(255,255,255,.25);
  }',
    'Footer divider color'
);

// ── 8. Footer copy text → white ───────────────────────────────────────────────
apply_patch($target,
    '  .footer-copy {
    font-size: .7rem; color: #8a95a8;
    letter-spacing: .06em; text-transform: uppercase;
  }',
    '  .footer-copy {
    font-size: .7rem; color: rgba(255,255,255,.65);
    letter-spacing: .06em; text-transform: uppercase;
  }',
    'Footer copy color'
);

echo "\n🎉  All patches applied successfully.\n";
echo "    Run: php artisan view:clear\n";
