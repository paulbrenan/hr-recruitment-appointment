/* ============================================
   Job Posting Detail — interactive polish (JS)
   Save to: public/js/jobposting-show-polish.js

   Purely additive: re-colors the applications table's status badges
   (they render as one flat text-bg-info badge in show.blade.php) and
   injects a Print button next to the existing Edit button.
   ============================================ */

(function () {
    // ── Color-code the applications table's status badges by their text ────
    document.querySelectorAll('table').forEach(function (table) {
        const headerCells = Array.from(table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.trim().toLowerCase();
        });
        if (!headerCells.includes('status') || !headerCells.includes('candidate')) return;

        table.querySelectorAll('tbody .badge').forEach(function (badge) {
            const key = badge.textContent.trim().toLowerCase().replace(/\s+/g, '_');
            badge.classList.add('jps-status-' + key);
        });
    });

    // ── Inject a Print button next to the existing Edit button ─────────────
    const editBtn = document.querySelector('a.btn.btn-outline-secondary[href*="/edit"]');
    if (editBtn && editBtn.parentElement) {
        const printBtn = document.createElement('button');
        printBtn.type = 'button';
        printBtn.className = 'btn btn-sm btn-outline-secondary jps-print-btn ms-2';
        printBtn.innerHTML = '<i class="bi bi-printer me-1"></i> Print';
        printBtn.addEventListener('click', function () {
            window.print();
        });
        editBtn.insertAdjacentElement('afterend', printBtn);
    }
})();
