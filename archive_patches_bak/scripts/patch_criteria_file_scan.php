<?php
/**
 * patch_criteria_file_scan.php
 *
 * Run from the project root:
 *   php patch_criteria_file_scan.php
 *
 * What it does:
 *  app/Http/Controllers/AssessmentController.php
 *    - adds importCriteriaScan(Request): accepts a PDF/DOCX/XLSX/image
 *      upload, extracts its text (digital extraction first, OCR fallback
 *      for scanned/photographed pages), scans the text for the 8 known
 *      CSC merit-selection criteria names, and creates whichever ones are
 *      found (with their standard weight) that aren't already added and
 *      don't push the posting over 100%.
 *    - adds the private helper methods it needs: matchCriteriaCatalog(),
 *      extractTextFromSpreadsheet(), extractTextFromDocx(),
 *      extractTextFromPdf(), ocrPdf(), extractTextFromImage().
 *
 * Requirements already assumed present on this machine (same ones your
 * job-posting PDF import already depends on):
 *   - pdftotext and pdftoppm (Poppler utils)
 *   - tesseract (OCR)
 *   - PHP ext-zip (for reading .docx)
 *   - phpoffice/phpspreadsheet (already used for the Excel template)
 *
 * You still need to add ONE route yourself (routes/web.php not provided):
 *
 *   Route::post('/assessments/criteria/import-scan', [AssessmentController::class, 'importCriteriaScan'])
 *       ->name('assessments.criteria.import-scan');
 *
 * Put it anywhere in the assessments section — it's a POST to a path with
 * an extra segment, so it can't collide with the existing routes there.
 *
 * Safe to run multiple times: aborts with no changes if the expected
 * anchor isn't found exactly. A .bak copy is made before any write.
 */

$root = __DIR__;
$path = $root . '/app/Http/Controllers/AssessmentController.php';

if (!file_exists($path)) {
    echo "[SKIP] AssessmentController.php — file not found: $path\n";
    exit;
}

$content = file_get_contents($path);
$original = $content;

$anchor = <<<'ANCHOR'
    public function destroyAllCriteria(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $count = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->count();
        AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->delete();

        return back()->with('success', "Deleted all {$count} assessment criteria for this posting.");
    }
ANCHOR;

if (strpos($content, $anchor) === false) {
    echo "[ABORT] AssessmentController.php — expected destroyAllCriteria() anchor not found (run patch_delete_all_criteria.php first, or file has changed). No changes written.\n";
    exit;
}

$addition = <<<'NEW'


    /**
     * Scan an uploaded PDF/DOCX/XLSX/image for recognized assessment
     * criteria names and create whichever ones are found, with their
     * standard CSC merit-selection weight. Existing criteria and anything
     * that would push the posting over 100% weight are skipped.
     */
    public function importCriteriaScan(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'criteria_file'  => 'required|file|mimes:pdf,docx,xlsx,xls,jpg,jpeg,png|max:20480',
        ]);

        $file = $request->file('criteria_file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $tmpPath = $file->getRealPath();

        try {
            $text = match ($ext) {
                'xlsx', 'xls'        => $this->extractTextFromSpreadsheet($tmpPath),
                'docx'               => $this->extractTextFromDocx($tmpPath),
                'pdf'                => $this->extractTextFromPdf($tmpPath),
                'jpg', 'jpeg', 'png' => $this->extractTextFromImage($tmpPath),
                default              => '',
            };
        } catch (\Throwable $e) {
            Log::warning('Criteria scan extraction failed: ' . $e->getMessage());
            return back()->with('error', 'Could not read that file. Try a clearer scan or a digital copy.');
        }

        if (trim((string) $text) === '') {
            return back()->with('error', 'No readable text found in that file.');
        }

        $matches = $this->matchCriteriaCatalog($text);

        if (empty($matches)) {
            return back()->with('error', 'No recognized criteria names found in that file (Education, Training, Experience, Performance, Outstanding Accomplishments, Application of Education, Application of Learning and Development, Potential).');
        }

        $existingNames = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])
            ->pluck('name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();

        $existingWeight = (float) AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])
            ->sum('weight_percentage');

        $created = 0;
        $skippedExisting = 0;
        $skippedWeight = 0;

        foreach ($matches as $name => $weight) {
            if (in_array(strtolower($name), $existingNames, true)) {
                $skippedExisting++;
                continue;
            }
            if ($existingWeight + $weight > 100) {
                $skippedWeight++;
                continue;
            }

            AssessmentCriterion::create([
                'job_posting_id'    => $validated['job_posting_id'],
                'name'              => $name,
                'weight_percentage' => $weight,
            ]);

            $existingWeight += $weight;
            $created++;
        }

        $msg = "Scanned file: added {$created} criterion/criteria.";
        if ($skippedExisting > 0) $msg .= " Skipped {$skippedExisting} already added.";
        if ($skippedWeight   > 0) $msg .= " Skipped {$skippedWeight} that would exceed 100% weight.";

        return back()->with($created > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Matches known CSC merit-selection criteria names inside extracted
     * text and returns [canonical name => standard weight]. Multi-word
     * phrases are checked first and stripped from the working buffer so
     * e.g. "Application of Education" doesn't also register as a
     * standalone "Education" match.
     */
    private function matchCriteriaCatalog(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower($text));
        $result = [];

        $multiWord = [
            ['patterns' => ['application of learning and development', 'application of l&d', 'application of l & d'], 'name' => 'Application of Learning and Development', 'weight' => 10],
            ['patterns' => ['application of education'], 'name' => 'Application of Education', 'weight' => 10],
            ['patterns' => ['outstanding accomplishments', 'outstanding accomplishment'], 'name' => 'Outstanding Accomplishments', 'weight' => 10],
        ];

        foreach ($multiWord as $def) {
            foreach ($def['patterns'] as $p) {
                if (str_contains($normalized, $p)) {
                    $result[$def['name']] = $def['weight'];
                    $normalized = str_replace($p, ' ', $normalized);
                    break;
                }
            }
        }

        $singleWord = [
            'performance' => ['Performance', 25],
            'experience'  => ['Experience', 10],
            'training'    => ['Training', 10],
            'potential'   => ['Potential', 15],
            'education'   => ['Education', 10],
        ];

        foreach ($singleWord as $needle => [$name, $weight]) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/', $normalized)) {
                $result[$name] = $weight;
            }
        }

        return $result;
    }

    private function extractTextFromSpreadsheet(string $path): string
    {
        $spreadsheet = IOFactory::load($path);
        $text = '';
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->toArray() as $row) {
                $text .= implode(' ', array_map(fn($c) => (string) $c, $row)) . "\n";
            }
        }
        return $text;
    }

    private function extractTextFromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            return '';
        }
        $text = preg_replace('/<[^>]+>/', ' ', $xml);
        return html_entity_decode((string) $text);
    }

    private function extractTextFromPdf(string $path): string
    {
        $text = @shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($text && strlen(trim($text)) > 20) {
            return $text;
        }
        // Likely a scanned/photographed PDF -- OCR fallback, same tools
        // the job posting PDF import already relies on.
        return $this->ocrPdf($path);
    }

    private function ocrPdf(string $path): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'criteria_ocr_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $prefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
        shell_exec('pdftoppm -png -r 200 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>/dev/null');

        $text = '';
        foreach (glob($prefix . '*.png') as $img) {
            $text .= $this->extractTextFromImage($img) . "\n";
        }

        foreach (glob($tmpDir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($tmpDir);

        return $text;
    }

    private function extractTextFromImage(string $path): string
    {
        return (string) @shell_exec('tesseract ' . escapeshellarg($path) . ' stdout 2>/dev/null');
    }
NEW;

$content = str_replace($anchor, $anchor . $addition, $content);

if ($content === $original) {
    echo "[SKIP] AssessmentController.php — no changes needed.\n";
    exit;
}

$backup = $path . '.bak';
if (!file_exists($backup)) {
    copy($path, $backup);
} else {
    copy($path, $path . '.bak.' . date('Ymd_His'));
}

file_put_contents($path, $content);
echo "[OK] AssessmentController.php — patched. Backup at: $backup\n";
echo "Remember to add the route (see comment at top of this script) and the upload button (separate patch coming for the blade view).\n";
