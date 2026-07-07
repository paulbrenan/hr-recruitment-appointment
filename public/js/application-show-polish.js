/* ============================================
   Application Details — interactive polish (JS)
   Save to: public/js/application-show-polish.js

   Purely additive: doesn't touch the status-update form, the
   qualification-check form submission, or any existing route/behavior.
   ============================================ */

(function () {
    // ── Transaction number: click to copy ───────────────────────────────────
    document.querySelectorAll('.font-monospace.fw-medium').forEach(function (el) {
        el.classList.add('appshow-txn');
        el.title = 'Click to copy';

        const badge = document.createElement('span');
        badge.className = 'appshow-copied-badge';
        badge.textContent = 'Copied!';
        el.insertAdjacentElement('afterend', badge);

        el.addEventListener('click', function () {
            navigator.clipboard.writeText(el.textContent.trim()).then(function () {
                badge.classList.add('show');
                setTimeout(function () { badge.classList.remove('show'); }, 1200);
            });
        });
    });

    // ── Pulse the qualification result badge once, on load, if present ─────
    const headerBadges = document.querySelectorAll('.card-header .badge');
    headerBadges.forEach(function (badge) {
        const text = badge.textContent.trim().toLowerCase();
        if (text === 'qualified' || text === 'disqualified') {
            badge.classList.add('appshow-result-pulse');
        }
    });

    // ── Tint each qualification criteria row green/red as it's marked ──────
    document.querySelectorAll('input[type="radio"][name$="_passed"]').forEach(function (radio) {
        function syncRowTint() {
            const row = radio.closest('.border-bottom');
            if (!row) return;
            row.classList.remove('appshow-criteria-pass', 'appshow-criteria-fail');
            const checked = row.querySelector('input[type="radio"]:checked');
            if (!checked) return;
            row.classList.add(checked.value === '1' ? 'appshow-criteria-pass' : 'appshow-criteria-fail');
        }
        radio.addEventListener('change', syncRowTint);
        if (radio.checked) syncRowTint();
    });

    // ── Inject a Print button next to "Update status" card header ──────────
    const candidateCard = document.querySelector('.col-md-4 .card .card-body.p-3.text-center');
    if (candidateCard) {
        const printBtn = document.createElement('button');
        printBtn.type = 'button';
        printBtn.className = 'btn btn-sm btn-outline-secondary w-100 mt-2 appshow-print-btn';
        printBtn.innerHTML = '<i class="bi bi-printer me-1"></i> Print application';
        printBtn.addEventListener('click', function () {
            window.print();
        });
        candidateCard.appendChild(printBtn);
    }
})();
