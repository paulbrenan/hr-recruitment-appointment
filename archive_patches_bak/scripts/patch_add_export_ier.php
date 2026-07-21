<?php
/**
 * patch_add_export_ier.php
 *
 * Adds an "Export IER" (Initial Evaluation Result) button to Step 3 --
 * Open Ranking & Scheduling. Generates an .xlsx matching the DepEd
 * Annex D / Annex D-1 IER template exactly (merges, borders, fonts,
 * column widths) with every applicant on the posting filled in.
 *
 * Data sources:
 *   - Position: $posting->title
 *   - Salary Grade and Monthly Salary: numeric grade parsed from
 *     $posting->salary_grade, Step 1 monthly amount looked up directly
 *     from the salary_grades table for the CURRENT budget circular
 *     (BudgetCircular::current() + SalaryGrade where grade/step=1) --
 *     NOT config('salary_grades.table'), which is the old hardcoded
 *     source now superseded by the PDF/Excel import system.
 *   - Qualification Standards (Education/Training/Experience/
 *     Eligibility): $posting->qualification_education /
 *     _training / _experience / _eligibility
 *   - Per-applicant Education / Training-Title / Experience-Details /
 *     Eligibility: prefers the HR-verified text from
 *     application->qualification_check['criteria'][x]['actual']
 *     (entered during Qualification Checking), falling back to the
 *     candidate's raw self-reported field if that's not set.
 *   - Training-Hours / Experience-Years: candidate->training_hours /
 *     years_experience (no separate "actual" text exists for these,
 *     they're already numeric).
 *   - Remarks -> QS: application->qualification_result, mapped to
 *     "Qualified" / "Disqualified".
 *   - Remarks -> Performance (Met/Not Met): LEFT BLANK. This export
 *     happens at the Scheduling phase, before the interview/exam takes
 *     place, so there is no performance outcome yet -- matches how the
 *     blank Annex D template is meant to be filled in progressively.
 *   - "Prepared and certified correct by": current logged-in HR user's
 *     name, title hardcoded to "Human Resource Management Officer"
 *     (matches both sample files) -- adjust if your office uses a
 *     different standard title.
 *
 * Includes ALL applicants on the posting (both qualified and
 * disqualified), matching the IER's actual purpose -- it's meant to
 * show the initial evaluation result for everyone, not just those who
 * passed.
 *
 * Run once from the project root:
 *   php patch_add_export_ier.php
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
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
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
$routesFile     = __DIR__ . '/routes/web.php';
$showView       = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ── 1. Route ──────────────────────────────────────────────────────────

apply_patch(
    $routesFile,
    <<<'OLD'
Route::get('/job-postings/{id}/export-qualifications', [JobPostingController::class, 'exportQualifications'])->name('job-postings.export-qualifications');
OLD,
    <<<'NEW'
Route::get('/job-postings/{id}/export-qualifications', [JobPostingController::class, 'exportQualifications'])->name('job-postings.export-qualifications');
Route::get('/job-postings/{id}/export-ier', [JobPostingController::class, 'exportIER'])->name('job-postings.export-ier');
NEW,
    'Add route: GET /job-postings/{id}/export-ier'
);

// ── 2. Controller method ─────────────────────────────────────────────

apply_patch(
    $controllerFile,
    <<<'OLD'
    public function destroy($id)
OLD,
    <<<'NEW'
    /**
     * Export the DepEd Initial Evaluation Result (Annex D / Annex D-1)
     * for this posting, matching the official template exactly. See the
     * header comment in patch_add_export_ier.php for data-source notes.
     */
    public function exportIER($id)
    {
        $posting = JobPosting::findOrFail($id);

        $applications = Application::with('candidate')
            ->where('job_posting_id', $id)
            ->get()
            ->sortBy(fn ($a) => $a->candidate?->full_name ?? '')
            ->values();

        // Salary Grade + Step 1 monthly amount, from the CURRENT imported
        // schedule -- not the old hardcoded config table.
        $grade = $posting->salary_grade ? (int) preg_replace('/[^0-9]/', '', $posting->salary_grade) : null;
        $monthlySalary = null;
        $currentCircular = \App\Models\BudgetCircular::current()->first();
        if ($currentCircular && $grade) {
            $monthlySalary = \App\Models\SalaryGrade::where('budget_circular_id', $currentCircular->id)
                ->where('grade', $grade)
                ->where('step', 1)
                ->value('amount');
        }
        $sgLine = $grade
            ? 'SG ' . $grade . ($monthlySalary !== null ? ' - Php ' . number_format($monthlySalary, 0) : '')
            : '';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IER');

        $font = 'Bookman Old Style';
        $thin = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;

        // Column widths matching the template
        $widths = ['A' => 1.5, 'B' => 6, 'C' => 18.5, 'D' => 32, 'E' => 16, 'F' => 12, 'G' => 12, 'H' => 12,
            'I' => 12, 'J' => 14, 'K' => 12, 'L' => 20.8, 'M' => 18, 'N' => 19.5, 'O' => 15.5, 'P' => 9,
            'Q' => 17, 'R' => 9.8, 'S' => 14.4, 'T' => 18.3, 'U' => 18.8];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Header block
        $sheet->mergeCells('B2:U2');
        $sheet->setCellValue('B2', 'INITIAL EVALUATION RESULT (IER)');
        $sheet->getStyle('B2')->getFont()->setName($font)->setSize(20)->setBold(true);
        $sheet->getStyle('B2')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('B4', 'Position:   ' . $posting->title);
        $sheet->setCellValue('B5', 'Salary Grade and Monthly Salary:   ' . $sgLine);
        $sheet->setCellValue('B6', 'Qualification Standards:');
        $sheet->getStyle('B4:B6')->getFont()->setName($font)->setSize(18);

        $sheet->setCellValue('C7', 'Education');
        $sheet->setCellValue('D7', $posting->qualification_education);
        $sheet->setCellValue('C8', 'Training');
        $sheet->setCellValue('D8', $posting->qualification_training);
        $sheet->setCellValue('C9', 'Experience');
        $sheet->setCellValue('D9', $posting->qualification_experience);
        $sheet->setCellValue('C10', 'Eligibility');
        $sheet->setCellValue('D10', $posting->qualification_eligibility);
        $sheet->getStyle('C7:D10')->getFont()->setName($font)->setSize(18);

        // Table header row 1 (row 12)
        $headerFont = ['name' => $font, 'size' => 14, 'bold' => true];
        $headerCells = [
            'B12' => 'No.', 'C12' => 'Application Code', 'D12' => 'Names of Applicant',
            'E12' => 'Personal Information', 'N12' => 'Education', 'O12' => 'Training',
            'Q12' => 'Experience', 'S12' => 'Eligibility', 'T12' => 'Remarks',
        ];
        foreach ($headerCells as $coord => $val) {
            $sheet->setCellValue($coord, $val);
        }
        foreach (['B12:B13', 'C12:C13', 'D12:D13', 'E12:M12', 'N12:N13', 'O12:P12', 'Q12:R12', 'S12:S13', 'T12:U12'] as $range) {
            $sheet->mergeCells($range);
        }

        // Table header row 2 (row 13)
        $subHeaderCells = [
            'E13' => 'Address', 'F13' => 'Age', 'G13' => 'Sex', 'H13' => 'Civil Status',
            'I13' => 'Religion', 'J13' => 'Disability', 'K13' => 'Ethnic Group',
            'L13' => 'Email Address', 'M13' => 'Contact No. ',
            'O13' => 'Title', 'P13' => 'Hours', 'Q13' => 'Details', 'R13' => 'Years',
            'T13' => "QS\n(Qualified or Disqualified)", 'U13' => "Performance\n(Met or\nNot Met)",
        ];
        foreach ($subHeaderCells as $coord => $val) {
            $sheet->setCellValue($coord, $val);
        }

        $sheet->getStyle('B12:U13')->getFont()->setName($font)->setSize(14)->setBold(true);
        $sheet->getStyle('B12:U13')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $sheet->getStyle('B12:U13')->getBorders()->getAllBorders()->setBorderStyle($thin);
        $sheet->getRowDimension(12)->setRowHeight(18.6);
        $sheet->getRowDimension(13)->setRowHeight(59.45);

        // Applicant rows
        $row = 14;
        foreach ($applications as $i => $app) {
            $cand = $app->candidate;
            $check = $app->qualification_check ?? [];
            $criteria = $check['criteria'] ?? [];

            $education = $criteria['education']['actual'] ?? $cand?->education;
            $trainingTitle = $criteria['training']['actual'] ?? null;
            $experienceDetails = $criteria['experience']['actual'] ?? null;
            $eligibility = $criteria['eligibility']['actual'] ?? $cand?->eligibility;

            $qsRemark = match ($app->qualification_result) {
                'qualified' => 'Qualified',
                'not_qualified' => 'Disqualified',
                default => $app->qualification_result ? ucfirst(str_replace('_', ' ', $app->qualification_result)) : '',
            };

            $values = [
                'B' => $i + 1,
                'C' => $app->transaction_number,
                'D' => $cand?->full_name,
                'E' => $cand?->address,
                'F' => $cand?->age,
                'G' => $cand?->sex,
                'H' => $cand?->civil_status,
                'I' => $cand?->religion,
                'J' => $cand?->disability,
                'K' => $cand?->ethnic_group,
                'L' => $cand?->email,
                'M' => $cand?->phone,
                'N' => $education,
                'O' => $trainingTitle,
                'P' => $cand?->training_hours,
                'Q' => $experienceDetails,
                'R' => $cand?->years_experience,
                'S' => $eligibility,
                'T' => $qsRemark,
                'U' => '', // Performance -- filled in by hand after the interview
            ];
            foreach ($values as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
            }

            $sheet->getStyle('B' . $row . ':U' . $row)->getFont()->setName($font)->setSize(11);
            $sheet->getStyle('B' . $row . ':U' . $row)->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
            $sheet->getStyle('B' . $row . ':U' . $row)->getBorders()->getAllBorders()->setBorderStyle($thin);
            $sheet->getRowDimension($row)->setRowHeight(40.5);

            $row++;
        }

        // Footer -- "Prepared and certified correct by"
        $footerStart = $row + 2;
        $sheet->setCellValue('O' . $footerStart, 'Prepared and certified correct by:');
        $sheet->setCellValue('O' . ($footerStart + 3), strtoupper(auth()->user()->name ?? ''));
        $sheet->setCellValue('O' . ($footerStart + 4), 'Human Resource Management Officer');
        $sheet->setCellValue('O' . ($footerStart + 5), 'Date: _______________');
        $sheet->getStyle('O' . $footerStart . ':O' . ($footerStart + 5))->getFont()->setName($font)->setSize(18);
        $sheet->getStyle('O' . ($footerStart + 3))->getFont()->setBold(true);

        // Footer -- HRMO notes
        $notesStart = $footerStart + 7;
        $sheet->setCellValue('B' . $notesStart, 'Notes and Instructions for the HRMO:');
        $sheet->setCellValue('B' . ($notesStart + 1), 'a) For the purpose of posting the IER, columns D to M shall be concealed in accordance with RA No. 10163 (Data Privacy Act). The only information that shall be made public are the ');
        $sheet->setCellValue('B' . ($notesStart + 2), 'application codes, qualifications of the applicants in terms of Education, Training, Experience, Eligibility, and Competency (if applicable), and remark on whether Qualified or Disqualified');
        $sheet->setCellValue('B' . ($notesStart + 3), 'b) If the information does not apply to the applicant, please put N/A.');
        $sheet->getStyle('B' . $notesStart)->getFont()->setName($font)->setSize(11)->setBold(true);
        $sheet->getStyle('B' . ($notesStart + 1) . ':B' . ($notesStart + 3))->getFont()->setName($font)->setSize(11);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $safeTitle = preg_replace('/[^A-Za-z0-9]+/', '-', $posting->title);
        $filename = 'IER-' . $safeTitle . '-' . now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy($id)
NEW,
    'Add exportIER() method matching the DepEd Annex D/D-1 template'
);

// ── 3. "Export IER" button in Step 3 ─────────────────────────────────

apply_patch(
    $showView,
    <<<'OLD'
                            <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                    data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                                <i class="bi bi-plus-lg me-1"></i> New schedule
                            </button>
OLD,
    <<<'NEW'
                            <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                    data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                                <i class="bi bi-plus-lg me-1"></i> New schedule
                            </button>
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
NEW,
    'Add "Export IER" button next to "New schedule" in Step 3'
);

echo "\nDone. An 'Export IER' button now appears in Step 3 (Open Ranking & Scheduling),\n";
echo "downloading an .xlsx matching the DepEd Annex D/D-1 template with every applicant\n";
echo "on the posting filled in.\n";
