<?php
/**
 * fix_offer_nested_form_duplicate_ids.php
 *
 * Root cause of "The application_ids.0 field has a duplicate value":
 * the per-row single-candidate "Offer" button was its own <form>,
 * nested INSIDE the outer bulk <form id="generateOfferForm">. HTML does
 * not allow nested forms -- browsers silently drop the inner <form>
 * start tag during parsing, which reparents that row's hidden
 * application_ids[] input into the OUTER bulk form instead of keeping
 * it in its own isolated form.
 *
 * The practical effect: EVERY row's hidden application_ids[] (not just
 * the row you clicked "Offer" on) becomes part of the bulk form's
 * payload. Since each row's hidden input has the exact same value as
 * that row's checkbox, checking a candidate's box means that candidate's
 * application_id gets submitted TWICE in the same array -- once from
 * the checkbox, once from the orphaned hidden input -- which is exactly
 * what the 'distinct' validation rule is flagging.
 *
 * Fix: the per-row button is no longer a <form> at all. It's a plain
 * <button type="button"> that builds and submits its own standalone
 * form dynamically via JS on click -- fully isolated from the bulk
 * form's DOM, so nothing leaks between the two.
 *
 * Run once from the project root:
 *   php fix_offer_nested_form_duplicate_ids.php
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

// ── 1. Replace the nested per-row <form> with a plain button + JS ───────

$old1 = <<<'OLD'
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('offers.store') }}" class="d-inline"
                                              onsubmit="return confirm('Generate a draft offer for {{ addslashes($cand->candidate_name) }} at SG {{ $posting->salary_grade }} Step 1?')">
                                            @csrf
                                            <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                            <input type="hidden" name="application_ids[]" value="{{ $cand->application_id }}">
                                            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                                                <i class="bi bi-envelope-paper me-1"></i> Offer
                                            </button>
                                        </form>
                                    </td>
OLD;

$new1 = <<<'NEW'
                                    <td class="text-end">
                                        {{-- Deliberately NOT a <form> -- it used to be, nested inside the
                                             bulk #generateOfferForm above, which is invalid HTML (forms
                                             can't nest). Browsers silently drop the inner <form> tag and
                                             reparent its hidden application_ids[] input into the OUTER bulk
                                             form instead, so every row's hidden value rode along with the
                                             bulk submission and duplicated whatever was also checked. This
                                             button builds and submits its own isolated form via JS instead. --}}
                                        <button type="button" class="btn btn-sm offer-single-btn"
                                                style="background-color: var(--hr-primary); color: #fff;"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}">
                                            <i class="bi bi-envelope-paper me-1"></i> Offer
                                        </button>
                                    </td>
NEW;

apply_patch($showView, $old1, $new1, 'Step 5: replace nested per-row <form> with a plain button (removes invalid form nesting)');

// ── 2. Add the JS that builds + submits the standalone single-offer
//       form on click, isolated from the bulk form entirely ────────────

$old2 = <<<'OLD'
                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();

                        // SG override -> auto-fill the peso field with that
OLD;

$new2 = <<<'NEW'
                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();

                        // Per-row single-candidate "Offer" button. Builds a
                        // fully standalone form (not nested anywhere) and
                        // submits it directly -- isolated from the bulk
                        // #generateOfferForm above on purpose.
                        document.querySelectorAll('.offer-single-btn').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                const appId = btn.dataset.applicationId;
                                const candidateName = btn.dataset.candidateName;
                                if (!confirm('Generate a draft offer for ' + candidateName + ' at SG {{ $posting->salary_grade }} Step 1?')) {
                                    return;
                                }

                                const f = document.createElement('form');
                                f.method = 'POST';
                                f.action = '{{ route("offers.store") }}';
                                f.style.display = 'none';

                                const csrf = document.createElement('input');
                                csrf.type = 'hidden';
                                csrf.name = '_token';
                                csrf.value = '{{ csrf_token() }}';
                                f.appendChild(csrf);

                                const postingIdInput = document.createElement('input');
                                postingIdInput.type = 'hidden';
                                postingIdInput.name = 'job_posting_id';
                                postingIdInput.value = '{{ $posting->id }}';
                                f.appendChild(postingIdInput);

                                const appIdInput = document.createElement('input');
                                appIdInput.type = 'hidden';
                                appIdInput.name = 'application_ids[]';
                                appIdInput.value = appId;
                                f.appendChild(appIdInput);

                                document.body.appendChild(f);
                                f.submit();
                            });
                        });

                        // SG override -> auto-fill the peso field with that
NEW;

apply_patch($showView, $old2, $new2, 'Step 5: add JS to build+submit an isolated standalone form for the per-row Offer button');

echo "\nDone. The per-row Offer button no longer nests a <form> inside the bulk\n";
echo "generate-offer form, so checking a candidate's box (or clicking their\n";
echo "individual Offer button) no longer double-submits their application_id.\n";
