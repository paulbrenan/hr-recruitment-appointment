<?php

namespace App\Services;

/**
 * PositionBlockDetector
 *
 * Scans OCR'd text from a DepEd Cavite vacancy announcement PDF and
 * splits it into individual position blocks (e.g. "A. ADMINISTRATIVE
 * OFFICER II (SG-11)"), extracting the structured fields each block
 * contains: qualification standards, vacancy count, place of
 * assignment (either "To be determined" or a full school table via
 * VacancyTableParser), and duties & responsibilities.
 *
 * Anchors position detection on the known title list (config
 * job_titles.titles), since titles must conform to that list.
 *
 * Secondary/Elementary prefix handling (REVISED):
 * Real memos sometimes carry a "Secondary"/"Elementary" prefix not
 * present in the canonical list (e.g. "SECONDARY SCHOOL PRINCIPAL III"
 * vs. the canonical "School Principal III"). Earlier behavior silently
 * stripped the prefix and merged both variants into one generic
 * canonical title — this caused real data loss (two genuinely distinct
 * postings in the same memo collapsed into one) and a downstream bug:
 * editing an imported posting failed validation because the displayed
 * title (with prefix) didn't exist in the dropdown's option list.
 *
 * New behavior: when a Secondary/Elementary-prefixed title is detected
 * and the EXACT prefixed form is not yet in the canonical title list,
 * that exact form is added to config/job_titles.php on the spot (via
 * JobTitleRegistrar) so it becomes a real, permanent, editable option
 * — not silently merged, not just held in memory for one import.
 */
class PositionBlockDetector
{
    /** Prefixes that may appear in a real memo but aren't part of the canonical list. */
    private const STRIPPABLE_PREFIXES = ['Secondary', 'Elementary'];

    private array $canonicalTitles;
    private VacancyTableParser $tableParser;
    private JobTitleRegistrar $titleRegistrar;

    public function __construct(
        array $canonicalTitles,
        ?VacancyTableParser $tableParser = null,
        ?JobTitleRegistrar $titleRegistrar = null
    ) {
        $this->canonicalTitles = $canonicalTitles;
        $this->tableParser = $tableParser ?? new VacancyTableParser();
        $this->titleRegistrar = $titleRegistrar ?? new JobTitleRegistrar();
    }

    /**
     * @param array<int, array{number:int, text:string}> $pageTexts As produced by JobPostingImportController::extract()
     * @return array<int, array> One entry per DETECTED POSITION BLOCK (not yet expanded per-school)
     */
    public function detect(array $pageTexts): array
    {
        $fullText = '';
        $pageBoundaries = []; // offset => page number
        foreach ($pageTexts as $page) {
            $pageBoundaries[strlen($fullText)] = $page['number'];
            $fullText .= "\n" . $page['text'];
        }

        $headingMatches = $this->findPositionHeadings($fullText);

        if (empty($headingMatches)) {
            return [];
        }

        $blocks = [];
        foreach ($headingMatches as $i => $heading) {
            $blockStart = $heading['offset'];
            $blockEnd = $headingMatches[$i + 1]['offset'] ?? strlen($fullText);
            $blockText = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $blocks[] = $this->parseBlock($heading, $blockText, $pageTexts, $pageBoundaries, $blockStart);
        }

        return $blocks;
    }

    /**
     * Finds every line matching "LETTER. TITLE (SG-XX)" where TITLE
     * (after stripping known prefixes) matches the canonical title list.
     */
    private function findPositionHeadings(string $text): array
    {
        $matches = [];

        // Letter + title + (SG-XX), tolerant of OCR's "Ill" for "III" etc.
        // IMPORTANT: the leading "A." / "B." position-letter prefix must
        // stay case-SENSITIVE (uppercase only) — see prior session's
        // confirmed bug where /i across the whole pattern matched
        // lowercase sub-bullets inside Duties text as fake headings.
        $pattern = '/^[A-Z]\.\s+((?i)[A-Za-z][A-Za-z\s.,\'\-]+?)\s*\((?i)sg-?\s*(\d{1,2})\)/m';

        if (!preg_match_all($pattern, $text, $rawMatches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($rawMatches[0] as $i => $fullMatch) {
            $rawTitle = trim($rawMatches[1][$i][0]);
            $sg = $rawMatches[2][$i][0];
            $offset = $fullMatch[1];

            $resolved = $this->resolveTitle($rawTitle);

            if ($resolved === null) {
                // Not a real position heading match — skip (could be OCR
                // noise that happens to look like a heading).
                continue;
            }

            $matches[] = [
                'offset' => $offset,
                'raw_title' => $rawTitle,
                'canonical_title' => $resolved['title'],
                'was_registered' => $resolved['was_registered'],
                'salary_grade' => 'SG-' . $sg,
            ];
        }

        return $matches;
    }

    /**
     * Resolves a raw OCR'd title to a usable canonical title, with the
     * REVISED prefix behavior described in the class docblock.
     *
     * Resolution order:
     *  1. Exact match against the canonical list (case/whitespace/OCR-
     *     numeral tolerant) — most common case, no prefix involved.
     *  2. A Secondary/Elementary-prefixed title that ALREADY exists as
     *     its own specific canonical entry (e.g. a prior import already
     *     registered "Secondary School Principal III") — exact match.
     *  3. A Secondary/Elementary-prefixed title whose prefix-STRIPPED
     *     form matches a canonical entry, but the prefixed form itself
     *     does NOT exist yet — this is the case that used to silently
     *     merge. Now: register the exact prefixed form as a new
     *     permanent canonical title (via JobTitleRegistrar) and return
     *     it, flagged as newly registered.
     *
     * @return array{title:string, was_registered:bool}|null
     */
    private function resolveTitle(string $rawTitle): ?array
    {
        $normalizedRaw = $this->normalizeForComparison($rawTitle);

        // 1. Direct exact match (covers both plain titles AND any
        //    Secondary/Elementary variant already registered previously).
        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalizedRaw) {
                return ['title' => $canonical, 'was_registered' => false];
            }
        }

        // 2. Try stripping a known prefix and matching the REMAINDER
        //    against the canonical list — if it matches, the raw
        //    (prefixed) form is a genuine new variant that needs to be
        //    registered as its own entry, not merged into the generic one.
        foreach (self::STRIPPABLE_PREFIXES as $prefix) {
            $stripped = preg_replace('/^' . $prefix . '\s+/i', '', $rawTitle);
            if ($stripped === $rawTitle) {
                continue; // prefix wasn't present, nothing to strip
            }

            $strippedNormalized = $this->normalizeForComparison($stripped);
            foreach ($this->canonicalTitles as $canonical) {
                if ($this->normalizeForComparison($canonical) === $strippedNormalized) {
                    // Build the proper display form of the prefixed title,
                    // e.g. "Secondary School Principal III".
                    $newTitle = $this->buildDisplayTitle($rawTitle, $canonical);

                    // Register it as a real, permanent canonical entry.
                    // JobTitleRegistrar handles dedup internally too, so
                    // calling this repeatedly across multiple imports is safe.
                    $this->titleRegistrar->register($newTitle);

                    // Keep our in-memory list in sync for the REST of this
                    // same detect() run, so if the same variant appears
                    // twice in one document we don't re-register it.
                    $this->canonicalTitles[] = $newTitle;

                    return ['title' => $newTitle, 'was_registered' => true];
                }
            }
        }

        return null;
    }

    private function normalizeForComparison(string $text): string
    {
        // Fix common OCR Roman-numeral misreads before comparing.
        $text = preg_replace('/\bIll\b/', 'III', $text);
        $text = preg_replace('/\bll\b/', 'II', $text);
        $text = preg_replace('/\bl\b/', 'I', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return strtolower(trim($text));
    }

    private function parseBlock(
        array $heading,
        string $blockText,
        array $pageTexts,
        array $pageBoundaries,
        int $blockStartOffset
    ): array {
        $education = $this->extractLabeledField($blockText, 'Education');
        $training = $this->extractLabeledField($blockText, 'Training');
        $experience = $this->extractLabeledField($blockText, 'Experience');
        $eligibility = $this->extractLabeledField($blockText, 'Eligibility');

        $vacancies = null;
        if (preg_match('/Number of Vacant Positions:?\s*(\d+)/i', $blockText, $m)) {
            $vacancies = (int) $m[1];
        }

        $placeOfAssignment = $this->extractPlaceOfAssignment($blockText, $blockStartOffset, $pageTexts, $pageBoundaries, $vacancies);

        $duties = $this->extractDuties($blockText, $heading['canonical_title']);

        return [
            'title' => $heading['canonical_title'],
            'canonical_title' => $heading['canonical_title'],
            'was_registered' => $heading['was_registered'],
            'salary_grade' => $heading['salary_grade'],
            'qualification_education' => $education,
            'qualification_training' => $training,
            'qualification_experience' => $experience,
            'qualification_eligibility' => $eligibility,
            'vacancies' => $vacancies,
            'duties_responsibilities' => $duties,
            'place_of_assignment' => $placeOfAssignment,
        ];
    }

    /**
     * Builds the proper-cased prefixed title for a NEWLY REGISTERED
     * variant, e.g. raw "SECONDARY SCHOOL PRINCIPAL Ill" + canonical
     * "School Principal III" -> "Secondary School Principal III".
     */
    private function buildDisplayTitle(string $rawTitle, string $canonicalTitle): string
    {
        $fixed = preg_replace('/\bIll\b/', 'III', $rawTitle);
        $words = explode(' ', strtolower($fixed));
        $words = array_map(function ($w) {
            if ($w === 'iii') return 'III';
            if ($w === 'ii') return 'II';
            if ($w === 'iv') return 'IV';
            return ucfirst($w);
        }, $words);
        return implode(' ', $words);
    }

    private function extractLabeledField(string $text, string $label): ?string
    {
        $stopLabels = ['Education', 'Training', 'Experience', 'Eligibility', 'Number of Vacant Positions'];
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
        $stopPattern = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $stopLabels));

        $pattern = '/' . preg_quote($label, '/') . ':?\s*(.*?)(?=(?:' . $stopPattern . '):|$)/is';

        if (preg_match($pattern, $text, $m)) {
            $value = trim(preg_replace('/\s+/', ' ', $m[1]));
            $value = rtrim($value, '. ');
            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match('/Place of Assignment:?\s*To be determined/i', $blockText)) {
            return ['type' => 'single', 'value' => 'To be determined'];
        }

        $startPage = $this->pageForOffset($blockStartOffset, $pageBoundaries);

        $dutiesOffset = null;
        if (preg_match('/Duties and Responsibilities/i', $blockText, $m, PREG_OFFSET_CAPTURE)) {
            $dutiesOffset = $blockStartOffset + $m[0][1];
        }
        $endPage = $dutiesOffset !== null
            ? $this->pageForOffset($dutiesOffset, $pageBoundaries)
            : $this->pageForOffset($blockStartOffset + strlen($blockText), $pageBoundaries);

        $relevantPages = array_filter($pageTexts, fn ($p) => $p['number'] >= $startPage && $p['number'] <= $endPage);
        $pageTextsOnly = array_map(fn ($p) => $p['text'], $relevantPages);

        $schools = $this->tableParser->parseMultiPage($pageTextsOnly);

        return ['type' => 'table', 'schools' => $schools];
    }

    private function pageForOffset(int $offset, array $pageBoundaries): int
    {
        $page = 1;
        foreach ($pageBoundaries as $boundaryOffset => $pageNumber) {
            if ($boundaryOffset <= $offset) {
                $page = $pageNumber;
            }
        }
        return $page;
    }

    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            $duties = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $duties !== '' ? $duties : null;
        }

        return null;
    }
}
