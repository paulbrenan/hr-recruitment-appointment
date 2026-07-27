<?php
/**
 * patch_fix_export_ier_salary_and_performance.php
 *
 * Fixes two problems with exportIER() (added by patch_add_export_ier.php):
 *
 * 1. Export hangs on a loading screen and never finishes.
 *    Root cause: font/alignment/border styling was applied PER ROW inside
 *    the applicant loop via repeated getStyle() calls. PhpSpreadsheet
 *    creates a new style object internally on every getStyle() call, so
 *    this scales very badly -- for any real applicant count it can take
 *    minutes, and since HTTP headers are already sent by the time
 *    streamDownload's callback runs, the browser just sits on a stalled
 *    download with no useful error shown. Fixed by applying styling to
 *    the whole data range ONCE after the loop finishes, instead of once
 *    per row.
 *
 * 2. Salary Grade shows but the monthly salary amount is missing.
 *    Root cause: the lookup only tried an EXACT match on the current
 *    budget circular + the parsed grade + step = 1, with no fallback --
 *    if nothing is marked as the current circular yet, or if Step 1
 *    specifically wasn't parsed/imported for that grade, it silently
 *    returned null. Fixed with a fallback chain: current circular's
 *    exact Step 1 -> current circular's LOWEST available step for that
 *    grade (in case Step 1 wasn't imported) -> the old hardcoded
 *    config('salary_grades.table') as a last resort (clearly comment-
 *    marked as legacy, only used if nothing in the database matches at
 *    all).
 *
 * IMPORTANT: run patch_add_export_ier.php first if you haven't already --
 * this patch only fixes the method it added, it doesn't create it.
 *
 * Run once from the project root:
 *   php patch_fix_export_ier_salary_and_performance.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched, or patch_add_export_ier.php hasn't\n");
        fwrite(STDERR, "        been run yet. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$controllerFile = __DIR__ . '/app/Http/Controllers/JobPostingController.php';

// ── 1. Monthly salary fallback chain ─────────────────────────────────

apply_patch(
    $controllerFile,
    <<<'OLD'
        $grade = $posting->salary_grade ? (int) preg_replace('/[^0-9]/', '', $posting->salary_grade) : null;
        $monthlySalary = null;
        $currentCircular = \App\Models\BudgetCircular::current()->first();
        if ($currentCircular && $grade) {
            $monthlySalary = \App\Models\SalaryGrade::where('budget_circular_id', $currentCircular->id)
                ->where('grade', $grade)
                ->where('step', 1)
                ->value('amount');
        }
OLD,
    <<<'NEW'
        $grade = $posting->salary_grade ? (int) preg_replace('/[^0-9]/', '', $posting->salary_grade) : null;
        $monthlySalary = null;
        $currentCircular = \App\Models\BudgetCircular::current()->first();

        if ($currentCircular && $grade) {
            // Try exact Step 1 first.
            $monthlySalary = \App\Models\SalaryGrade::where('budget_circular_id', $currentCircular->id)
                ->where('grade', $grade)
                ->where('step', 1)
                ->value('amount');

            // Step 1 specifically wasn't imported for this grade -- fall
            // back to whichever step IS available, lowest first.
            if ($monthlySalary === null) {
                $monthlySalary = \App\Models\SalaryGrade::where('budget_circular_id', $currentCircular->id)
                    ->where('grade', $grade)
                    ->orderBy('step')
                    ->value('amount');
            }
        }

        // Nothing in the database at all (no current circular yet, or
        // this grade was never imported) -- last-resort fallback to the
        // old hardcoded table so the export still shows SOMETHING.
        if ($monthlySalary === null && $grade) {
            $monthlySalary = config("salary_grades.table.{$grade}.0");
        }
NEW,
    'exportIER(): add monthly-salary fallback chain (exact Step 1 -> lowest available step -> legacy config)'
);

// ── 2. Batch styling once after the loop instead of per row ─────────

apply_patch(
    $controllerFile,
    <<<'OLD'
            foreach ($values as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
            }

            $sheet->getStyle('B' . $row . ':U' . $row)->getFont()->setName($font)->setSize(11);
            $sheet->getStyle('B' . $row . ':U' . $row)->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
            $sheet->getStyle('B' . $row . ':U' . $row)->getBorders()->getAllBorders()->setBorderStyle($thin);
            $sheet->getRowDimension($row)->setRowHeight(40.5);

            $row++;
        }
OLD,
    <<<'NEW'
            foreach ($values as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
            }

            $sheet->getRowDimension($row)->setRowHeight(40.5);

            $row++;
        }

        // Style the whole applicant-data range in ONE call instead of
        // once per row -- getStyle() allocates a new style object every
        // time it's called, so doing this per row scaled very badly and
        // was the actual cause of the export hanging for any real
        // applicant count.
        if ($row > 14) {
            $dataRange = 'B14:U' . ($row - 1);
            $sheet->getStyle($dataRange)->getFont()->setName($font)->setSize(11);
            $sheet->getStyle($dataRange)->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle($thin);
        }
NEW,
    'exportIER(): apply applicant-row styling once for the whole range instead of per row (fixes the hang)'
);

echo "\nDone. The IER export should now finish quickly regardless of applicant count, and\n";
echo "the monthly salary should show whenever a matching or fallback amount can be found.\n";
