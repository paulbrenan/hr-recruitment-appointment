<?php
/**
 * fix_welcome_page_contact_section.php
 *
 * Patches the public landing page (welcome.blade.php) to:
 *  1. Remove the "Admin Login" button from the public nav — per project
 *     decision, applicant login was already removed and this page no
 *     longer needs to advertise HR/staff admin access. HR staff can
 *     still reach /login directly by URL; the route itself is untouched.
 *  2. Add a new "Contact & Location" section below the existing hero/
 *     tracker box, matching the requested DepEd-style layout (address /
 *     contact no. / email icons row, plus an embedded Google Maps
 *     location). Uses PLACEHOLDER data (address, phone numbers, email,
 *     map embed) since real DepEd Cavite office details weren't
 *     provided — clearly marked for the user to replace with real info.
 *  3. Adds mobile responsiveness for the new section, extending the
 *     EXISTING @media(max-width:560px) breakpoint already used
 *     elsewhere in this file, rather than introducing a new breakpoint
 *     strategy.
 *
 * Usage:
 *   1. Drop this file into your hr-recruitment project root
 *   2. Run: php fix_welcome_page_contact_section.php
 *   3. Delete this file afterward
 */

$projectRoot = __DIR__;

function fail(string $msg): void
{
    echo "\n[FAILED] $msg\n";
    exit(1);
}

function ok(string $msg): void
{
    echo "[OK] $msg\n";
}

// Try the most likely locations for this view, since its exact path
// wasn't confirmed this session.
$candidates = [
    $projectRoot . '/resources/views/welcome.blade.php',
    $projectRoot . '/resources/views/portal/welcome.blade.php',
    $projectRoot . '/resources/views/track/welcome.blade.php',
];

$viewPath = null;
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $viewPath = $candidate;
        break;
    }
}

if ($viewPath === null) {
    fail("Could not find welcome.blade.php in any of the expected locations:\n  - "
        . implode("\n  - ", $candidates)
        . "\n\nPlease edit \$candidates at the top of this script to add the correct path, "
        . "or move this script and re-run it from a location where one of those paths resolves correctly.");
}

ok("Found view at: $viewPath");

$backupPath = $viewPath . '.bak';
if (!copy($viewPath, $backupPath)) {
    fail("Could not create backup at $backupPath");
}
ok("Backed up welcome.blade.php -> .bak");

$content = file_get_contents($viewPath);

// ============================================================
// PART 1: Remove the Admin Login button
// ============================================================

$oldNav = <<<'BLADE'
<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
  <a class="btn-admin" href="{{ route('login') }}">
    <i class="bi bi-shield-lock"></i> Admin Login
  </a>
</nav>
BLADE;

$newNav = <<<'BLADE'
<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
</nav>
BLADE;

if (str_contains($content, 'Admin Login')) {
    if (!str_contains($content, $oldNav)) {
        fail("Found 'Admin Login' text but the surrounding <nav> markup doesn't match exactly "
            . "what was expected. No changes written — please re-paste the current file's <nav> section.");
    }
    $content = str_replace($oldNav, $newNav, $content);
    ok("Removed Admin Login button from the public nav");
} else {
    ok("No Admin Login button found — skipping (already removed).");
}

// ============================================================
// PART 2: Add the Contact & Location section CSS
// ============================================================

$cssAnchor = "  /* ── INFO STRIP ── */";

$contactCss = <<<'CSS'
  /* ── CONTACT & LOCATION ── */
  .contact-section { width:100%; max-width:900px; margin:48px auto 0; padding:0 24px; position:relative; z-index:1; text-align:center; }
  .contact-title { font-size:.95rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); margin-bottom:4px; }
  .contact-subtitle { font-size:.85rem; font-weight:700; color:var(--dark); margin-bottom:28px; }
  .contact-grid { display:flex; gap:32px; justify-content:center; flex-wrap:wrap; margin-bottom:36px; }
  .contact-item { flex:1; min-width:160px; max-width:240px; }
  .contact-item i { font-size:1.6rem; color:var(--teal-mid); margin-bottom:8px; display:block; }
  .contact-item .contact-label { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--dark); margin-bottom:6px; }
  .contact-item .contact-value { font-size:.8rem; color:var(--muted); line-height:1.5; }
  .location-block i { font-size:1.8rem; color:var(--teal-mid); margin-bottom:6px; display:block; }
  .location-label { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--dark); margin-bottom:16px; }
  .location-map { display:inline-block; width:100%; max-width:520px; border-radius:12px; overflow:hidden; border:1px solid #d4eaea; box-shadow:0 4px 20px rgba(43,138,138,.08); }
  .location-map iframe { width:100%; height:320px; border:0; display:block; }

  /* ── INFO STRIP ── */
CSS;

if (str_contains($content, '.contact-section {')) {
    ok("Contact section CSS already present — skipping CSS insertion.");
} else {
    if (!str_contains($content, $cssAnchor)) {
        fail("Could not find the '/* ── INFO STRIP ── */' CSS anchor comment. "
            . "Nav change above WAS already written. No further changes made — please check file structure.");
    }
    $content = str_replace($cssAnchor, $contactCss, $content);
    ok("Added Contact & Location section CSS");
}

// ============================================================
// PART 3: Add mobile responsiveness for the new section
// ============================================================

$oldMobile = <<<'BLADE'
  @media(max-width:560px){
    .topnav{padding:12px 16px;}
    .track-box{padding:22px 18px;}
    .track-input-row{flex-wrap:wrap;}
    .btn-track{width:100%;justify-content:center;}
    .detail-grid{grid-template-columns:1fr;}
  }
BLADE;

$newMobile = <<<'BLADE'
  @media(max-width:560px){
    .topnav{padding:12px 16px;}
    .track-box{padding:22px 18px;}
    .track-input-row{flex-wrap:wrap;}
    .btn-track{width:100%;justify-content:center;}
    .detail-grid{grid-template-columns:1fr;}
    .contact-section{padding:0 16px;margin-top:36px;}
    .contact-grid{gap:20px;}
    .contact-item{min-width:100%;max-width:100%;}
    .location-map iframe{height:240px;}
  }
BLADE;

if (str_contains($content, '.contact-section{padding:0 16px')) {
    ok("Mobile responsiveness for contact section already present — skipping.");
} else {
    if (!str_contains($content, $oldMobile)) {
        fail("Could not find the existing @media(max-width:560px) block. "
            . "Earlier changes above WERE already written. Please check file structure.");
    }
    $content = str_replace($oldMobile, $newMobile, $content);
    ok("Added mobile responsiveness for the contact section");
}

// ============================================================
// PART 4: Add the actual Contact & Location HTML section
// ============================================================

$htmlAnchor = "</section>\n\n{{-- ── FOOTER ── --}}";

$contactHtml = <<<'BLADE'

  <div class="contact-section">
    <div class="contact-title">Welcome to Online Document Tracking System</div>
    <div class="contact-subtitle">Department of Education &mdash; Schools Division Office of Cavite Province</div>

    <div class="contact-grid">
      <div class="contact-item">
        <i class="bi bi-geo-alt-fill"></i>
        <div class="contact-label">Address</div>
        {{-- PLACEHOLDER: replace with the real division office address --}}
        <div class="contact-value">Cavite Capitol Compound, Brgy. Luciano,<br>Trece Martires City, Cavite</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-telephone-fill"></i>
        <div class="contact-label">Contact No.</div>
        {{-- PLACEHOLDER: replace with the real division office contact numbers --}}
        <div class="contact-value">(046) 419-1286,<br>(046) 412-0349</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-envelope-fill"></i>
        <div class="contact-label">Email</div>
        {{-- PLACEHOLDER: replace with the real division office email --}}
        <div class="contact-value">deped.cavite@deped.gov.ph</div>
      </div>
    </div>

    <div class="location-block">
      <i class="bi bi-globe2"></i>
      <div class="location-label">Location</div>
      <div class="location-map">
        {{-- PLACEHOLDER: replace this src with the real division office's Google Maps embed URL --}}
        <iframe
          src="https://www.google.com/maps?q=Schools+Division+Office+of+Cavite+Province&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>

</section>

{{-- ── FOOTER ── --}}
BLADE;

if (str_contains($content, 'contact-section">')) {
    ok("Contact section HTML already present — skipping HTML insertion.");
} else {
    if (!str_contains($content, $htmlAnchor)) {
        fail("Could not find the </section> + FOOTER comment anchor to insert the contact section after. "
            . "Earlier changes above WERE already written. No further changes made — please check file structure.");
    }
    $content = str_replace($htmlAnchor, $contactHtml, $content);
    ok("Added Contact & Location section HTML (with placeholder address/phone/email/map)");
}

if (file_put_contents($viewPath, $content) === false) {
    fail("Could not write updated welcome.blade.php");
}
ok("Wrote updated welcome.blade.php");

echo "\n=========================================================\n";
echo "DONE.\n";
echo "=========================================================\n\n";

echo "IMPORTANT: the address, phone numbers, email, and map embed are all\n";
echo "PLACEHOLDER data (marked with PHP comments in the source). Replace\n";
echo "them with your division's real contact details before this goes live.\n\n";

echo "To get a real Google Maps embed URL for the map: go to Google Maps,\n";
echo "search your office, click Share -> Embed a map -> copy the src URL\n";
echo "from the provided <iframe> tag, and swap it into the iframe src\n";
echo "in resources/views/welcome.blade.php (or wherever this file ended up).\n\n";

echo "Test: refresh the public landing page (/) — the Admin Login button\n";
echo "should be gone from the top nav, and a new Contact & Location section\n";
echo "should appear below the tracker box, above the footer. Resize your\n";
echo "browser narrow (or check on an actual phone) to confirm it stacks\n";
echo "cleanly on mobile.\n\n";

echo "You can delete this script (fix_welcome_page_contact_section.php) now.\n";
