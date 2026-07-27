/* ============================================
   DepEd Page Loading Screen
   Save to: public/js/page-loader.js

   What it does:
   - Injects a full-screen loading overlay (logo + spinner)
   - Shows it automatically when:
       - clicking any internal link (dashboard, register, login, etc.)
       - submitting any form (login, register, apply, etc.)
   - Hides it automatically when the new page finishes loading,
     and also when the user hits Back/Forward (bfcache safe).

   Requires: your DepEd/SDO logo saved at public/images/sdo-logo.png
   (change LOGO_PATH below if you use a different name/location)
   ============================================ */

(function () {
    const LOGO_PATH = '/sdo-logo.png';
    const LOGO_ALT  = 'DepEd Cavite';
    const LOADING_MESSAGE = 'Loading';
    const MIN_DISPLAY_MS = 250; // how long the loader stays visible at minimum (ms)
    let shownAt = 0;

    function buildWaveBars(count) {
        let bars = '';
        for (let i = 0; i < count; i++) {
            bars += '<div class="wave-bar"></div>';
        }
        return bars;
    }

    function buildOverlay() {
        if (document.getElementById('deped-page-loader')) return;

        const overlay = document.createElement('div');
        overlay.id = 'deped-page-loader';
        overlay.innerHTML = `
            <div class="loader-progress-track"><div class="loader-progress-bar"></div></div>
            <div class="loader-panel">
                <div class="loader-logo-wrap">
                    <div class="loader-logo-circle"><img src="${LOGO_PATH}" alt="${LOGO_ALT}"></div>
                    <div class="wave-row">${buildWaveBars(12)}</div>
                </div>
                <div class="loader-text">${LOADING_MESSAGE}<span class="dots"><span>.</span><span>.</span><span>.</span></span></div>
                <div class="loader-subtitle">Schools Division Office of Cavite</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function showLoader() {
        const overlay = document.getElementById('deped-page-loader');
        if (overlay) {
            overlay.classList.add('is-active');
            shownAt = Date.now();

            // Genuine safety net: force-hide after 8s no matter what, in
            // case a future link/download triggers the loader without
            // ever firing a 'load' or 'pageshow' event to hide it again
            // (the header comment already claimed this existed -- it
            // didn't, this is that).
            clearTimeout(window.__depedLoaderSafetyTimer);
            window.__depedLoaderSafetyTimer = setTimeout(function () {
                if (overlay.classList.contains('is-active')) {
                    overlay.classList.remove('is-active');
                }
            }, 8000);
        }
    }

    function hideLoader() {
        const overlay = document.getElementById('deped-page-loader');
        if (!overlay || !overlay.classList.contains('is-active')) return;

        const elapsed = Date.now() - shownAt;
        const remaining = Math.max(0, MIN_DISPLAY_MS - elapsed);

        setTimeout(function () {
            const bar = overlay.querySelector('.loader-progress-bar');
            if (bar) {
                bar.style.transition = 'width 0.15s ease-out';
                bar.style.width = '100%';
            }

            setTimeout(function () {
                overlay.classList.remove('is-active');
                if (bar) {
                    setTimeout(function () {
                        bar.style.transition = '';
                        bar.style.width = '';
                    }, 150);
                }
            }, 120);
        }, remaining);
    }

    function isSameOriginLink(link) {
        if (!link.href) return false;
        if (link.target && link.target !== '' && link.target !== '_self') return false;
        if (link.hasAttribute('data-no-loader')) return false;
        if (link.href.startsWith('javascript:')) return false;
        if (link.href.startsWith('mailto:') || link.href.startsWith('tel:')) return false;
        if (link.getAttribute('href') && link.getAttribute('href').startsWith('#')) return false;
        if (link.hasAttribute('download')) return false;

        try {
            const url = new URL(link.href, window.location.href);
            return url.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        buildOverlay();

        // Show loader when clicking any internal link
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;
            if (isSameOriginLink(link)) {
                showLoader();
            }
        });

        // Show loader on any form submit (login, register, apply, etc.)
        document.addEventListener('submit', function (e) {
            // If the form's own handler already cancelled this submit
            // (e.g. onsubmit="return confirm(...)" and the user clicked
            // Cancel), don't show the loader -- no navigation is going to
            // happen, so nothing would ever hide it again.
            if (e.defaultPrevented) return;
            const form = e.target;
            if (form.hasAttribute('data-no-loader')) return;
            showLoader();
        });
    });

    // Hide loader once the new page has fully loaded
    window.addEventListener('load', hideLoader);

    // Hide loader when navigating back/forward (bfcache restores the old DOM state)
    window.addEventListener('pageshow', function (event) {
        hideLoader();
    });

    // Safety net: force-hide after 8s in case something hangs
    window.addEventListener('beforeunload', function () {
        showLoader();
    });
})();