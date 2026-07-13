<?php

/**
 * patch_panelist_inline_edit.php
 *
 * WHAT THIS DOES:
 *   Patches resources/views/job-postings/form.blade.php:
 *   - Makes panelist names inline-editable (click to edit, Enter/blur to save)
 *   - Saves via AJAX PUT /panelists/{id} — no full page reload needed
 *   - Shows a small spinner while saving, then a green tick on success
 *     or red text on failure
 *
 * HOW TO RUN:
 *   php patch_panelist_inline_edit.php     (from project root)
 *   No migration needed.
 *
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — pattern found $count times in $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_panelist_inline_edit.php ===\n\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

// ─── 1. Replace the static name label with an inline-editable version ──────

echo "[1] Patching panelist name label → inline edit...\n";

$oldNameLabel = <<<'BLADE'
                                        {{-- Name (editable inline) --}}
                                        <label class="form-check-label flex-grow-1 mb-0" for="panelist{{ $panelist->id }}" style="cursor: pointer;">
                                            <span class="panelist-name-display">{{ $panelist->name }}</span>
                                        </label>
BLADE;

$newNameLabel = <<<'BLADE'
                                        {{-- Name — click to edit inline --}}
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
                                        </div>
BLADE;

apply_patch($formPath, $oldNameLabel, $newNameLabel, 'form.blade.php: panelist name → inline edit UI');

// ─── 2. Add inline-edit JS after the existing panelist JS block ────────────

echo "\n[2] Adding inline-edit JS...\n";

$oldDeleteJs = <<<'BLADE'
    // Delete panelist from system (submits a hidden DELETE form via JS)
    document.querySelectorAll('.panelist-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = this.dataset.panelistName;
            const id   = this.dataset.panelistId;
            if (!confirm('Remove "' + name + '" from the panelist pool? This cannot be undone.')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/panelists/' + id;
            form.innerHTML = `
                @csrf
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });
BLADE;

$newDeleteJs = <<<'BLADE'
    // Delete panelist from system (submits a hidden DELETE form via JS)
    document.querySelectorAll('.panelist-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = this.dataset.panelistName;
            const id   = this.dataset.panelistId;
            if (!confirm('Remove "' + name + '" from the panelist pool? This cannot be undone.')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/panelists/' + id;
            form.innerHTML = `
                @csrf
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });

    // ── Inline panelist name editing ─────────────────────────────────────────
    const csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '{{ csrf_token() }}';

    function savePanelistName(id, newName, statusEl, displayEl, inputEl) {
        if (!newName.trim() || newName.trim() === displayEl.textContent.trim()) {
            // Nothing changed — just switch back to display mode
            inputEl.classList.add('d-none');
            displayEl.classList.remove('d-none');
            statusEl.textContent = '';
            return;
        }

        statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i></span>';

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
            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-lg"></i></span>';
            setTimeout(() => { statusEl.textContent = ''; }, 2000);
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger small">Save failed</span>';
            inputEl.focus();
        });
    }

    // Click on name display → switch to input
    document.querySelectorAll('.panelist-name-display').forEach(function (display) {
        display.addEventListener('click', function () {
            const id     = this.dataset.panelistId;
            const input  = document.querySelector('.panelist-name-input[data-panelist-id="' + id + '"]');
            const status = document.querySelector('.panelist-save-status[data-panelist-id="' + id + '"]');
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
        const display = document.querySelector('.panelist-name-display[data-panelist-id="' + id + '"]');
        const status  = document.querySelector('.panelist-save-status[data-panelist-id="' + id + '"]');

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
    });
BLADE;

apply_patch($formPath, $oldDeleteJs, $newDeleteJs, 'form.blade.php: inline-edit JS');

// ─── 3. PanelistController — return JSON for AJAX ─────────────────────────

echo "\n[3] Patching PanelistController@update to return JSON...\n";

$controllerPath = ROOT . '/app/Http/Controllers/PanelistController.php';

$oldUpdate = <<<'PHP'
    public function update(Request $request, $id)
    {
        $panelist = Panelist::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $panelist->update(['name' => $request->input('name')]);

        return redirect()->back()->with('success', 'Panelist name updated.');
    }
PHP;

$newUpdate = <<<'PHP'
    public function update(Request $request, $id)
    {
        $panelist = Panelist::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $panelist->update(['name' => $request->input('name')]);

        // Respond with JSON when called via AJAX (inline edit),
        // or redirect when called via a regular form submit.
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'name' => $panelist->name]);
        }

        return redirect()->back()->with('success', 'Panelist name updated.');
    }
PHP;

apply_patch($controllerPath, $oldUpdate, $newUpdate, 'PanelistController: update() returns JSON for AJAX');

echo <<<TEXT

✅ Done. No migration needed.

HOW IT WORKS:
  - Click a panelist name (dashed underline = clickable hint)
  - It becomes a text input
  - Press Enter or click away → saves via AJAX, no page reload
  - Hourglass while saving → green ✓ on success → fades after 2s
  - Press Escape to cancel without saving
  - On save failure, "Save failed" appears in red and input stays open

DELETE this script after running.

TEXT;
