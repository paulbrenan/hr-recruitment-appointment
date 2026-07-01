<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\PdfImportBatch;
use App\Jobs\ProcessPdfImportJob;
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
    // This just saves the upload and queues the actual OCR work as a
    // background job, returning immediately instead of blocking the request
    // for 25-45+ seconds. See app/Jobs/ProcessPdfImportJob.php for the work,
    // and status()/processing.blade.php below for the polling progress page.
    //
    // IMPORTANT: this method must NOT touch pdftoppm/Tesseract, and must NOT
    // delete $pdfPath. It used to do its own full synchronous OCR pass here
    // (pdftoppm -> Tesseract -> PositionBlockDetector -> PositionBlockExpander),
    // then deleted the tmp dir the PDF lived in, THEN dispatched
    // ProcessPdfImportJob pointing at that now-deleted path -- so the queued
    // job would always immediately fail with "Uploaded PDF file could not be
    // found for processing", no matter how the synchronous pass above it went.
    // All of that work already correctly happens inside ProcessPdfImportJob;
    // this method's only job is to persist the upload somewhere that will
    // still exist when the job runs, and hand off.
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

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

        // ── 6. Expand each block into flat per-row candidates ─────────────────
        $expander = new PositionBlockExpander();
        $candidates = $expander->expand($blocks);

        // ── 7. Store as a temporary batch for the review screen ───────────────
        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => [],
            'expires_at' => now()->addDay(),
            'status' => 'processing',
            'pdf_path' => $pdfPath,
        ]);

        ProcessPdfImportJob::dispatch($batch->id);

        return redirect()->route('job-postings.import.processing', $batch->id);
    }

    // ── Processing screen: shown immediately, polls status() until ready ──────
    public function processing($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        if ($batch->status === 'ready') {
            return redirect()->route('job-postings.import.review', $batch->id);
        }

        return view('job-postings.import.processing', compact('batch'));
    }

    // ── JSON status endpoint, polled by the processing screen ─────────────────
    public function status($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        return response()->json([
            'status' => $batch->status,
            'error_message' => $batch->error_message,
        ]);
    }

    // ── Review screen ─────────────────────────────────────────────────────────
    public function review($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        if ($batch->status === 'processing') {
            return redirect()->route('job-postings.import.processing', $batch->id);
        }

        if ($batch->status === 'failed') {
            return redirect()
                ->route('job-postings.import.create')
                ->withErrors(['pdf_file' => $batch->error_message ?? 'PDF processing failed.']);
        }

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
}