<?php
/**
 * fix_offer_table_applicant_clickable.php
 *
 * The Job Offers table in Offer Management (Step 5) renders the
 * candidate's name as plain text -- every other pipeline panel
 * (Qualification Checking, Scheduling, Assessment & Results) makes the
 * name clickable via showApplicantInfo(this), opening the shared
 * #applicantInfoModal. This just brings the offers table in line with
 * that same pattern, using $o->application in place of the ranking
 * panel's $rankApp/$rankCand.
 *
 * Run once from the project root:
 *   php fix_offer_table_applicant_clickable.php
 * Then delete this file.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$showView = __DIR__ . '/resources/views/job-postings/show.blade.php';

$old = <<<'OLD'
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = ['draft' => 'secondary', 'sent' => 'primary', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'dark'];
                                @endphp
                                <tr>
                                    <td class="fw-medium">{{ $o->application->candidate->full_name ?? 'Unknown' }}</td>
OLD;

$new = <<<'NEW'
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = ['draft' => 'secondary', 'sent' => 'primary', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'dark'];
                                    $offerApp = $o->application;
                                    $offerCand = $offerApp->candidate ?? null;
                                    $offerPlace = $offerApp ? (optional($offerApp->jobPostingLocation)->place_of_assignment ?? $posting->place_of_assignment ?? null) : null;
                                    $offerCheckData = $offerApp->qualification_check ?? [];
                                    $offerCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ock => $ocl) {
                                        if (isset($offerCheckData['criteria'][$ock])) {
                                            $offerCriteria[] = [
                                                'label' => $ocl,
                                                'actual' => $offerCheckData['criteria'][$ock]['actual'] ?? null,
                                                'passed' => (bool) ($offerCheckData['criteria'][$ock]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    $offerInfoData = [
                                        'name' => $offerCand->full_name ?? 'Unknown',
                                        'email' => $offerCand->email ?? null,
                                        'phone' => $offerCand->phone ?? null,
                                        'address' => $offerCand->address ?? null,
                                        'age' => $offerCand->age ?? null,
                                        'sex' => $offerCand->sex ?? null,
                                        'civil_status' => $offerCand->civil_status ?? null,
                                        'religion' => $offerCand->religion ?? null,
                                        'disability' => $offerCand->disability ?? null,
                                        'ethnic_group' => $offerCand->ethnic_group ?? null,
                                        'education' => $offerCand->education ?? null,
                                        'training_hours' => $offerCand->training_hours ?? null,
                                        'years_experience' => $offerCand->years_experience ?? null,
                                        'eligibility' => $offerCand->eligibility ?? null,
                                        'transaction_number' => $offerApp->transaction_number ?? null,
                                        'applied_at' => $offerApp && $offerApp->applied_at ? \Carbon\Carbon::parse($offerApp->applied_at)->format('M d, Y') : null,
                                        'status' => $offerApp ? str_replace('_', ' ', ucfirst($offerApp->status)) : null,
                                        'place_of_assignment' => $offerPlace,
                                        'notes' => $offerApp->notes ?? null,
                                        'qualification_result' => ($offerApp && $offerApp->qualification_result) ? ucfirst(str_replace('_', ' ', $offerApp->qualification_result)) : null,
                                        'criteria' => $offerCriteria,
                                    ];
                                @endphp
                                <tr>
                                    <td class="fw-medium">
                                        <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                              title="View applicant information"
                                              onclick="showApplicantInfo(this)"
                                              data-info="{{ json_encode($offerInfoData) }}">
                                            {{ $offerCand->full_name ?? 'Unknown' }}
                                        </span>
                                    </td>
NEW;

apply_patch($showView, $old, $new, 'Job Offers table: make applicant name clickable, opening the shared applicant info modal');

echo "\nDone. Applicant names in the Job Offers table now open the same info modal\n";
echo "used in the other pipeline panels.\n";
