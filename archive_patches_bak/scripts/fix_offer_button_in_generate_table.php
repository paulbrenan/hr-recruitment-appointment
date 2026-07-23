<?php
/**
 * fix_offer_button_in_generate_table.php
 *
 * Corrected placement -- supersedes fix_offer_button_and_email_column.php's
 * ranking-table button (don't run that script; use this one instead).
 *
 * [1] Adds a per-row "Offer" button as a new last column in the
 *     "Generate new offer" table (Offer Management step). Lets HR send
 *     a draft offer to just ONE candidate immediately, independent of
 *     the existing checkbox-based bulk form below it -- same SG-
 *     inherited-from-posting default, Step 1, no overrides. The
 *     existing checkbox + bulk "Generate offer" button flow is
 *     untouched for when HR wants to offer multiple candidates at once
 *     with a shared override/deadline.
 *
 * [2] Job offers table: "Compensation" column replaced with "Email".
 *
 * HOW TO RUN:
 *   php fix_offer_button_in_generate_table.php   (from project root)
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

echo "\n=== fix_offer_button_in_generate_table.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Add Offer column to the Generate new offer table ───────────────

echo "[1] Adding per-row Offer button to the Generate new offer table...\n";

apply_patch(
    $showPath,
    '                            <thead>
                                <tr>
                                    <th style="width:2.5rem;"></th>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Education</th>
                                    <th>Experience</th>
                                    <th>Eligibility</th>
                                </tr>
                            </thead>
                            <tbody id="offerCandidateRows">
                                @foreach ($eligibleOfferApplications as $cand)
                                <tr>
                                    <td>
                                        <input class="form-check-input offer-candidate-checkbox" type="checkbox"
                                               name="application_ids[]" value="{{ $cand->application_id }}"
                                               {{ in_array($cand->application_id, old(\'application_ids\', [])) ? \'checked\' : \'\' }}>
                                    </td>
                                    <td>
                                        @if ($cand->rank === 1)
                                            <span class="badge text-bg-warning">#1</span>
                                        @else
                                            <span class="text-muted">#{{ $cand->rank }}</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                                    <td>{{ $cand->candidate->education ?? \'—\' }}</td>
                                    <td>{{ $cand->candidate->years_experience ?? \'—\' }}{{ $cand->candidate->years_experience ? \' yrs\' : \'\' }}</td>
                                    <td>{{ $cand->candidate->eligibility ?? \'—\' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>',
    '                            <thead>
                                <tr>
                                    <th style="width:2.5rem;"></th>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Education</th>
                                    <th>Experience</th>
                                    <th>Eligibility</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="offerCandidateRows">
                                @foreach ($eligibleOfferApplications as $cand)
                                <tr>
                                    <td>
                                        <input class="form-check-input offer-candidate-checkbox" type="checkbox"
                                               name="application_ids[]" value="{{ $cand->application_id }}"
                                               {{ in_array($cand->application_id, old(\'application_ids\', [])) ? \'checked\' : \'\' }}>
                                    </td>
                                    <td>
                                        @if ($cand->rank === 1)
                                            <span class="badge text-bg-warning">#1</span>
                                        @else
                                            <span class="text-muted">#{{ $cand->rank }}</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                                    <td>{{ $cand->candidate->education ?? \'—\' }}</td>
                                    <td>{{ $cand->candidate->years_experience ?? \'—\' }}{{ $cand->candidate->years_experience ? \' yrs\' : \'\' }}</td>
                                    <td>{{ $cand->candidate->eligibility ?? \'—\' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route(\'offers.store\') }}" class="d-inline"
                                              onsubmit="return confirm(\'Generate a draft offer for {{ addslashes($cand->candidate_name) }} at SG {{ $posting->salary_grade }} Step 1?\')">
                                            @csrf
                                            <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                            <input type="hidden" name="application_ids[]" value="{{ $cand->application_id }}">
                                            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                                                <i class="bi bi-envelope-paper me-1"></i> Offer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>',
    'show.blade.php: Offer button column added to Generate new offer table'
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
echo "  - Generate new offer table now has a per-row \"Offer\" button --\n";
echo "    click it to send a draft offer to just that one candidate\n";
echo "    immediately (SG inherited from posting, Step 1). The existing\n";
echo "    checkbox + bulk \"Generate offer\" form below is untouched, for\n";
echo "    when HR wants to offer several candidates at once with a\n";
echo "    shared override/deadline.\n";
echo "  - Job offers table shows candidate Email instead of Compensation.\n\n";
echo "DELETE this script after running.\n";
