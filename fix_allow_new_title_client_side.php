<?php
/**
 * fix_allow_new_title_client_side.php
 *
 * The title search field blocks form submission client-side unless the
 * typed text exactly matches an EXISTING title in the list -- this ran
 * before fix_auto_register_new_job_title.php existed, back when any
 * title not already in config/job_titles.php genuinely couldn't be
 * saved. Now that the backend auto-registers a new title on submit,
 * this client-side gate needs to change from "must match an existing
 * title" to "must not be empty" -- otherwise a brand-new, genuinely
 * intended title (e.g. "Data Analyst") never even reaches the server
 * that would now accept it.
 *
 * REQUIRES fix_auto_register_new_job_title.php already applied on the
 * backend -- without it, this would let typed titles through client-side
 * only to still fail server-side Rule::in() validation.
 *
 * HOW TO RUN:
 *   php fix_allow_new_title_client_side.php   (from project root)
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

echo "\n=== fix_allow_new_title_client_side.php ===\n\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

echo "[1] Patching title field submit validation: allow new titles, block empty ones...\n";

apply_patch(
    $formPath,
    "        // Block submission if the typed text doesn't match an exact, valid title.
        searchInput.closest('form').addEventListener('submit', function (event) {
            if (!titles.includes(searchInput.value.trim())) {
                event.preventDefault();
                searchInput.classList.add('is-invalid');
                searchInput.focus();
                renderResults(searchInput.value);

                let feedback = wrapper.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.textContent = 'Please select a valid position title from the list.';
                    wrapper.appendChild(feedback);
                }
            } else {
                hiddenInput.value = searchInput.value.trim();
            }
        });",
    "        // A typed title that ISN'T already in the list is now allowed\n" .
    "        // through -- the backend auto-registers genuinely new titles on\n" .
    "        // submit (see JobPostingController::autoRegisterTitle()). Only\n" .
    "        // block submission if the field is empty.\n" .
    "        searchInput.closest('form').addEventListener('submit', function (event) {\n" .
    "            const value = searchInput.value.trim();\n" .
    "\n" .
    "            if (value === '') {\n" .
    "                event.preventDefault();\n" .
    "                searchInput.classList.add('is-invalid');\n" .
    "                searchInput.focus();\n" .
    "                renderResults(searchInput.value);\n" .
    "\n" .
    "                let feedback = wrapper.querySelector('.invalid-feedback');\n" .
    "                if (!feedback) {\n" .
    "                    feedback = document.createElement('div');\n" .
    "                    feedback.className = 'invalid-feedback d-block';\n" .
    "                    wrapper.appendChild(feedback);\n" .
    "                }\n" .
    "                feedback.textContent = 'Please enter a position title.';\n" .
    "            } else {\n" .
    "                hiddenInput.value = value;\n" .
    "                searchInput.classList.remove('is-invalid');\n" .
    "            }\n" .
    "        });",
    "form.blade.php: title field allows new (not-yet-listed) titles, only blocks empty"
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Typing a brand-new title (e.g. \"Data Analyst\") and submitting\n";
echo "    now goes through client-side -- it's no longer required to\n";
echo "    exactly match an existing entry in the dropdown.\n";
echo "  - An EMPTY title field still blocks submission, same as before.\n";
echo "  - The backend (fix_auto_register_new_job_title.php) then registers\n";
echo "    the new title permanently and creates the posting.\n\n";
echo "DELETE this script after running.\n";
