<?php
/**
 * fix_offer_button_and_email_column.php
 *
 * [1] "Offer beside candidate" -- adds a quick "Offer" button in the
 *     Candidate ranking table's action column (next to the existing
 *     Edit Scores pencil), so HR can generate a draft offer for that
 *     specific candidate directly from the ranking row, without
 *     scrolling down to the separate "Generate new offer" checklist in
 *     Offer Management. Submits straight to offers.store with just that
 *     one application_id -- same defaults (SG inherited from posting,
 *     Step 1) as the existing generate-offer form. Hidden if that
 *     candidate already has an offer, or if the posting is closed
 *     (matches the existing Edit Scores button's closed-posting guard).
 *
 * [2] Job offers table: "Compensation" column replaced with "Email",
 *     matching the request to show the candidate's email there instead.
 *
 * HOW TO RUN:
 *   php fix_offer_button_and_email_column.php   (from project root)
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

echo "\n=== fix_offer_button_and_email_column.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Offer button in the ranking table ───────────────────────────────

echo "[1] Adding Offer button next to each ranked candidate...\n";

apply_patch(
    $showPath,
    '                                <td>
                                    @if ($posting->status !== \'closed\')
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                            data-application-id="{{ $cand->application_id }}"
                                            data-candidate-name="{{ $cand->candidate_name }}"
                                            data-scores="{{ json_encode($cand->scores) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endif
                                </td>',
    '                                <td class="text-end">
                                    @php
                                        $rankAlreadyOffered = $offers->firstWhere(\'application_id\', $cand->application_id);
                                    @endphp
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if ($posting->status !== \'closed\')
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}"
                                                data-scores="{{ json_encode($cand->scores) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @endif
                                        @if ($rankAlreadyOffered)
                                        <span class="badge text-bg-light text-dark border align-self-center" style="font-size:.68rem;">Offer sent</span>
                                        @else
                                        <form method="POST" action="{{ route(\'offers.store\') }}" class="d-inline"
                                              onsubmit="return confirm(\'Generate a draft offer for {{ addslashes($cand->candidate_name) }} at SG {{ $posting->salary_grade }} Step 1?\')">
                                            @csrf
                                            <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                            <input type="hidden" name="application_ids[]" value="{{ $cand->application_id }}">
                                            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                                                <i class="bi bi-envelope-paper me-1"></i> Offer
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>',
    'show.blade.php: Offer button added next to each ranked candidate'
);

// ─── 2. Job offers table: Compensation column -> Email column ──────────

echo "\n[2] Replacing Compensation column with Email in Job offers table...\n";

apply_patch(
    $showPath,
    '                                <tr>
                                    <th>Candidate</th>
                                    <th>Compensation</th>
                                    <th>Sent</th>
                                    <th>Email delivery</th>
                                    <th>Response by</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = [\'draft\' => \'secondary\', \'sent\' => \'primary\', \'accepted\' => \'success\', \'declined\' => \'danger\', \'expired\' => \'dark\'];
                                @endphp
                                <tr>
                                    <td class="fw-medium">{{ $o->application->candidate->full_name ?? \'Unknown\' }}</td>
                                    <td>&#8369;{{ number_format($o->compensation, 2) }}</td>',
    '                                <tr>
                                    <th>Candidate</th>
                                    <th>Email</th>
                                    <th>Sent</th>
                                    <th>Email delivery</th>
                                    <th>Response by</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = [\'draft\' => \'secondary\', \'sent\' => \'primary\', \'accepted\' => \'success\', \'declined\' => \'danger\', \'expired\' => \'dark\'];
                                @endphp
                                <tr>
                                    <td class="fw-medium">{{ $o->application->candidate->full_name ?? \'Unknown\' }}</td>
                                    <td>{{ $o->application->candidate->email ?? \'—\' }}</td>',
    'show.blade.php: Job offers table Compensation column -> Email column'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Candidate ranking table: each row now has an \"Offer\" button\n";
echo "    beside the Edit Scores button, generating a draft offer for\n";
echo "    that candidate directly (SG inherited from the posting, Step\n";
echo "    1, same defaults as the existing generate-offer form). Once\n";
echo "    that candidate has any offer, the button is replaced with an\n";
echo "    \"Offer sent\" badge instead.\n";
echo "  - Job offers table now shows the candidate's Email instead of\n";
echo "    Compensation.\n\n";
echo "NOTE: server-side vacancy-limit enforcement in\n";
echo "JobOfferController::store() still applies -- if all vacancy slots\n";
echo "are already filled with active offers, clicking Offer here will\n";
echo "redirect back with the same \"No open offer slots remain\" error as\n";
echo "the existing form.\n\n";
echo "DELETE this script after running.\n";
