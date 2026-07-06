<?php

namespace App\Services;

/**
 * RequirementsExtractor
 *
 * Extracts the REAL "Mandatory Requirements" and "Additional
 * Requirements" sections from a DepEd Cavite vacancy memo's cover
 * pages (typically pages 1-4), rather than always falling back to the
 * hardcoded standard A-J default that was previously used for every
 * import regardless of what the actual memo said.
 *
 * Confirmed real structure (from OSDS-2026-DM-073.pdf, pages 1-2):
 *
 *   Mandatory Requirements:
 *       A. Letter of intent ...
 *       B. Duly Accomplished Personal Data Sheet ...
 *       ...
 *       J. Checklist of Requirements and Omnibus Sworn Statement ...
 *
 *   Additional Requirements:
 *       A. Means of Verification showing Outstanding Accomplishments...
 *           1. Awards and Recognition
 *               a. Citation or Commendation
 *                   - Letter of Citation...
 *           2. Research and Innovation
 *               ...
 *
 * Mandatory Requirements is a flat, single-level A-J list — reliably
 * parseable into a clean array, matching the format the rest of the
 * app already uses for mandatory_requirements (newline-delimited list
 * via JobPosting::mandatoryRequirementsList()).
 *
 * Additional Requirements has a much deeper nested structure (lettered
 * top level, then numbered, then lettered sub-bullets, then dash
 * bullets) that doesn't map cleanly onto the same flat-list format.
 * Rather than lossily flattening it, this extractor preserves it as
 * one formatted text block (indented by nesting level) and stores it
 * as-is in additional_requirements — still far more useful than the
 * previous behavior of leaving it blank/default on every import.
 */
class RequirementsExtractor
{
    /**
     * @param array<int, array{number:int, text:string}> $pageTexts All OCR'd pages for the document.
     * @return array{mandatory: string[], additional: string}
     *   mandatory: clean array of requirement strings (the A-J list items, letters stripped)
     *   additional: the Additional Requirements section as one formatted text block, '' if not found
     */
    public function extract(array $pageTexts): array
    {
        // Mandatory/Additional Requirements live in the cover memo, which
        // per prior structural analysis is always pages 1-4 (before "LIST
        // OF VACANT POSITIONS"). Concatenate just those candidate pages —
        // cheaper and safer than scanning the whole document, since the
        // position blocks later in the doc can themselves contain
        // unrelated text that might accidentally match these patterns.
        $coverText = $this->buildCoverText($pageTexts);

        if ($coverText === '') {
            return ['mandatory' => [], 'additional' => ''];
        }

        $mandatory = $this->extractMandatory($coverText);
        $additional = $this->extractAdditional($coverText);

        return ['mandatory' => $mandatory, 'additional' => $additional];
    }

    /**
     * Concatenates pages up to (but not including) the page containing
     * "LIST OF VACANT POSITIONS", since everything from that point on is
     * position-block data, not cover-memo content. Falls back to using
     * all pages if that marker isn't found (better to over-include than
     * silently return nothing).
     */
    private function buildCoverText(array $pageTexts): string
    {
        $cutoffPage = null;
        foreach ($pageTexts as $page) {
            if (stripos($page['text'], 'LIST OF VACANT POSITIONS') !== false) {
                $cutoffPage = $page['number'];
                break;
            }
        }

        $text = '';
        foreach ($pageTexts as $page) {
            if ($cutoffPage !== null && $page['number'] >= $cutoffPage) {
                break;
            }
            $text .= "\n" . $page['text'];
        }

        return trim($text) !== '' ? $text : implode("\n", array_column($pageTexts, 'text'));
    }

    /**
     * Parses the flat A-J "Mandatory Requirements:" list into a clean
     * array of requirement strings, letters stripped.
     *
     * Stops at "Additional Requirements:" if present, otherwise at the
     * next all-caps section heading, otherwise end of cover text.
     */
    private function extractMandatory(string $coverText): array
    {
        if (!preg_match(
            '/Mandatory Requirements:?\s*(.*?)(?=Additional Requirements:|^\s*\d+\.\s+[A-Z]|\z)/ism',
            $coverText,
            $m
        )) {
            return [];
        }

        $block = $m[1];

        // Split on "LETTER." at the start of a line/segment — items are
        // formatted "A. <text>", "B. <text>", etc., confirmed real format.
        preg_match_all('/[A-J]\.\s+(.+?)(?=(?:[A-J]\.\s+)|\z)/s', $block, $itemMatches);

        $items = [];
        foreach ($itemMatches[1] as $raw) {
            $clean = trim(preg_replace('/\s+/', ' ', $raw));
            $clean = rtrim($clean, '. ');
            // Re-append the period if the source clearly ended a sentence
            // (most items do) — keep punctuation natural rather than
            // stripping it entirely.
            if ($clean !== '' && !str_ends_with($clean, '.')) {
                // Only add it back if it looks like a complete sentence,
                // not an OCR fragment — heuristic: ends in a letter, not
                // a dangling comma/colon.
                if (preg_match('/[a-zA-Z\)]$/', $clean)) {
                    $clean .= '.';
                }
            }
            if ($clean !== '') {
                $items[] = $clean;
            }
        }

        return $items;
    }

    /**
     * Extracts the "Additional Requirements:" section as a single
     * formatted text block, preserving its nested numbered/lettered/
     * dash structure as indentation rather than flattening it into a
     * simple list (which would lose meaningful hierarchy).
     *
     * Stops at the next major section boundary — confirmed real
     * boundary is paragraph "4." ("Folder of applicant shall be
     * labelled..."), which is a numbered memo paragraph, not part of
     * the requirements list itself.
     */
    private function extractAdditional(string $coverText): string
    {
        if (!preg_match(
            '/Additional Requirements:?\s*(.*?)(?=^\s*4\.\s+Folder of applicant|\z)/ism',
            $coverText,
            $m
        )) {
            return '';
        }

        $block = trim($m[1]);
        if ($block === '') {
            return '';
        }

        // Normalize excess whitespace per line but preserve line breaks,
        // since the nesting (1. / a. / dash bullets) is conveyed by
        // leading whitespace in the original OCR output and collapsing
        // everything to one paragraph would make it unreadable.
        $lines = preg_split('/\r\n|\r|\n/', $block);
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $cleanLines[] = $trimmed;
        }

        return implode("\n", $cleanLines);
    }
}
