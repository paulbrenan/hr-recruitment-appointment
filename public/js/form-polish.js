/* ============================================
   Review Imported Postings — interactive polish (JS)
   Save to: public/js/review-polish.js

   Purely additive: layered on top of review.blade.php's own inline
   script. Doesn't touch the existing add/remove location-row logic,
   the school search, or the fab-count submit logic — all of that
   keeps working exactly as-is.
   ============================================ */

(function () {
    // ── Dim a group's card when its checkbox is unchecked ───────────────────
    function syncExcludedState(checkbox) {
        const card = checkbox.closest('.candidate-row');
        if (!card) return;
        card.classList.toggle('review-excluded', !checkbox.checked);
    }

    document.querySelectorAll('.group-checkbox').forEach(function (cb) {
        syncExcludedState(cb); // set initial state
        cb.addEventListener('change', function () {
            syncExcludedState(cb);
        });
    });

    // ── Click a group's title to collapse/expand its card body ──────────────
    document.querySelectorAll('.candidate-row').forEach(function (card) {
        const header = card.querySelector('.card-header');
        const label = header ? header.querySelector('.fw-medium') : null;
        const body = card.querySelector('.card-body');
        if (!label || !body) return;

        const icon = document.createElement('i');
        icon.className = 'bi bi-chevron-down review-collapse-icon';
        header.appendChild(icon);

        label.addEventListener('click', function () {
            const collapsed = body.style.display === 'none';
            body.style.display = collapsed ? '' : 'none';
            icon.classList.toggle('is-collapsed', !collapsed);
        });
    });

    // ── Soft amber hint on location rows left blank ─────────────────────────
    // Purely visual — reads existing location-import-input values,
    // changes no form behavior or validation.
    function checkEmptyPlace(input) {
        input.classList.toggle('review-needs-place', input.value.trim() === '' && input !== document.activeElement);
    }

    document.addEventListener('blur', function (e) {
        if (e.target.matches && e.target.matches('.location-import-input')) {
            checkEmptyPlace(e.target);
        }
    }, true);

    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches('.location-import-input')) {
            e.target.classList.remove('review-needs-place');
        }
    });

    // Run an initial pass (e.g. rows restored via browser back/forward cache)
    document.querySelectorAll('.location-import-input').forEach(checkEmptyPlace);
})();