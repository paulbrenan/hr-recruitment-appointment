<?php
/**
 * patch_wire_requirements_extractor.php
 *
 * ROOT CAUSE: ProcessPdfImportJob::handle() never called
 * RequirementsExtractor::extract() and never set 'requirements' on the
 * batch. JobPostingImportController::confirm() reads
 * $batch->requirements ?? ['mandatory' => [], 'additional' => ''] — since
 * requirements was never set, that fallback fired on EVERY import, meaning
 * both mandatory_requirements and additional_requirements have been empty
 * on every PDF-imported posting since this pipeline stage was added (the
 * confirm() comment references a "Fix 2" that was supposed to have wired
 * this up, but the actual extraction call never made it into this job).
 *
 * FIX: call RequirementsExtractor::extract($pageTexts) right after OCR text
 * extraction, and persist the result onto the batch alongside candidates/
 * status/memo_pdf_path.
 *
 * HOW TO RUN:
 *   php patch_wire_requirements_extractor.php   (from project root)
 * DELETE this script after running.
 *
 * AFTER RUNNING: existing imported postings created before this fix will
 * still have empty additional_requirements (and possibly empty
 * mandatory_requirements too) — this only fixes NEW imports going forward.
 * Let me know if you want a separate one-off script to backfill existing
 * postings from their stored memo_pdf_path.
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

echo "\n=== patch_wire_requirements_extractor.php ===\n\n";

$jobPath = ROOT . '/app/Jobs/ProcessPdfImportJob.php';

// 1. Import RequirementsExtractor
apply_patch(
    $jobPath,
    "use App\Services\PositionBlockDetector;
use App\Services\PositionBlockExpander;",
    "use App\Services\PositionBlockDetector;
use App\Services\PositionBlockExpander;
use App\Services\RequirementsExtractor;",
    'Add RequirementsExtractor import'
);

// 2. Extract requirements right after $pageTexts is available, and include
//    them in the batch update.
apply_patch(
    $jobPath,
    "            \$expander = new PositionBlockExpander();
            \$candidates = \$expander->expand(\$blocks);",
    "            \$expander = new PositionBlockExpander();
            \$candidates = \$expander->expand(\$blocks);

            // Extract mandatory + additional requirements from this same
            // memo's cover-page text. Previously this was never called, so
            // \$batch->requirements stayed null and confirm() silently fell
            // back to empty mandatory/additional requirements for every
            // imported posting.
            \$requirements = (new RequirementsExtractor())->extract(\$pageTexts);",
    'Call RequirementsExtractor::extract() after OCR text extraction'
);

apply_patch(
    $jobPath,
    "            \$batch->update([
                'candidates' => self::sanitizeUtf8(\$candidates),
                'status' => 'ready',
                'memo_pdf_path' => \$memoPdfPath,
            ]);",
    "            \$batch->update([
                'candidates' => self::sanitizeUtf8(\$candidates),
                'requirements' => self::sanitizeUtf8(\$requirements),
                'status' => 'ready',
                'memo_pdf_path' => \$memoPdfPath,
            ]);",
    "Persist \$requirements onto the batch"
);

echo "\n✅ Done.\n\n";
echo "Next PDF import should now carry both mandatory AND additional\n";
echo "requirements through to the created job posting(s).\n\n";
echo "Quick sanity check to confirm the regression's scope on EXISTING\n";
echo "postings (run in tinker):\n";
echo "  App\\Models\\JobPosting::whereNotNull('memo_pdf_path')\n";
echo "      ->select('id','title','mandatory_requirements','additional_requirements')\n";
echo "      ->get();\n\n";
echo "DELETE this script after running.\n";
