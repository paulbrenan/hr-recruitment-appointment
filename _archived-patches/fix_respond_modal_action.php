<?php

/**
 * fix_respond_modal_action.php
 *
 * The respond modal form was submitting to /offers (POST) instead of
 * /offers/{id}/respond (PUT) because the form action wasn't being set.
 *
 * Fix: set the action directly on the <form> tag as a data-driven default
 * AND keep the JS setter — belt and suspenders.
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

echo "\n=== fix_respond_modal_action.php ===\n\n";

$path = ROOT . '/resources/views/offers/index.blade.php';

// 1. Give the form a placeholder action so it never falls back to /offers
apply_patch(
    $path,
    '<form method="POST" id="respondForm" action="">',
    '<form method="POST" id="respondForm" action="#" onsubmit="return document.getElementById(\'respondForm\').action !== \'#\'">',
    'offers/index.blade.php: respondForm — safe placeholder action'
);

// 2. Make sure the modal JS sets method spoofing input + action correctly
$oldModalJs = <<<'JS'
    document.getElementById('respondModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const offerId = button.getAttribute('data-offer-id');
        const response = button.getAttribute('data-response');

        document.getElementById('respondForm').action = '/offers/' + offerId + '/respond';
        document.getElementById('respondResponseInput').value = response;
        document.getElementById('respondModalTitle').textContent = response === 'accepted' ? 'Mark offer as accepted' : 'Mark offer as declined';
        document.getElementById('respondModalBody').textContent = response === 'accepted'
            ? 'This will mark the offer as accepted and update the application status accordingly.'
            : 'This will mark the offer as declined and update the application status accordingly.';
    });
JS;

$newModalJs = <<<'JS'
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
JS;

apply_patch($path, $oldModalJs, $newModalJs, 'offers/index.blade.php: respondModal JS — ensure action + _method set correctly');

echo "\n✅ Done. No migration needed. Delete this script.\n\n";
echo "If the error persists, also check that @method('PUT') is inside the modal form in the blade file.\n";
