/* ============================================
   Job Postings — interactive polish (JS)
   Save to: public/js/jobpostings-index-polish.js

   Purely additive: reads the existing stat cards' label text and the
   existing status badges' text to wire up filtering — no Blade/PHP
   changes, no new data-attributes required in the markup.
   ============================================ */

(function () {
    const statCards = Array.from(document.querySelectorAll('.row.mb-3.g-2 > div > .card.p-3'));
    const rows = Array.from(document.querySelectorAll('.posting-row'));
    if (!statCards.length || !rows.length) return;

    // Map each card's visible label to the status value used in the badge
    // text (matches DashboardController-style labels already rendered).
    const labelToStatus = {
        'open': 'open',
        'screening': 'screening',
        'interview': 'interview_scheduled',
        'ranking': 'ranking',
        'closed': 'closed',
    };

    function rowStatusLabel(row) {
        const badge = row.querySelector('.badge-status');
        return badge ? badge.textContent.trim().toLowerCase() : '';
    }

    let activeFilter = null;

    // Build a small "no results" row, inserted/removed as needed
    const table = rows[0].closest('table');
    const tbody = rows[0].closest('tbody');
    const noResultsRow = document.createElement('tr');
    noResultsRow.innerHTML = '<td colspan="8" class="jp-no-results">No postings match this filter.</td>';

    function applyFilter(status) {
        let anyVisible = false;
        rows.forEach(function (row) {
            const label = rowStatusLabel(row);
            const matches = !status || label.includes(status);
            row.classList.toggle('jp-row-hidden', !matches);
            if (matches) anyVisible = true;
        });

        if (!anyVisible && status) {
            if (!tbody.contains(noResultsRow)) tbody.appendChild(noResultsRow);
        } else if (tbody.contains(noResultsRow)) {
            noResultsRow.remove();
        }
    }

    statCards.forEach(function (card) {
        const labelEl = card.querySelector('.text-muted.small');
        if (!labelEl) return;
        const label = labelEl.textContent.trim().toLowerCase();

        // "Total vacancies" isn't a status — skip making it a filter toggle
        if (!(label in labelToStatus)) return;

        card.addEventListener('click', function () {
            const isActive = card.classList.contains('jp-stat-active');
            statCards.forEach(function (c) { c.classList.remove('jp-stat-active'); });

            if (isActive) {
                activeFilter = null;
                applyFilter(null);
            } else {
                card.classList.add('jp-stat-active');
                activeFilter = label;
                applyFilter(label);
            }
        });
    });
})();
