/* ============================================
   Applications List — interactive polish (JS)
   Save to: public/js/applications-index-polish.js

   Purely additive: injects a client-side name/email search box next to
   the existing status filter. Doesn't touch the status <select>'s own
   onchange-submit behavior — just dims the table while it reloads.
   ============================================ */

(function () {
    const table = document.querySelector('.card table');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const tbody = table.querySelector('tbody');
    const filterForm = document.querySelector('form[action*="applications"]');

    // ── Dim the card while the status filter causes a page reload ──────────
    const card = table.closest('.card');
    const statusSelect = document.querySelector('select[name="status"]');
    if (statusSelect && card) {
        statusSelect.addEventListener('change', function () {
            card.classList.add('appidx-filtering');
        });
    }

    // ── Inject a quick search box next to the status filter ────────────────
    const filterBar = document.querySelector('.d-flex.justify-content-between.align-items-center.mb-3 .d-flex.gap-2');
    if (!filterBar) return;

    const searchWrap = document.createElement('div');
    searchWrap.className = 'appidx-search-wrap';
    searchWrap.innerHTML = `
        <i class="bi bi-search"></i>
        <input type="text" class="form-control form-control-sm" id="appidxSearchInput" placeholder="Quick search by name or email...">
    `;
    filterBar.insertAdjacentElement('afterbegin', searchWrap);

    const searchInput = document.getElementById('appidxSearchInput');
    const noResultsRow = document.createElement('tr');
    noResultsRow.innerHTML = '<td colspan="5" class="appidx-no-results">No applications match your search.</td>';

    searchInput.addEventListener('input', function () {
        const q = searchInput.value.trim().toLowerCase();
        let anyVisible = false;

        rows.forEach(function (row) {
            const haystack = row.textContent.toLowerCase();
            const matches = q === '' || haystack.includes(q);
            row.classList.toggle('appidx-row-hidden', !matches);
            if (matches) anyVisible = true;
        });

        if (!anyVisible && q !== '') {
            if (!tbody.contains(noResultsRow)) tbody.appendChild(noResultsRow);
        } else if (tbody.contains(noResultsRow)) {
            noResultsRow.remove();
        }
    });
})();
