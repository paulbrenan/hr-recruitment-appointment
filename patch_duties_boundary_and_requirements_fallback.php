<?php
/**
 * patch_duties_boundary_and_requirements_fallback.php
 *
 * PART A — PositionBlockDetector.php
 * ------------------------------------------------------------------------
 * 1. BOUNDARY BUG (the reported screenshot): parseCosBlock()'s "Terms of
 *    Reference" duties extraction only stopped at a numbered list item
 *    ("\n  1. "). Bullet-style duty lines (this memo's OCR renders "•" as
 *    a lone "e ") never hit that stop condition, so the capture ran all
 *    the way to \z — swallowing the "Interested and qualified applicants
 *    shall fill-up..." intro, the entire Mandatory Requirements A-J list,
 *    footer contact info, and the start of Additional Requirements into
 *    duties_responsibilities. Added the recurring boilerplate markers
 *    (shared across every DepEd Cavite memo) as additional stop points.
 *
 * 2. UNDERLYING BUG: both extractDuties() (regular format) and
 *    parseCosBlock() (COS format) collapsed ALL whitespace — including
 *    real newlines from the OCR — down to single spaces. That means
 *    duties_responsibilities has never actually contained line breaks for
 *    any imported posting, which is why the Overview panel's duties
 *    UX parser (added earlier, splits on newlines to detect "A." headings
 *    and "a."/bullet lines) has had nothing to split on — it's only ever
 *    rendered one flat paragraph. New shared cleanDutiesText() helper
 *    preserves real line breaks, only collapsing repeated spaces/tabs
 *    WITHIN a line, and normalizes the "e " bullet-glyph misread back to
 *    "•" per line.
 *
 * PART B — mandatory/additional requirements: stop duplicating
 * ------------------------------------------------------------------------
 * RequirementsExtractor always returns the same static text regardless of
 * the source PDF, so copying a full duplicate of that block into
 * mandatory_requirements/additional_requirements on every single imported
 * JobPosting row is pure duplication for no benefit. Changed:
 *   - confirm() no longer writes those two columns when creating a
 *     posting from an import (they stay null, same as a manual posting
 *     where HR hasn't filled them in).
 *   - JobPosting::mandatoryRequirementsList()/additionalRequirementsList()
 *     now fall back to RequirementsExtractor's static defaults when the
 *     column is null — so every posting (imported OR manual) always shows
 *     both lists, from one source of truth, no per-row duplication.
 *
 * HOW TO RUN:
 *   php patch_duties_boundary_and_requirements_fallback.php   (project root)
 * DELETE this script after running.
 *
 * NOTE: if you already ran patch_wire_requirements_extractor.php, that's
 * fine to leave in place — $batch->requirements is still useful for the
 * review-screen preview and gets deleted with the batch after confirm()
 * either way. This patch just stops copying that same static text onto
 * every posting row permanently.
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_duties_boundary_and_requirements_fallback.php ===\n\n";

// ─── PART A: PositionBlockDetector.php ────────────────────────────────────

echo "[A] PositionBlockDetector.php\n";

$detectorPath = ROOT . '/app/Services/PositionBlockDetector.php';

// A1. extractDuties() — preserve line breaks via shared helper
apply_patch(
    $detectorPath,
    "    private function extractDuties(string \$blockText, string \$canonicalTitle): ?string
    {
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\\s]+)?:?\\s*(.*)/is', \$blockText, \$m)) {
            \$duties = trim(preg_replace('/\\s+/', ' ', \$m[1]));
            return \$duties !== '' ? \$duties : null;
        }

        return null;
    }",
    "    private function extractDuties(string \$blockText, string \$canonicalTitle): ?string
    {
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\\s]+)?:?\\s*(.*)/is', \$blockText, \$m)) {
            return \$this->cleanDutiesText(\$m[1]);
        }

        return null;
    }

    /**
     * Shared duties-text cleaner for both extractDuties() (regular format)
     * and parseCosBlock() (COS format).
     *
     * Previously both callers collapsed ALL whitespace — including real
     * newlines from the OCR — into single spaces, which flattened every
     * duties section into one run-on paragraph with no structure left to
     * render as headings/bullets. This preserves real line breaks (only
     * collapsing repeated spaces/tabs WITHIN a line) and normalizes the
     * confirmed \"e \" bullet-glyph OCR misread (tesseract reading \"•\" as a
     * lone \"e\") back to a real bullet, per line.
     */
    private function cleanDutiesText(string \$raw): ?string
    {
        \$lines = preg_split('/\\r\\n|\\r|\\n/', \$raw);
        \$cleaned = [];

        foreach (\$lines as \$line) {
            // Collapse repeated spaces/tabs WITHIN the line, but don't
            // touch the newlines themselves.
            \$line = trim(preg_replace('/[ \\t]+/', ' ', \$line));
            if (\$line === '') {
                continue;
            }
            // Confirmed OCR misread: bullet glyph \"•\" renders as a lone
            // \"e\" token at the start of a duty line (e.g. \"e Facilitate
            // the implementation...\"). Normalize back to a real bullet.
            \$line = preg_replace('/^e (?=[A-Z])/', '• ', \$line);
            \$cleaned[] = \$line;
        }

        \$result = implode(\"\\n\", \$cleaned);
        return \$result !== '' ? \$result : null;
    }",
    'Add cleanDutiesText() helper; extractDuties() now preserves line breaks'
);

// A2. parseCosBlock() — broaden stop-lookahead + use the same helper
apply_patch(
    $detectorPath,
    "        // Duties — \"Terms of Reference:\" in COS memos.
        \$duties = null;
        if (preg_match('/Terms of Reference:?\\s*(.*?)(?=\\n\\s*\\d+\\.\\s|\\z)/is', \$blockText, \$m)) {
            \$raw = trim(preg_replace('/\\s+/', ' ', \$m[1]));
            \$duties = \$raw !== '' ? \$raw : null;
        }",
    "        // Duties — \"Terms of Reference:\" in COS memos.
        // CONFIRMED REAL BUG: the stop-lookahead only recognized a numbered
        // list item (\"\\n  1. ...\") as the end of the duties section.
        // Bullet-style duty lines never hit that stop condition, so the
        // capture ran all the way to the end of the block/document,
        // swallowing the entire \"Interested and qualified applicants...\"
        // intro, the full Mandatory Requirements A-J list, footer contact
        // info, and the start of Additional Requirements into
        // duties_responsibilities. Added the recurring boilerplate section
        // markers shared across every DepEd Cavite memo as stop points.
        \$duties = null;
        if (preg_match(
            '/Terms of Reference:?\\s*(.*?)(?=\\n\\s*\\d+\\.\\s|Interested and qualified applicants|Mandatory Requirements|Additional Requirements|Checklist of Requirements|\\z)/is',
            \$blockText, \$m
        )) {
            \$duties = \$this->cleanDutiesText(\$m[1]);
        }",
    'parseCosBlock(): broaden stop-lookahead + preserve line breaks'
);

// ─── PART B: stop duplicating static requirements per posting ────────────

echo "\n[B] JobPostingImportController.php\n";

$importControllerPath = ROOT . '/app/Http/Controllers/JobPostingImportController.php';

apply_patch(
    $importControllerPath,
    "        // Real requirements extracted from THIS document's cover memo
        // (Fix 2) — applied to every posting created from this import.
        // No more silent fallback to the old hardcoded standard A-J
        // default: if extraction found nothing for this particular PDF,
        // these fields are simply left null, same as any manually
        // created posting where HR hasn't filled them in yet.
        \$extractedRequirements = \$batch->requirements ?? ['mandatory' => [], 'additional' => ''];
        \$mandatoryText = !empty(\$extractedRequirements['mandatory'])
            ? implode(\"\\n\", \$extractedRequirements['mandatory'])
            : null;
        \$additionalText = !empty(\$extractedRequirements['additional'])
            ? \$extractedRequirements['additional']
            : null;

        \$created = 0;",
    "        // Mandatory/additional requirements are NOT copied onto each
        // created posting here anymore. RequirementsExtractor always
        // returns the same static DepEd-standard text regardless of which
        // PDF was uploaded, so duplicating that block into every single
        // imported JobPosting row's mandatory_requirements/
        // additional_requirements columns just bloats storage for no
        // benefit. These columns stay null on import (same as a manual
        // posting HR hasn't filled them in for yet) — JobPosting::
        // mandatoryRequirementsList()/additionalRequirementsList() fall
        // back to the same static defaults at display time instead, from
        // one source of truth.

        \$created = 0;",
    "confirm(): stop building \$mandatoryText/\$additionalText"
);

apply_patch(
    $importControllerPath,
    "                'place_of_assignment' => \$firstPlace,
                'mandatory_requirements' => \$mandatoryText,
                'additional_requirements' => \$additionalText,
                'memo_pdf_path' => \$memoPdfPath,",
    "                'place_of_assignment' => \$firstPlace,
                'memo_pdf_path' => \$memoPdfPath,",
    "confirm(): stop writing mandatory_requirements/additional_requirements on JobPosting::create()"
);

// ─── PART C: JobPosting model — fallback to static defaults ──────────────

echo "\n[C] JobPosting.php\n";

$modelPath = ROOT . '/app/Models/JobPosting.php';

apply_patch(
    $modelPath,
    "    public function mandatoryRequirementsList(): array
    {
        return \$this->splitRequirementLines(\$this->mandatory_requirements);
    }

    public function additionalRequirementsList(): array
    {
        return \$this->splitRequirementLines(\$this->additional_requirements);
    }",
    "    public function mandatoryRequirementsList(): array
    {
        if (\$this->mandatory_requirements) {
            return \$this->splitRequirementLines(\$this->mandatory_requirements);
        }

        // RequirementsExtractor always returns the same static
        // DepEd-standard text regardless of source document -- used as
        // the display fallback for every posting (imported or manual)
        // that hasn't had this column filled in, instead of duplicating
        // the same static text into every row at creation time.
        return (new \App\Services\RequirementsExtractor())->extract([])['mandatory'];
    }

    public function additionalRequirementsList(): array
    {
        if (\$this->additional_requirements) {
            return \$this->splitRequirementLines(\$this->additional_requirements);
        }

        return \$this->splitRequirementLines(
            (new \App\Services\RequirementsExtractor())->extract([])['additional']
        );
    }",
    'Model: fall back to RequirementsExtractor defaults when column is null'
);

echo "\n✅ Done.\n\n";
echo "Existing imported postings with garbled duties_responsibilities (from\n";
echo "before this fix) will need re-importing or manual cleanup -- this only\n";
echo "fixes the extraction going forward. Same for any postings that already\n";
echo "have the full requirements text duplicated onto their rows: harmless,\n";
echo "just redundant now; leave as-is or null them out later if you want.\n\n";
echo "DELETE this script after running.\n";
