/* ============================================
   Extracted Text — interactive polish (JS)
   Save to: public/js/extracted-polish.js

   Purely additive: injects a search box and per-page "Copy" buttons
   into the existing accordion via JS. Doesn't touch Bootstrap's own
   collapse/accordion behavior or any Blade/PHP output.
   ============================================ */

(function () {
    const accordion = document.getElementById('pageAccordion');
    if (!accordion) return;

    const items = Array.from(accordion.querySelectorAll('.accordion-item'));

    // ── Inject a search box above the accordion ─────────────────────────────
    const searchWrap = document.createElement('div');
    searchWrap.className = 'extracted-search-wrap';
    searchWrap.innerHTML = `
        <i class="bi bi-search"></i>
        <input type="text" class="form-control form-control-sm" id="extractedSearchInput"
               placeholder="Search extracted text across all pages...">
        <div class="extracted-search-count" id="extractedSearchCount"></div>
    `;
    accordion.parentNode.insertBefore(searchWrap, accordion);

    const searchInput = document.getElementById('extractedSearchInput');
    const searchCount = document.getElementById('extractedSearchCount');

    // Keep each page's original (un-highlighted) text so re-searching
    // doesn't compound <mark> tags on top of each other.
    const originals = items.map(function (item) {
        const pre = item.querySelector('pre');
        return pre ? pre.textContent : '';
    });

    function escapeRegExp(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function runSearch(query) {
        const q = query.trim();
        let totalMatches = 0;
        let firstMatchItem = null;

        items.forEach(function (item, i) {
            const pre = item.querySelector('pre');
            if (!pre) return;

            if (q === '') {
                pre.innerHTML = escapeHtml(originals[i]);
                return;
            }

            const regex = new RegExp(escapeRegExp(q), 'gi');
            const matches = originals[i].match(regex);
            const count = matches ? matches.length : 0;
            totalMatches += count;

            const highlighted = escapeHtml(originals[i]).replace(
                new RegExp(escapeRegExp(escapeHtml(q)), 'gi'),
                function (m) { return '<mark class="extracted-hit">' + m + '</mark>'; }
            );
            pre.innerHTML = highlighted;

            if (count > 0 && !firstMatchItem) {
                firstMatchItem = item;
            }
        });

        if (q === '') {
            searchCount.textContent = '';
            return;
        }

        searchCount.textContent = totalMatches === 0
            ? 'No matches found'
            : totalMatches + ' match' + (totalMatches === 1 ? '' : 'es') + ' found';

        // Auto-expand the first page with a match (uses Bootstrap's own
        // collapse API, so its animation/behavior is untouched).
        if (firstMatchItem && window.bootstrap) {
            const collapseEl = firstMatchItem.querySelector('.accordion-collapse');
            if (collapseEl && !collapseEl.classList.contains('show')) {
                const collapse = window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                collapse.show();
            }
        }
    }

    let debounceTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const value = searchInput.value;
        debounceTimer = setTimeout(function () { runSearch(value); }, 150);
    });

    // ── Per-page "Copy text" button, injected into each accordion header ────
    items.forEach(function (item, i) {
        const header = item.querySelector('.accordion-header');
        if (!header) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'extracted-copy-btn';
        btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
        btn.style.position = 'absolute';
        btn.style.right = '48px';
        btn.style.top = '50%';
        btn.style.transform = 'translateY(-50%)';
        btn.style.zIndex = '2';

        header.style.position = 'relative';
        header.appendChild(btn);

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            navigator.clipboard.writeText(originals[i]).then(function () {
                btn.classList.add('extracted-copied');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied';
                setTimeout(function () {
                    btn.classList.remove('extracted-copied');
                    btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
                }, 1500);
            });
        });
    });
})();
