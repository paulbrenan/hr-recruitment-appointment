<?php
/**
 * fix_edit_panelist_email.php
 *
 * Panelists already have click-to-rename inline editing for NAME, but
 * there was no way to edit EMAIL at all after a panelist was created --
 * it wasn't even displayed in the panelist list. Now that email matters
 * for actually sending schedule invitations, this extends the existing
 * inline editor to show + edit both fields together.
 *
 *   1. form.blade.php: adds an email display/input under the name in
 *      each panelist row, wired into the same click-to-edit pattern.
 *   2. PanelistController::update(): now accepts an optional 'email'
 *      field alongside the already-required 'name'.
 *
 * HOW TO RUN:
 *   php fix_edit_panelist_email.php   (from project root)
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

echo "\n=== fix_edit_panelist_email.php ===\n\n";

$formPath       = ROOT . '/resources/views/job-postings/form.blade.php';
$controllerPath = ROOT . '/app/Http/Controllers/PanelistController.php';

// ─── 1. Blade: show + edit email under name ─────────────────────────────

echo "[1] Adding email display/edit to each panelist row...\n";

apply_patch(
    $formPath,
    '                                        {{-- Name — click to edit inline --}}
                                        <div class="flex-grow-1 d-flex align-items-center gap-2" style="min-width: 0;">
                                            <span
                                                class="panelist-name-display small fw-medium"
                                                data-panelist-id="{{ $panelist->id }}"
                                                title="Click to rename"
                                                style="cursor: pointer; border-bottom: 1px dashed #adb5bd;"
                                            >{{ $panelist->name }}</span>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm panelist-name-input d-none"
                                                data-panelist-id="{{ $panelist->id }}"
                                                value="{{ $panelist->name }}"
                                                style="max-width: 220px;"
                                            >
                                            <span class="panelist-save-status small ms-1" data-panelist-id="{{ $panelist->id }}"></span>
                                        </div>',
    '                                        {{-- Name + email — click either to edit both inline --}}
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span
                                                    class="panelist-name-display small fw-medium"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    title="Click to edit"
                                                    style="cursor: pointer; border-bottom: 1px dashed #adb5bd;"
                                                >{{ $panelist->name }}</span>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm panelist-name-input d-none"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    value="{{ $panelist->name }}"
                                                    placeholder="Name"
                                                    style="max-width: 200px;"
                                                >
                                                <span class="panelist-save-status small ms-1" data-panelist-id="{{ $panelist->id }}"></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span
                                                    class="panelist-email-display text-muted"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    title="Click to edit"
                                                    style="cursor: pointer; font-size: 0.72rem; border-bottom: 1px dashed #adb5bd;"
                                                >{{ $panelist->email ?: \'No email set — click to add\' }}</span>
                                                <input
                                                    type="email"
                                                    class="form-control form-control-sm panelist-email-input d-none"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    value="{{ $panelist->email }}"
                                                    placeholder="Email"
                                                    style="max-width: 200px; font-size: 0.78rem;"
                                                >
                                            </div>
                                        </div>',
    'form.blade.php: add email display + input to panelist row'
);

// ─── 2. JS: click-to-edit for email, save() sends both fields ──────────

echo "\n[2] Rewiring inline editor JS to handle name + email together...\n";

apply_patch(
    $formPath,
    "    function savePanelistName(id, newName, statusEl, displayEl, inputEl) {
        if (!newName.trim() || newName.trim() === displayEl.textContent.trim()) {
            // Nothing changed — just switch back to display mode
            inputEl.classList.add('d-none');
            displayEl.classList.remove('d-none');
            statusEl.textContent = '';
            return;
        }

        statusEl.innerHTML = '<span class=\"text-muted\"><i class=\"bi bi-hourglass-split\"></i></span>';

        fetch('/panelists/' + id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name: newName.trim() }),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Server error ' + res.status);
            return res.json();
        })
        .then(function () {
            displayEl.textContent = newName.trim();
            // Update the delete button's data-panelist-name too
            const row = displayEl.closest('li');
            if (row) {
                const deleteBtn = row.querySelector('.panelist-delete-btn');
                if (deleteBtn) deleteBtn.dataset.panelistName = newName.trim();
            }
            inputEl.classList.add('d-none');
            displayEl.classList.remove('d-none');
            statusEl.innerHTML = '<span class=\"text-success\"><i class=\"bi bi-check-lg\"></i></span>';
            setTimeout(() => { statusEl.textContent = ''; }, 2000);
        })
        .catch(function () {
            statusEl.innerHTML = '<span class=\"text-danger small\">Save failed</span>';
            inputEl.focus();
        });
    }

    // Click on name display → switch to input
    document.querySelectorAll('.panelist-name-display').forEach(function (display) {
        display.addEventListener('click', function () {
            const id     = this.dataset.panelistId;
            const input  = document.querySelector('.panelist-name-input[data-panelist-id=\"' + id + '\"]');
            const status = document.querySelector('.panelist-save-status[data-panelist-id=\"' + id + '\"]');
            if (!input) return;

            display.classList.add('d-none');
            input.classList.remove('d-none');
            input.value = display.textContent.trim();
            input.focus();
            input.select();
        });
    });

    // Enter → save; Escape → cancel
    document.querySelectorAll('.panelist-name-input').forEach(function (input) {
        const id      = input.dataset.panelistId;
        const display = document.querySelector('.panelist-name-display[data-panelist-id=\"' + id + '\"]');
        const status  = document.querySelector('.panelist-save-status[data-panelist-id=\"' + id + '\"]');

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                savePanelistName(id, input.value, status, display, input);
            }
            if (e.key === 'Escape') {
                input.classList.add('d-none');
                display.classList.remove('d-none');
                status.textContent = '';
            }
        });

        input.addEventListener('blur', function () {
            // Small delay so Enter keydown fires first and doesn't double-save
            setTimeout(function () {
                if (!input.classList.contains('d-none')) {
                    savePanelistName(id, input.value, status, display, input);
                }
            }, 150);
        });
    });",
    "    function savePanelist(id, statusEl) {
        const nameInput  = document.querySelector('.panelist-name-input[data-panelist-id=\"' + id + '\"]');
        const nameDisplay = document.querySelector('.panelist-name-display[data-panelist-id=\"' + id + '\"]');
        const emailInput  = document.querySelector('.panelist-email-input[data-panelist-id=\"' + id + '\"]');
        const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id=\"' + id + '\"]');

        const newName  = nameInput.value.trim();
        const newEmail = emailInput.value.trim();

        if (!newName) {
            nameInput.focus();
            return;
        }

        const unchanged = newName === nameDisplay.textContent.trim()
            && newEmail === (emailDisplay.textContent.trim() === 'No email set — click to add' ? '' : emailDisplay.textContent.trim());

        if (unchanged) {
            nameInput.classList.add('d-none');
            nameDisplay.classList.remove('d-none');
            emailInput.classList.add('d-none');
            emailDisplay.classList.remove('d-none');
            statusEl.textContent = '';
            return;
        }

        statusEl.innerHTML = '<span class=\"text-muted\"><i class=\"bi bi-hourglass-split\"></i></span>';

        fetch('/panelists/' + id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name: newName, email: newEmail }),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Server error ' + res.status);
            return res.json();
        })
        .then(function () {
            nameDisplay.textContent = newName;
            emailDisplay.textContent = newEmail || 'No email set — click to add';
            const row = nameDisplay.closest('li');
            if (row) {
                const deleteBtn = row.querySelector('.panelist-delete-btn');
                if (deleteBtn) deleteBtn.dataset.panelistName = newName;
            }
            nameInput.classList.add('d-none');
            nameDisplay.classList.remove('d-none');
            emailInput.classList.add('d-none');
            emailDisplay.classList.remove('d-none');
            statusEl.innerHTML = '<span class=\"text-success\"><i class=\"bi bi-check-lg\"></i></span>';
            setTimeout(() => { statusEl.textContent = ''; }, 2000);
        })
        .catch(function () {
            statusEl.innerHTML = '<span class=\"text-danger small\">Save failed</span>';
        });
    }

    function enterEditMode(id) {
        const nameDisplay  = document.querySelector('.panelist-name-display[data-panelist-id=\"' + id + '\"]');
        const nameInput    = document.querySelector('.panelist-name-input[data-panelist-id=\"' + id + '\"]');
        const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id=\"' + id + '\"]');
        const emailInput   = document.querySelector('.panelist-email-input[data-panelist-id=\"' + id + '\"]');
        if (!nameInput || !emailInput) return;

        nameDisplay.classList.add('d-none');
        nameInput.classList.remove('d-none');
        nameInput.value = nameDisplay.textContent.trim();

        emailDisplay.classList.add('d-none');
        emailInput.classList.remove('d-none');
        emailInput.value = emailDisplay.textContent.trim() === 'No email set — click to add' ? '' : emailDisplay.textContent.trim();

        nameInput.focus();
        nameInput.select();
    }

    // Click on name OR email display → switch both to edit mode
    document.querySelectorAll('.panelist-name-display, .panelist-email-display').forEach(function (display) {
        display.addEventListener('click', function () {
            enterEditMode(this.dataset.panelistId);
        });
    });

    // Enter → save; Escape → cancel; Tab between name/email is native
    document.querySelectorAll('.panelist-name-input, .panelist-email-input').forEach(function (input) {
        const id     = input.dataset.panelistId;
        const status = document.querySelector('.panelist-save-status[data-panelist-id=\"' + id + '\"]');

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                savePanelist(id, status);
            }
            if (e.key === 'Escape') {
                const nameDisplay  = document.querySelector('.panelist-name-display[data-panelist-id=\"' + id + '\"]');
                const nameInput    = document.querySelector('.panelist-name-input[data-panelist-id=\"' + id + '\"]');
                const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id=\"' + id + '\"]');
                const emailInput   = document.querySelector('.panelist-email-input[data-panelist-id=\"' + id + '\"]');
                nameInput.classList.add('d-none');
                nameDisplay.classList.remove('d-none');
                emailInput.classList.add('d-none');
                emailDisplay.classList.remove('d-none');
                status.textContent = '';
            }
        });

        input.addEventListener('blur', function () {
            // Small delay so Enter keydown / focus-to-sibling-field fires first
            setTimeout(function () {
                const nameInput  = document.querySelector('.panelist-name-input[data-panelist-id=\"' + id + '\"]');
                const emailInput = document.querySelector('.panelist-email-input[data-panelist-id=\"' + id + '\"]');
                const stillEditing = document.activeElement === nameInput || document.activeElement === emailInput;
                if (!stillEditing && !nameInput.classList.contains('d-none')) {
                    savePanelist(id, status);
                }
            }, 150);
        });
    });",
    'form.blade.php: JS handles name + email together in one editor'
);

// ─── 3. PanelistController: accept optional email ──────────────────────

echo "\n[3] Patching PanelistController::update() to accept email...\n";

apply_patch(
    $controllerPath,
    "    public function update(Request \$request, \$id)
    {
        \$panelist = Panelist::findOrFail(\$id);
        \$request->validate(['name' => 'required|string|max:255']);
        \$panelist->update(['name' => \$request->input('name')]);",
    "    public function update(Request \$request, \$id)
    {
        \$panelist = Panelist::findOrFail(\$id);
        \$validated = \$request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        \$panelist->update([
            'name'  => \$validated['name'],
            'email' => \$validated['email'] ?? null,
        ]);",
    'PanelistController::update() -- accepts optional email'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Each panelist row now shows their email under their name (or\n";
echo "    'No email set — click to add' if empty).\n";
echo "  - Clicking either the name or email switches BOTH to edit mode\n";
echo "    together, and Enter/blur saves both in one request.\n";
echo "  - PanelistController::update() now accepts and saves email too.\n\n";
echo "DELETE this script after running.\n";
