<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\PdfImportBatch;
use App\Services\PositionBlockDetector;
use App\Services\PositionBlockExpander;
use App\Services\RequirementsExtractor;
use Illuminate\Http\Request;

class JobPostingImportController extends Controller
{
    // ── Upload form ───────────────────────────────────────────────────────────
    public function create()
    {
        return view('job-postings.import.upload');
    }

    // ── OCR extraction + parsing pipeline ─────────────────────────────────────
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        // ── 1. Save uploaded PDF to a dedicated temp directory ────────────────
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hr_ocr_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            return back()->withErrors(['pdf_file' => 'Could not create temporary directory for processing.']);
        }

        $pdfPath      = $tmpDir . DIRECTORY_SEPARATOR . 'input.pdf';
        $originalName = $request->file('pdf_file')->getClientOriginalName();
        $request->file('pdf_file')->move($tmpDir, 'input.pdf');

        // ── 2. Convert PDF pages to PNG images via pdftoppm ──────────────────
        $imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';

        $pdftoppmCmd = sprintf(
            '"C:\\poppler\\Library\\bin\\pdftoppm.exe" -r 200 -png %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePrefix)
        );

        $pdftoppmOutput = shell_exec($pdftoppmCmd);
        $imageFiles     = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');

        if (empty($imageFiles)) {
            $this->cleanupTmp($tmpDir);
            return back()->withErrors([
                'pdf_file' => 'pdftoppm could not convert the PDF to images. '
                            . 'pdftoppm output: ' . ($pdftoppmOutput ?: '(none)'),
            ]);
        }

        natsort($imageFiles);
        $imageFiles = array_values($imageFiles);

        // ── 3. Run Tesseract on each page image ───────────────────────────────
        $pageTexts = [];

        foreach ($imageFiles as $index => $imagePath) {
            $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);

            $tesseractCmd = sprintf(
                '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng 2>&1',
                escapeshellarg($imagePath),
                escapeshellarg($outBase)
            );

            shell_exec($tesseractCmd);

            $txtFile = $outBase . '.txt';
            $text    = file_exists($txtFile) ? file_get_contents($txtFile) : '';

            $pageTexts[] = [
                'number' => $index + 1,
                'text'   => $text,
            ];
        }

        // ── 4. Clean up all temp files ────────────────────────────────────────
        $this->cleanupTmp($tmpDir);

        // ── 5. Parse OCR'd text into structured position blocks ──────────────
        $detector = new PositionBlockDetector(config('job_titles.titles', []));
        $blocks = $detector->detect($pageTexts);

        if (empty($blocks)) {
            return back()->withErrors([
                'pdf_file' => 'No recognizable position headings were found in this PDF. '
                            . 'It may not be a vacancy announcement in the expected format, '
                            . 'or OCR quality was too poor to detect headings.',
            ]);
        }

        // ── 5b. Track any newly-registered Secondary/Elementary title
        //        variants so the review screen can surface a notice —
        //        these are now PERMANENT entries in config/job_titles.php,
        //        not silently merged into a generic title as before.
        $newlyRegisteredTitles = collect($blocks)
            ->filter(fn ($b) => !empty($b['was_registered']))
            ->pluck('canonical_title')
            ->unique()
            ->values()
            ->all();

        // ── 6. Expand each block into flat per-row candidates ─────────────────
        $expander = new PositionBlockExpander();
        $candidates = $expander->expand($blocks);

        // ── 6b. Extract the REAL Mandatory/Additional Requirements from
        //        this document's cover-memo pages, instead of always
        //        falling back to the hardcoded standard A-J default.
        $requirementsExtractor = new RequirementsExtractor();
        $requirements = $requirementsExtractor->extract($pageTexts);

        // ── 7. Store as a temporary batch for the review screen ───────────────
        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => $candidates,
            'requirements' => $requirements,
            'newly_registered_titles' => $newlyRegisteredTitles,
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('job-postings.import.review', $batch->id);
    }

    // ── Review screen ─────────────────────────────────────────────────────────
    public function review($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $grouped = collect($batch->candidates)
            ->groupBy('group_key')
            ->map(function ($rows) {
                return [
                    'label' => $rows->first()['group_label'] ?? 'Untitled position',
                    'rows' => $rows->values(),
                ];
            });

        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
            'requirements' => $batch->requirements ?? ['mandatory' => [], 'additional' => ''],
            'newlyRegisteredTitles' => $batch->newly_registered_titles ?? [],
        ]);
    }

    // ── Confirm: bulk-create real job_postings from checked candidates ────────
    public function confirm(Request $request, $batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $validated = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
            'rows' => ['required', 'array'],
        ]);

        $selectedIndexes = array_flip($validated['selected'] ?? []);
        $editedRows = $validated['rows'];

        // Real requirements extracted from THIS document's cover memo
        // (Fix 2) — applied to every posting created from this import.
        // No more silent fallback to the old hardcoded standard A-J
        // default: if extraction found nothing for this particular PDF,
        // these fields are simply left null, same as any manually
        // created posting where HR hasn't filled them in yet.
        $extractedRequirements = $batch->requirements ?? ['mandatory' => [], 'additional' => ''];
        $mandatoryText = !empty($extractedRequirements['mandatory'])
            ? implode("\n", $extractedRequirements['mandatory'])
            : null;
        $additionalText = !empty($extractedRequirements['additional'])
            ? $extractedRequirements['additional']
            : null;

        $created = 0;

        foreach ($editedRows as $index => $rowData) {
            if (!isset($selectedIndexes[$index])) {
                continue;
            }

            JobPosting::create([
                'title' => $rowData['title'],
                'salary_grade' => $rowData['salary_grade'] ?? null,
                'qualification_education' => $rowData['qualification_education'] ?? null,
                'qualification_training' => $rowData['qualification_training'] ?? null,
                'qualification_experience' => $rowData['qualification_experience'] ?? null,
                'qualification_eligibility' => $rowData['qualification_eligibility'] ?? null,
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                'place_of_assignment' => $rowData['place_of_assignment'] ?? null,
                'mandatory_requirements' => $mandatoryText,
                'additional_requirements' => $additionalText,
                'vacancies' => max(1, (int) ($rowData['vacancies'] ?? 1)),
                'employment_type' => 'Regular',
                'status' => 'open',
            ]);

            $created++;
        }

        $batch->delete();

        return redirect()
            ->route('job-postings.index')
            ->with('success', "Imported {$created} job posting(s) from PDF.");
    }

    // ── Helper: recursively delete temp directory ─────────────────────────────
    private function cleanupTmp(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->cleanupTmp($path) : unlink($path);
        }
        rmdir($dir);
    }
}