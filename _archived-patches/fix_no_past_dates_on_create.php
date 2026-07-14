<?php
/**
 * fix_no_past_dates_on_create.php
 *
 * Job posting create form currently allows "Posted" / "Closes" dates in
 * the past (rules() only checked closes_at >= posted_at, never checked
 * either against today). This adds a today-or-later constraint, but ONLY
 * on create() -- update() keeps the old rules so existing postings with
 * already-past dates can still be edited/saved without being rejected.
 *
 * HOW TO RUN:
 *   php fix_no_past_dates_on_create.php   (from project root)
 * DELETE this script after running.
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

echo "\n=== fix_no_past_dates_on_create.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// ─── 1. rules() gains a $forCreate flag ─────────────────────────────────

echo "[1] Patching rules() to accept a \$forCreate flag...\n";

apply_patch(
    $controllerPath,
    "    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::in(config('job_titles.titles', []))],
            'description' => ['nullable', 'string'],
            'duties_responsibilities' => ['nullable', 'string'],
            'qualification_education' => ['nullable', 'string'],
            'qualification_training' => ['nullable', 'string'],
            'qualification_experience' => ['nullable', 'string'],
            'qualification_eligibility' => ['nullable', 'string'],
            'mandatory_requirements' => ['nullable', 'string'],
            'additional_requirements' => ['nullable', 'string'],
            // place_of_assignment is now managed via job_posting_locations table
            'employment_type' => ['nullable', 'string', 'max:255'],
            'salary_grade' => [
                'nullable',
                'string',
                'max:50',
                function (\$attribute, \$value, \$fail) {
                    if (empty(\$value)) {
                        return;
                    }
                    \$numeric = preg_replace('/^sg-?/i', '', trim(\$value));
                    if (!ctype_digit(\$numeric) || (int) \$numeric < 1 || (int) \$numeric > 33) {
                        \$fail('The salary grade must be a valid Salary Grade from 1 to 33 (e.g. \"21\" or \"SG-21\").');
                    }
                },
            ],
            // vacancies is now per-location in job_posting_locations table
            'posted_at' => ['nullable', 'date'],
            'closes_at' => [
                'nullable',
                'date',
                Rule::when(
                    fn (\$input) => !empty(\$input['posted_at']),
                    ['after_or_equal:posted_at']
                ),
            ],
            'status' => ['required', 'in:open,interview_scheduled,ranking,closed'],
        ];
    }",
    "    private function rules(bool \$forCreate = false): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::in(config('job_titles.titles', []))],
            'description' => ['nullable', 'string'],
            'duties_responsibilities' => ['nullable', 'string'],
            'qualification_education' => ['nullable', 'string'],
            'qualification_training' => ['nullable', 'string'],
            'qualification_experience' => ['nullable', 'string'],
            'qualification_eligibility' => ['nullable', 'string'],
            'mandatory_requirements' => ['nullable', 'string'],
            'additional_requirements' => ['nullable', 'string'],
            // place_of_assignment is now managed via job_posting_locations table
            'employment_type' => ['nullable', 'string', 'max:255'],
            'salary_grade' => [
                'nullable',
                'string',
                'max:50',
                function (\$attribute, \$value, \$fail) {
                    if (empty(\$value)) {
                        return;
                    }
                    \$numeric = preg_replace('/^sg-?/i', '', trim(\$value));
                    if (!ctype_digit(\$numeric) || (int) \$numeric < 1 || (int) \$numeric > 33) {
                        \$fail('The salary grade must be a valid Salary Grade from 1 to 33 (e.g. \"21\" or \"SG-21\").');
                    }
                },
            ],
            // vacancies is now per-location in job_posting_locations table
            'posted_at' => [
                'nullable',
                'date',
                // Only enforced when creating a brand new posting -- editing
                // an existing posting must not break just because its
                // original dates are now in the past.
                ...(\$forCreate ? ['after_or_equal:today'] : []),
            ],
            'closes_at' => [
                'nullable',
                'date',
                ...(\$forCreate ? ['after_or_equal:today'] : []),
                Rule::when(
                    fn (\$input) => !empty(\$input['posted_at']),
                    ['after_or_equal:posted_at']
                ),
            ],
            'status' => ['required', 'in:open,interview_scheduled,ranking,closed'],
        ];
    }",
    'JobPostingController: rules() accepts $forCreate flag with after_or_equal:today'
);

// ─── 2. store() passes forCreate = true ─────────────────────────────────

echo "\n[2] Patching store() to request the create-only date rules...\n";

apply_patch(
    $controllerPath,
    'public function store(Request $request)
    {
        $validated = $request->validate($this->rules());',
    'public function store(Request $request)
    {
        $validated = $request->validate($this->rules(forCreate: true));',
    'JobPostingController: store() uses forCreate rules'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Creating a NEW job posting now rejects 'Posted' or 'Closes'\n";
echo "    dates earlier than today (validation error, same as any other\n";
echo "    field).\n";
echo "  - Editing an EXISTING posting is untouched -- old postings with\n";
echo "    already-past dates can still be saved normally.\n\n";
echo "NOTE: this is server-side only. If you want the date picker itself\n";
echo "to grey out past dates in the browser, the <input type=\"date\">\n";
echo "in resources/views/job-postings/form.blade.php needs a min\n";
echo "attribute (e.g. min=\"{{ now()->format('Y-m-d') }}\") -- send me\n";
echo "that file if you want that patched too.\n";
echo "DELETE this script after running.\n";
