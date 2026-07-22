<?php
/**
 * fix_wire_qualification_notice_signatories.php
 *
 * qualification-notice.blade.php's signature block just showed
 * $check['chair_name'] -- whatever HR manually typed into the
 * qualification check form for that one application, no fixed source
 * of truth, retyped every time. Wires in the configured
 * QualificationNoticeSignatory records instead (Signatories admin
 * page): each configured signatory renders as its own signature block
 * (name + position). Falls back to the existing $check['chair_name']
 * behavior if none have been configured yet, so this doesn't break
 * before HR sets one up.
 *
 * REQUIRES fix_remove_signatory_role.php already applied (this patch
 * assumes QualificationNoticeSignatory only has name/position, no
 * role_label).
 *
 * HOW TO RUN:
 *   php fix_wire_qualification_notice_signatories.php   (from project root)
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

echo "\n=== fix_wire_qualification_notice_signatories.php ===\n\n";

$path = ROOT . '/resources/views/pdf/qualification-notice.blade.php';

echo "[1] Adding signature-block style for multiple signatories...\n";

apply_patch(
    $path,
    '        .closing { margin-top: 30px; }
        .chair { margin-top: 46px; font-weight: bold; text-transform: uppercase; }',
    '        .closing { margin-top: 30px; }
        .chair { margin-top: 46px; font-weight: bold; text-transform: uppercase; }
        .chair .position { font-weight: normal; text-transform: none; font-size: 10.5pt; }
        .chair + .chair { margin-top: 34px; }',
    'qualification-notice.blade.php: add position sub-line + spacing style for signature blocks'
);

echo "\n[2] Wiring configured signatories into the signature block...\n";

apply_patch(
    $path,
    '    <div class="closing">
        Very truly yours,
        <div class="chair">{{ $check[\'chair_name\'] ?? \'[Sub-Committee Chair]\' }}</div>
    </div>',
    '    <div class="closing">
        Very truly yours,
        @php
            $qnSignatories = \App\Models\QualificationNoticeSignatory::all();
        @endphp
        @if ($qnSignatories->isNotEmpty())
            @foreach ($qnSignatories as $sig)
            <div class="chair">
                {{ strtoupper($sig->name) }}
                <div class="position">{{ $sig->position }}</div>
            </div>
            @endforeach
        @else
            {{-- No signatories configured yet at /signatories -- fall back
                 to whatever HR typed into the qualification check form. --}}
            <div class="chair">{{ $check[\'chair_name\'] ?? \'[Sub-Committee Chair]\' }}</div>
        @endif
    </div>',
    'qualification-notice.blade.php: signature block uses configured QualificationNoticeSignatory records'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The qualification notice PDF's signature block now pulls from\n";
echo "    every signatory configured under Qualification Notice Email on\n";
echo "    the /signatories page -- each renders as its own \"NAME /\n";
echo "    Position\" block, e.g. for committees with more than one\n";
echo "    signer.\n";
echo "  - If none are configured yet, it falls back to the existing\n";
echo "    manually-typed \$check['chair_name'] behavior -- nothing breaks\n";
echo "    before HR sets one up.\n\n";
echo "DELETE this script after running.\n";
