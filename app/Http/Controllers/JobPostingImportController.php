<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\PdfImportBatch;
use App\Jobs\ProcessPdfImportJob;
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

        // Store the upload and let the queued job do OCR/parsing while the
        // PDF still exists on disk.
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

        // Atomically claim this batch so a duplicate/concurrent submission
        // (double-click, browser back+resubmit, retry, two tabs, etc.)
        // can't run the import loop twice. Only one request will see
        // $claimed > 0; any other request for the same batch is turned
        // away before it can create duplicate JobPosting rows.
        $claimed = PdfImportBatch::where('id', $batch->id)
            ->where('status', '!=', 'confirmed')
            ->update(['status' => 'confirmed']);

        if (!$claimed) {
            return redirect()
                ->route('job-postings.index')
                ->with('success', 'This import was already processed.');
        }

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
        $skipped = 0;

        // How far back to look when deciding a row is "the same import,
        // done again" rather than a genuinely new posting for the same
        // title/location. Re-running the same PDF (new upload, new batch
        // id) isn't caught by the confirm()-level lock above since it's a
        // different batch each time -- this is what actually protects
        // against that case.
        $recentWindow = now()->subHours(24);

        foreach ($editedRows as $index => $rowData) {
            if (!isset($selectedIndexes[$index])) {
                continue;
            }

            $title = $rowData['title'];
            $place = $rowData['place_of_assignment'] ?? null;

            $alreadyImported = JobPosting::where('title', $title)
                ->where(function ($query) use ($place) {
                    if ($place === null) {
                        $query->whereNull('place_of_assignment');
                    } else {
                        $query->where('place_of_assignment', $place);
                    }
                })
                ->where('created_at', '>=', $recentWindow)
                ->exists();

            if ($alreadyImported) {
                $skipped++;
                continue;
            }

            JobPosting::create([
                'title' => $title,
                'salary_grade' => $rowData['salary_grade'] ?? null,
                'qualification_education' => $rowData['qualification_education'] ?? null,
                'qualification_training' => $rowData['qualification_training'] ?? null,
                'qualification_experience' => $rowData['qualification_experience'] ?? null,
                'qualification_eligibility' => $rowData['qualification_eligibility'] ?? null,
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                'place_of_assignment' => $place,
                'mandatory_requirements' => $mandatoryText,
                'additional_requirements' => $additionalText,
                'vacancies' => max(1, (int) ($rowData['vacancies'] ?? 1)),
                'employment_type' => 'Regular',
                'status' => 'open',
            ]);

            $created++;
        }

        $batch->delete();

        $message = "Imported {$created} job posting(s) from PDF.";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} that matched postings already imported in the last 24 hours.";
        }

        return redirect()
            ->route('job-postings.index')
            ->with('success', $message);
    }
}