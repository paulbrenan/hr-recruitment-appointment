<?php

/**
 * fix_respond_modal_direct.php
 *
 * Root cause: the modal form relied on JS setting form.action before submit.
 * The @push('scripts') block wasn't loading in time (or at all), so the
 * form submitted to action="#" which Laravel routed as PUT /offers → 405.
 *
 * Fix: remove the single shared modal form. Instead, each offer row gets
 * its own inline accept/decline forms with Blade-rendered routes — zero JS
 * dependency for the submission itself.
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — pattern found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_respond_modal_direct.php ===\n\n";

$path = ROOT . '/resources/views/offers/index.blade.php';

// ─── 1. Replace Accept/Decline buttons with inline forms ──────────────────

echo "[1] Replacing Accept/Decline modal triggers with inline forms...\n";

$oldButtons = <<<'BLADE'
                            @elseif ($o->status === 'sent')
                            <button type="button" class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal" data-bs-target="#respondModal"
                                data-offer-id="{{ $o->id }}" data-response="accepted">
                                Accept
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#respondModal"
                                data-offer-id="{{ $o->id }}" data-response="declined">
                                Decline
                            </button>
BLADE;

$newButtons = <<<'BLADE'
                            @elseif ($o->status === 'sent')
                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                  onsubmit="return confirm('Mark this offer as accepted?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="response" value="accepted">
                                <button type="submit" class="btn btn-sm btn-outline-success">Accept</button>
                            </form>
                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                  onsubmit="return confirm('Mark this offer as declined?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="response" value="declined">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Decline</button>
                            </form>
BLADE;

apply_patch($path, $oldButtons, $newButtons, 'offers/index.blade.php: inline accept/decline forms');

// ─── 2. Remove the modal HTML entirely ────────────────────────────────────

echo "\n[2] Removing respond modal HTML...\n";

$oldModal = <<<'BLADE'
{{-- Respond to offer modal --}}
<div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="respondForm" action="#" onsubmit="return document.getElementById('respondForm').action !== '#'">
                @csrf
                @method('PUT')
                <input type="hidden" name="response" id="respondResponseInput">
                <div class="modal-header">
                    <h6 class="modal-title" id="respondModalTitle">Confirm response</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-0" id="respondModalBody"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>
BLADE;

apply_patch($path, $oldModal, '{{-- Respond modal removed — accept/decline now use inline forms per row --}}', 'offers/index.blade.php: remove modal HTML');

// ─── 3. Remove the modal JS from @push('scripts') ─────────────────────────

echo "\n[3] Removing modal JS (keeping SG preview JS)...\n";

$oldModalJs = <<<'BLADE'
    // ── Respond modal ────────────────────────────────────────────────────────
    document.getElementById('respondModal').addEventListener('show.bs.modal', function (event) {
        const button   = event.relatedTarget;
        const offerId  = button.getAttribute('data-offer-id');
        const response = button.getAttribute('data-response');

        const form = document.getElementById('respondForm');
        form.action = '/offers/' + offerId + '/respond';

        // Ensure the _method spoofing field is PUT
        let methodInput = form.querySelector('input[name="_method"]');
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type  = 'hidden';
            methodInput.name  = '_method';
            form.appendChild(methodInput);
        }
        methodInput.value = 'PUT';

        document.getElementById('respondResponseInput').value = response;
        document.getElementById('respondModalTitle').textContent  = response === 'accepted' ? 'Mark offer as accepted' : 'Mark offer as declined';
        document.getElementById('respondModalBody').textContent   = response === 'accepted'
            ? 'This will mark the offer as accepted and update the application status accordingly.'
            : 'This will mark the offer as declined and update the application status accordingly.';
    });
BLADE;

apply_patch($path, $oldModalJs, '    // Respond modal removed — no JS needed for accept/decline', 'offers/index.blade.php: remove modal JS');

echo <<<TEXT

✅ Done. No migration needed.

Accept and Decline now submit directly via their own inline forms with
Blade-rendered routes — no JS, no modal, no action-setting race condition.
A browser confirm() dialog still asks for confirmation before submitting.

DELETE this script after running.

TEXT;
