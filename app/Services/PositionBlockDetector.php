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
 * job_titles.titles), since titles must conform to that list. Titles
 * in real memos sometimes carry a "Secondary"/"Elementary" prefix not
 * present in the canonical list (e.g. "SECONDARY SCHOOL PRINCIPAL III"
 * vs. the canonical "School Principal III") — these prefixes are
 * stripped before comparing, but preserved in the final output's title
 * field so the distinction isn't lost.
 */
class PositionBlockDetector
{
    /** Prefixes that may appear in a real memo but aren't part of the canonical title. */
    private const STRIPPABLE_PREFIXES = ['Secondary', 'Elementary'];

    private array $canonicalTitles;
    private VacancyTableParser $tableParser;

    public function __construct(array $canonicalTitles, ?VacancyTableParser $tableParser = null)
    {
        $this->canonicalTitles = $canonicalTitles;
        $this->tableParser = $tableParser ?? new VacancyTableParser();
    }

    /**
     * @param array<int, array{number:int, text:string}> $pageTexts As produced by JobPostingImportController::extract()
     * @return array<int, array> One entry per DETECTED POSITION BLOCK (not yet expanded per-school)
     */
    public function detect(array $pageTexts): array
    {
        // Concatenate all pages into one continuous string, but remember
        // which page each character offset roughly belongs to (page
        // boundaries matter for VacancyTableParser, which needs per-page
        // text to track multi-page tables correctly).
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
        // by not anchoring strictly on Roman numeral characters.
        // IMPORTANT: the leading "A." / "B." position-letter prefix must
        // stay case-SENSITIVE (uppercase only). Using /i for the whole
        // pattern was a real, confirmed bug — it let the regex match
        // lowercase sub-list bullets inside Duties and Responsibilities
        // text (e.g. "a. recruitment and selection...", "b. promotion
        // and deployment...") as if they were position headings, which
        // then swallowed everything up to the NEXT real heading inside
        // one giant incorrect match. Only the title text and "(SG-XX)"
        // portion should tolerate case variation.
        $pattern = '/^[A-Z]\.\s+((?i)[A-Za-z][A-Za-z\s.,\'\-]+?)\s*\((?i)sg-?\s*(\d{1,2})\)/m';

        if (!preg_match_all($pattern, $text, $rawMatches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($rawMatches[0] as $i => $fullMatch) {
            $rawTitle = trim($rawMatches[1][$i][0]);
            $sg = $rawMatches[2][$i][0];
            $offset = $fullMatch[1];

            $canonicalTitle = $this->matchCanonicalTitle($rawTitle);

            if ($canonicalTitle === null) {
                // Not a real position heading match — skip (could be OCR
                // noise that happens to look like a heading).
                continue;
            }

            $matches[] = [
                'offset' => $offset,
                'raw_title' => $rawTitle,
                'canonical_title' => $canonicalTitle,
                'salary_grade' => 'SG-' . $sg,
            ];
        }

        return $matches;
    }

    /**
     * Tries to match a raw OCR'd title (e.g. "SECONDARY SCHOOL PRINCIPAL
     * Ill") against the canonical title list, tolerating:
     *  - A leading "Secondary"/"Elementary" prefix not in the canonical list
     *  - Common OCR Roman-numeral misreads (Ill -> III, etc.)
     *  - Case and whitespace differences
     */
    private function matchCanonicalTitle(string $rawTitle): ?string
    {
        $normalized = $this->normalizeForComparison($rawTitle);

        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalized) {
                return $canonical;
            }
        }

        // Try stripping known prefixes (e.g. "Secondary School Principal
        // III" -> "School Principal III").
        foreach (self::STRIPPABLE_PREFIXES as $prefix) {
            $stripped = preg_replace('/^' . $prefix . '\s+/i', '', $rawTitle);
            if ($stripped !== $rawTitle) {
                $strippedNormalized = $this->normalizeForComparison($stripped);
                foreach ($this->canonicalTitles as $canonical) {
                    if ($this->normalizeForComparison($canonical) === $strippedNormalized) {
                        return $canonical;
                    }
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
            'title' => $this->buildDisplayTitle($heading['raw_title'], $heading['canonical_title']),
            'canonical_title' => $heading['canonical_title'],
            'salary_grade' => $heading['salary_grade'],
            'qualification_education' => $education,
            'qualification_training' => $training,
            'qualification_experience' => $experience,
            'qualification_eligibility' => $eligibility,
            'vacancies' => $vacancies,
            'duties_responsibilities' => $duties,
            'place_of_assignment' => $placeOfAssignment, // either ['type' => 'single', 'value' => 'To be determined'] or ['type' => 'table', 'schools' => [...]]
        ];
    }

    /**
     * Preserves how the title actually appeared in the memo (e.g.
     * "SECONDARY SCHOOL PRINCIPAL III") rather than silently replacing it
     * with the bare canonical form, since the distinction (secondary vs.
     * elementary) is real, useful information for the review screen.
     */
    private function buildDisplayTitle(string $rawTitle, string $canonicalTitle): string
    {
        // Title-case the raw OCR'd title (which is in shouting caps) for
        // a readable display title, fixing the common Roman-numeral misread.
        $fixed = preg_replace('/\bIll\b/', 'III', $rawTitle);
        $words = explode(' ', strtolower($fixed));
        $words = array_map(function ($w) {
            if ($w === 'iii') return 'III';
            if ($w === 'ii') return 'II';
            return ucfirst($w);
        }, $words);
        return implode(' ', $words);
    }

    private function extractLabeledField(string $text, string $label): ?string
    {
        // Stops at the next known label or "Number of Vacant Positions".
        $stopLabels = ['Education', 'Training', 'Experience', 'Eligibility', 'Number of Vacant Positions'];
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
        $stopPattern = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $stopLabels));

        // Confirmed real OCR behavior: the bullet marker ("•") in front of
        // each Qualification Standards line is misread as a lone letter
        // ("e", "o", "0"), e.g. "e Training: None required." With a lazy
        // capture stopping right at "Training:", that stray bullet-letter
        // ends up captured as part of THIS field's value instead ("...the
        // job. e"). This optional bullet group lets the lazy match stop
        // BEFORE the bullet artifact instead of after it.
        $bullet = '(?:[•●○]|\b[oOe0]\b)?';

        $pattern = '/' . preg_quote($label, '/') . ':?\s*(.*?)(?=\s*' . $bullet . '\s*(?:' . $stopPattern . '):|$)/is';

        if (preg_match($pattern, $text, $m)) {
            $value = trim(preg_replace('/\s+/', ' ', $m[1]));
            $value = rtrim($value, '. '); // trailing bullet artifacts
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

        // It's a table. Figure out which pages this block's table spans:
        // from the page containing "Place of Assignment:" through the
        // page containing "Duties and Responsibilities" (or block end).
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
        $pageTextsOnly = array_values($pageTextsOnly);

        // IMPORTANT: "Duties and Responsibilities" can start partway through
        // $endPage's text, not at the top of it. Passing the WHOLE page
        // through lets VacancyTableParser's number-hunting logic wander
        // into the duties bullets and pick up an unrelated number as a
        // bogus extra "row" (confirmed real case: a duty bullet reading
        // "Update regularly 201 files..." got matched as row 201, well
        // past the real table's last row). Truncate the last page's text
        // at the duties heading's actual in-page offset so the parser
        // never sees text past the real table.
        if ($dutiesOffset !== null && !empty($pageTextsOnly)) {
            $endPageStart = $this->pageStartOffset($endPage, $pageBoundaries);
            $relativeOffset = $dutiesOffset - $endPageStart;
            $lastIndex = count($pageTextsOnly) - 1;
            if ($relativeOffset >= 0 && $relativeOffset < strlen($pageTextsOnly[$lastIndex])) {
                $pageTextsOnly[$lastIndex] = substr($pageTextsOnly[$lastIndex], 0, $relativeOffset);
            }
        }

        $schools = $this->tableParser->parseMultiPage($pageTextsOnly);

        return ['type' => 'table', 'schools' => $schools];
    }

    /**
     * Returns the offset within $fullText where the given page's text
     * begins (the inverse lookup of the $pageBoundaries map built in
     * detect()).
     */
    private function pageStartOffset(int $pageNumber, array $pageBoundaries): int
    {
        foreach ($pageBoundaries as $offset => $num) {
            if ($num === $pageNumber) {
                return $offset + 1; // +1 skips the "\n" separator prepended before each page's text
            }
        }
        return 0;
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

    /**
     * Finds "Duties and Responsibilities" for THIS block specifically.
     * Confirmed real behavior: it can appear right after the block's own
     * table/place-of-assignment ends, OR after a heading like "DUTIES
     * AND RESPONSIBILITIES OF AO II" that names the position's
     * abbreviation rather than repeating "Duties and Responsibilities:"
     * verbatim.
     */
    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            $duties = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $duties !== '' ? $duties : null;
        }

        return null;
    }
}