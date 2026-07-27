<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SalaryGradeTableParser
{
    /**
     * Pull circular_no / subject / effective_date out of the memo's text,
     * e.g. "NATIONAL BUDGET CIRCULAR No. 601" / "SUBJECT: Implementation of
     * the Third Tranche..." / "Effective January 1, 2026".
     *
     * Best-effort only -- staff can override any of these on the upload
     * form or afterward, so a miss here isn't fatal.
     */
    public function parseMeta(string $fullText): array
    {
        $circularNo = null;
        $subject = null;
        $effectiveDate = null;

        if (preg_match('/BUDGET\s+CIRCULAR\s*(?:NO\.?)?\s*[:\-]?\s*(\d+)/i', $fullText, $m)) {
            $circularNo = $m[1];
        }

        if (preg_match('/SUBJECT\s*:?\s*(.+)/i', $fullText, $m)) {
            $subject = trim(preg_replace('/\s+/', ' ', $m[1]));
            $subject = Str::limit($subject, 250);
        }

        if (preg_match('/Effective\s+([A-Za-z]+\s+\d{1,2},?\s*\d{4})/i', $fullText, $m)) {
            try {
                $effectiveDate = Carbon::parse($m[1])->toDateString();
            } catch (\Throwable) {
                $effectiveDate = null;
            }
        }

        return [
            'circularNo' => $circularNo,
            'subject' => $subject,
            'effectiveDate' => $effectiveDate,
        ];
    }

    /**
     * Parse "Salary Grade | Step 1 ... Step 8" rows out of pdftotext -layout
     * output. A row is: a 1-2 digit grade (1-33) followed by 2-8 peso
     * amounts. SG-33 legitimately has only 2 steps in the source circular,
     * so this doesn't require exactly 8.
     *
     * @param array $pageTexts [['number' => int, 'text' => string], ...]
     * @return array [['grade' => int, 'step' => int, 'amount' => float], ...]
     */
    public function parseTable(array $pageTexts): array
    {
        $rows = [];
        $seen = []; // grade => highest step already captured, dedupe across repeated pages/headers

        foreach ($pageTexts as $page) {
            foreach (preg_split('/\r\n|\r|\n/', $page['text']) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (!preg_match('/^(\d{1,2})\s+((?:[\d,]{3,}\s*){2,8})$/', $line, $m)) {
                    continue;
                }

                $grade = (int) $m[1];
                if ($grade < 1 || $grade > 33) {
                    continue;
                }

                preg_match_all('/[\d,]{3,}/', $m[2], $amountMatches);
                $amounts = $amountMatches[0];

                // Guard against stray matches (e.g. a page number that
                // happens to look like "1  2026" from a footer) -- a real
                // SG row has at least 2 amounts.
                if (count($amounts) < 2) {
                    continue;
                }

                foreach ($amounts as $i => $amt) {
                    $step = $i + 1;
                    $key = $grade . '-' . $step;
                    if (isset($seen[$key])) {
                        continue; // already captured this grade/step, skip duplicate
                    }
                    $seen[$key] = true;

                    $rows[] = [
                        'grade' => $grade,
                        'step' => $step,
                        'amount' => (float) str_replace(',', '', $amt),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Parse an uploaded .xlsx/.xls/.csv where column A is the salary grade
     * and the following columns are Step 1..Step N amounts (mirrors the
     * Annex A layout). Requires phpoffice/phpspreadsheet:
     *   composer require phpoffice/phpspreadsheet
     */
    public function parseTableFromSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $line) {
            $first = trim((string) ($line[0] ?? ''));
            if (!ctype_digit($first)) {
                continue; // header row or blank line
            }

            $grade = (int) $first;
            if ($grade < 1 || $grade > 33) {
                continue;
            }

            $step = 0;
            for ($col = 1; $col < count($line); $col++) {
                $raw = trim((string) ($line[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $clean = preg_replace('/[^\d.]/', '', $raw); // strip ₱, commas, spaces
                if ($clean === '' || !is_numeric($clean)) {
                    continue;
                }

                $step++;
                $rows[] = [
                    'grade' => $grade,
                    'step' => $step,
                    'amount' => (float) $clean,
                ];
            }
        }

        return $rows;
    }
}
