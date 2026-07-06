<?php

namespace App\Services;

/**
 * JobTitleRegistrar
 *
 * Adds a new title to config/job_titles.php on disk, permanently, so it
 * becomes a real selectable option in the Job Title dropdown going
 * forward (not just held in memory for one import run).
 *
 * Why this exists: PositionBlockDetector used to silently strip a
 * Secondary/Elementary prefix and merge both variants into one generic
 * canonical title. This caused two real, confirmed problems:
 *   1. Data loss — "Secondary School Principal III" and "Elementary
 *      School Principal III" are genuinely DIFFERENT postings in the
 *      same memo, collapsed into one indistinguishable title.
 *   2. A save-blocking bug — if a posting's title (with prefix) was
 *      ever displayed/edited without ALSO existing in the dropdown's
 *      validated option list, editing that posting would fail
 *      Rule::in() validation.
 *
 * This class is the fix for (2): whenever the detector decides a
 * prefixed variant is real and new, it's registered here immediately,
 * so the dropdown (and its server-side Rule::in() validation) always
 * stays in sync with what's actually been imported.
 *
 * Safe to call repeatedly with the same title — checks for an existing
 * exact match first and no-ops if already present.
 */
class JobTitleRegistrar
{
    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? config_path('job_titles.php');
    }

    /**
     * Adds $title to config/job_titles.php's 'titles' array if it isn't
     * already present (exact match, case-sensitive — titles are stored
     * pre-normalized by the detector, e.g. "Secondary School Principal III").
     *
     * Writes the file back out preserving the same simple array format
     * used throughout the project, and clears Laravel's config cache
     * for this key so the change is picked up on the very next request
     * without requiring `php artisan config:clear` manually.
     */
    public function register(string $title): bool
    {
        $titles = config('job_titles.titles', []);

        if (in_array($title, $titles, true)) {
            return false; // already exists, nothing to do
        }

        $titles[] = $title;
        sort($titles, SORT_STRING);

        $this->writeConfigFile($titles);

        // Keep the in-memory config in sync for the rest of THIS request
        // (e.g. if confirm() validates against config('job_titles.titles')
        // later in the same request that just registered a new one).
        config(['job_titles.titles' => $titles]);

        return true;
    }

    /**
     * Adds multiple titles in one pass (e.g. when re-processing a whole
     * document's worth of blocks) — more efficient than calling
     * register() in a loop since it only writes the file once.
     *
     * @param string[] $titlesToAdd
     * @return string[] The titles that were actually newly added.
     */
    public function registerMany(array $titlesToAdd): array
    {
        $existing = config('job_titles.titles', []);
        $added = [];

        foreach ($titlesToAdd as $title) {
            if (!in_array($title, $existing, true) && !in_array($title, $added, true)) {
                $added[] = $title;
            }
        }

        if (empty($added)) {
            return [];
        }

        $merged = array_merge($existing, $added);
        sort($merged, SORT_STRING);

        $this->writeConfigFile($merged);
        config(['job_titles.titles' => $merged]);

        return $added;
    }

    private function writeConfigFile(array $titles): void
    {
        $lines = array_map(
            fn ($t) => "        " . var_export($t, true) . ",",
            $titles
        );

        $body = "<?php\n\n"
            . "// config/job_titles.php\n"
            . "// Canonical list of position titles for job postings, sourced from the\n"
            . "// official DepEd \"Position Applying For\" list. Used to populate the\n"
            . "// searchable title dropdown on the Job Posting create/edit form.\n"
            . "//\n"
            . "// Entries can be added automatically by JobTitleRegistrar when the PDF\n"
            . "// import detects a genuine new title variant (e.g. a Secondary/Elementary\n"
            . "// prefixed grade) that doesn't yet exist in this list.\n\n"
            . "return [\n"
            . "    'titles' => [\n"
            . implode("\n", $lines) . "\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->configPath, $body);
    }
}
