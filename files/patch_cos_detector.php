<?php
/**
 * patch_cos_detector.php
 *
 * Extends PositionBlockDetector to handle COS-format memos
 * (e.g. OSDS-2026-DM-0056). Confirmed OCR structure from real output:
 *
 *   » SCHOOL SPORTS PROGRAM FOCAL PERSON
 *   (Contract of Service)
 *
 * The » is Tesseract's rendering of ➤. No SG, no lettered prefix.
 * Qualifications under "Qualifications:" not "Qualification Standards:"
 * Duties under "Terms of Reference:" not "Duties and Responsibilities:"
 * Place of assignment is inline prose, not a table.
 * "Number of Vacant Position:" (singular confirmed in real OCR output).
 *
 * Also: any unrecognised title detected via COS format is automatically
 * registered into config/job_titles.php via JobTitleRegistrar, so
 * editing the imported posting later won't fail dropdown validation.
 *
 * Drop in project root, run once: php patch_cos_detector.php
 * No migration needed. Delete after confirming COS PDF imports work.
 */

function do_backup(string $path): void {
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

$detectorPath = __DIR__ . '/app/Services/PositionBlockDetector.php';
if (!file_exists($detectorPath)) {
    die("ERROR: Cannot find app/Services/PositionBlockDetector.php\n");
}
do_backup($detectorPath);

$src = file_get_contents($detectorPath);

// ── Patch 1: detect() — add COS fallback when standard detection finds nothing

$find1 = '        $headingMatches = $this->findPositionHeadings($fullText);

        if (empty($headingMatches)) {
            return [];
        }

        $blocks = [];
        foreach ($headingMatches as $i => $heading) {
            $blockStart = $heading[\'offset\'];
            $blockEnd = $headingMatches[$i + 1][\'offset\'] ?? strlen($fullText);
            $blockText = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $blocks[] = $this->parseBlock($heading, $blockText, $pageTexts, $pageBoundaries, $blockStart);
        }

        return $blocks;';

$replace1 = '        $headingMatches = $this->findPositionHeadings($fullText);

        // If standard detection (A. TITLE (SG-XX)) finds nothing, try the
        // COS format (» TITLE / (Contract of Service)) before giving up.
        if (empty($headingMatches)) {
            return $this->detectCosFormat($fullText, $pageTexts, $pageBoundaries);
        }

        $blocks = [];
        foreach ($headingMatches as $i => $heading) {
            $blockStart = $heading[\'offset\'];
            $blockEnd = $headingMatches[$i + 1][\'offset\'] ?? strlen($fullText);
            $blockText = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $blocks[] = $this->parseBlock($heading, $blockText, $pageTexts, $pageBoundaries, $blockStart);
        }

        return $blocks;';

$count = substr_count($src, $find1);
if ($count !== 1) { die("ERROR [patch 1]: Expected 1 match, found $count — aborting.\n"); }
$src = str_replace($find1, $replace1, $src);
echo "OK [patch 1: add COS fallback to detect()]\n";

// ── Patch 2: insert COS methods before the closing brace of the class ─────────

$find2 = '    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        if (preg_match(\'/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is\', $blockText, $m)) {
            $duties = trim(preg_replace(\'/\s+/\', \' \', $m[1]));
            return $duties !== \'\' ? $duties : null;
        }

        return null;
    }
}';

$replace2 = '    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        if (preg_match(\'/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is\', $blockText, $m)) {
            $duties = trim(preg_replace(\'/\s+/\', \' \', $m[1]));
            return $duties !== \'\' ? $duties : null;
        }

        return null;
    }

    // =========================================================================
    // COS-format detection
    // Handles memos like OSDS-2026-DM-0056 where positions are announced
    // with a » bullet instead of a lettered block (A., B.) and have no SG.
    // =========================================================================

    /**
     * Detects position headings in COS-format memos.
     * Confirmed real OCR pattern (from OSDS-2026-DM-0056):
     *   "» SCHOOL SPORTS PROGRAM FOCAL PERSON"
     *   "(Contract of Service)"
     */
    private function detectCosFormat(
        string $fullText,
        array $pageTexts,
        array $pageBoundaries
    ): array {
        // Match » or > followed by an all-caps title, then optionally
        // "(Appointment Type)" on the next line.
        $pattern = \'/(?:»|>|›)\s+([A-Z][A-Z\s\-]+?)[ \t]*\n[ \t]*(?:\(([^)\n]+)\))?/m\';

        if (!preg_match_all($pattern, $fullText, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $blocks  = [];
        $total   = count($matches[0]);

        foreach ($matches[0] as $idx => $fullMatch) {
            $rawTitle        = trim($matches[1][$idx][0]);
            $appointmentType = isset($matches[2][$idx][0]) && $matches[2][$idx][0] !== \'\'
                               ? trim($matches[2][$idx][0])
                               : null;
            $offset          = $fullMatch[1];

            $displayTitle = $this->titleCaseOcr($rawTitle);
            if ($appointmentType) {
                $displayTitle .= \' (\' . $appointmentType . \')\';
            }

            $canonicalTitle = $this->resolveCosTitleAgainstList($displayTitle);

            $blockStart = $offset;
            $blockEnd   = ($idx + 1 < $total)
                          ? $matches[0][$idx + 1][1]
                          : strlen($fullText);
            $blockText  = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $block = $this->parseCosBlock(
                $displayTitle,
                $canonicalTitle,
                $blockText
            );

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Parses a single COS position block into the standard output
     * structure so PositionBlockExpander handles it identically.
     */
    private function parseCosBlock(
        string $displayTitle,
        string $canonicalTitle,
        string $blockText
    ): ?array {
        // Qualifications — same field names, under "Qualifications:" header.
        // extractLabeledField() works unchanged since it matches the label
        // name anywhere in the block text.
        $education  = $this->extractLabeledField($blockText, \'Education\');
        $training   = $this->extractLabeledField($blockText, \'Training\');
        $experience = $this->extractLabeledField($blockText, \'Experience\');

        // Additional Qualifications — COS-specific extra bullet points.
        // Stored in description since there\'s no standard field for them.
        $description = null;
        if (preg_match(
            \'/Additional Qualifications?:?\s*(.*?)(?=Number of Vacant|Place of Assignment|Terms of Reference|Mandatory Requirements|$)/is\',
            $blockText, $m
        )) {
            $val = trim(preg_replace(\'/\s+/\', \' \', $m[1]));
            $description = $val !== \'\' ? $val : null;
        }

        // Vacancies — "Number of Vacant Position:" (singular confirmed in OCR)
        $vacancies = 1;
        if (preg_match(\'/Number of Vacant Position[s]?:?\s*(\d+)/i\', $blockText, $m)) {
            $vacancies = (int) $m[1];
        }

        // Place of assignment — inline prose in COS memos, not a table.
        // Confirmed: "Place of Assignment: Schools Division Office — Curriculum Implementation Division"
        $place = \'To be determined\';
        if (preg_match(\'/Place of Assignment:?\s*(.+?)(?:\n|$)/i\', $blockText, $m)) {
            $extracted = trim($m[1]);
            if ($extracted !== \'\') {
                $place = $extracted;
            }
        }

        // Duties — "Terms of Reference:" in COS memos.
        $duties = null;
        if (preg_match(\'/Terms of Reference:?\s*(.*?)(?=\n\s*\d+\.\s|\z)/is\', $blockText, $m)) {
            $raw = trim(preg_replace(\'/\s+/\', \' \', $m[1]));
            $duties = $raw !== \'\' ? $raw : null;
        }

        return [
            \'title\'                     => $displayTitle,
            \'canonical_title\'           => $canonicalTitle,
            \'was_registered\'            => false,
            \'salary_grade\'              => null,
            \'qualification_education\'   => $education,
            \'qualification_training\'    => $training,
            \'qualification_experience\'  => $experience,
            \'qualification_eligibility\' => null,
            \'description\'              => $description,
            \'vacancies\'                => $vacancies,
            \'duties_responsibilities\'  => $duties,
            \'place_of_assignment\'      => [\'type\' => \'single\', \'value\' => $place],
        ];
    }

    /**
     * Matches a COS display title against the canonical list.
     * Registers it permanently via JobTitleRegistrar if not found,
     * so editing the imported posting later won\'t fail validation.
     */
    private function resolveCosTitleAgainstList(string $displayTitle): string
    {
        $normalized = $this->normalizeForComparison($displayTitle);

        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalized) {
                return $canonical;
            }
        }

        // Not in list — register permanently.
        $this->titleRegistrar->register($displayTitle);
        $this->canonicalTitles[] = $displayTitle;

        return $displayTitle;
    }

    /**
     * Converts an all-caps OCR string to Title Case, fixing common
     * Roman numeral OCR misreads (Ill -> III, ll -> II, etc.).
     */
    private function titleCaseOcr(string $raw): string
    {
        $fixed = preg_replace(\'/\bIll\b/\', \'III\', $raw);
        $fixed = preg_replace(\'/\bll\b/\',  \'II\',  $fixed);
        $words = explode(\' \', strtolower(trim($fixed)));
        $words = array_map(function ($w) {
            if ($w === \'iii\') return \'III\';
            if ($w === \'ii\')  return \'II\';
            if ($w === \'iv\')  return \'IV\';
            if ($w === \'i\')   return \'I\';
            return ucfirst($w);
        }, $words);
        return implode(\' \', $words);
    }
}';

$count = substr_count($src, $find2);
if ($count !== 1) { die("ERROR [patch 2]: Expected 1 match, found $count — aborting.\n"); }
$src = str_replace($find2, $replace2, $src);
echo "OK [patch 2: add COS detection methods]\n";

file_put_contents($detectorPath, $src);

echo "\nDone. No migration needed.\n";
echo "Upload OSDS-2026-DM-0056.pdf via /job-postings/import to confirm.\n";
echo "Expected: 1 position — 'School Sports Program Focal Person (Contract of Service)'\n";
echo "  Education, Training, Experience, Place of Assignment, Duties all populated.\n";
echo "  No SG (COS positions don't have one).\n";
echo "Delete this script when confirmed working.\n";
