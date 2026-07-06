/* ============================================================
   hr-interactive.js
   Shared, zero-config interactivity for the DepEd Cavite
   HR Recruitment System. Pairs with hr-interactive.css.

   Include once, near the end of <body>, on every layout
   (layouts/app, layouts/auth, layouts/portal, welcome, register,
   submitted, etc.):

     <link rel="stylesheet" href="{{ asset('css/hr-interactive.css') }}">
     ...
     <script src="{{ asset('js/hr-interactive.js') }}"></script>

   Everything below auto-detects existing markup/classes already
   used across the app (.card, .alert, .btn, .table, [data-count],
   .talent-card / #talentSearch, .accordion, transaction numbers,
   etc.) — no template changes required. Individual features are
   opt-in via harmless data-attributes where relevant.
   ============================================================ */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    fadeInContent();
    staggerCards();
    enableButtonRipple();
    enableButtonLoadingState();
    enableAlertAutoDismiss();
    enableCountUp();
    enableCopyableTransactionNumbers();
    enableSearchClearButtons();
    enableBackToTop();
    enableExternalSearchNoResults();
  });

  /* ------------------------------------------------------------
     1. Fade the main content area in on first paint
  ------------------------------------------------------------ */
  function fadeInContent() {
    var target =
      document.querySelector('.hr-main') ||
      document.querySelector('.portal-main') ||
      document.querySelector('.page-content') ||
      document.querySelector('main');
    if (target) target.classList.add('hri-fade-in');
  }

  /* ------------------------------------------------------------
     2. Stagger-animate sibling cards / rows on load
  ------------------------------------------------------------ */
  function staggerCards() {
    var groups = document.querySelectorAll(
      '.row.g-3, .row.g-4, #talentPoolGrid, table.table tbody'
    );
    groups.forEach(function (group) {
      var children = group.tagName === 'TBODY' ? group.children : group.children;
      Array.prototype.forEach.call(children, function (child, i) {
        child.style.animationDelay = Math.min(i * 40, 400) + 'ms';
        child.classList.add(group.tagName === 'TBODY' ? 'hri-row-anim' : 'hri-card-anim');
      });
      if (group.tagName === 'TBODY') {
        group.closest('table') && group.closest('table').classList.add('hri-stagger');
      } else {
        group.classList.add('hri-stagger');
      }
    });
  }

  /* ------------------------------------------------------------
     3. Material-style ripple on any .btn click
  ------------------------------------------------------------ */
  function enableButtonRipple() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn');
      if (!btn || btn.disabled) return;

      var rect = btn.getBoundingClientRect();
      var size = Math.max(rect.width, rect.height);
      var ripple = document.createElement('span');
      ripple.className = 'hri-ripple';
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
      ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

      var prevPosition = getComputedStyle(btn).position;
      if (prevPosition === 'static') btn.style.position = 'relative';

      btn.appendChild(ripple);
      window.setTimeout(function () {
        ripple.remove();
      }, 600);
    });
  }

  /* ------------------------------------------------------------
     4. Show a spinner on submit buttons when their form submits
        (skips forms with onsubmit="return confirm(...)" cancels)
  ------------------------------------------------------------ */
  function enableButtonLoadingState() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (form.defaultPrevented) return;
      // If browser-native validation blocks the submit, don't spin.
      if (form.checkValidity && !form.checkValidity()) return;

      var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitBtn && !submitBtn.classList.contains('hri-loading')) {
        submitBtn.classList.add('hri-loading');
      }
    }, true);
  }

  /* ------------------------------------------------------------
     5. Auto-dismiss success/info alerts after a few seconds,
        with a shrinking progress bar. Errors stay put.
  ------------------------------------------------------------ */
  function enableAlertAutoDismiss() {
    var alerts = document.querySelectorAll(
      '.alert-success, .alert-info'
    );
    alerts.forEach(function (alert) {
      // Don't auto-hide alerts that list multiple validation errors etc.
      if (alert.classList.contains('alert-danger')) return;
      alert.classList.add('hri-autohide');
      window.setTimeout(function () {
        dismissAlert(alert);
      }, 5000);
    });
  }

  function dismissAlert(alert) {
    if (!alert || !alert.parentNode) return;
    alert.classList.add('hri-leaving');
    window.setTimeout(function () {
      alert.remove();
    }, 260);
  }

  /* ------------------------------------------------------------
     6. Animated count-up for any [data-count] element
        (works for dashboard stat cards; safe no-op elsewhere)
  ------------------------------------------------------------ */
  function enableCountUp() {
    var counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;

    var duration = 700;
    counters.forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      var start = null;

      function step(ts) {
        if (!start) start = ts;
        var progress = Math.min(1, (ts - start) / duration);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(eased * target).toLocaleString();
        if (progress < 1) window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    });
  }

  /* ------------------------------------------------------------
     7. Click-to-copy for transaction numbers.
        Auto-tags anything that looks like a txn number container
        (.txn .num, .txn-box .number) plus opt-in .hri-copyable.
  ------------------------------------------------------------ */
  function enableCopyableTransactionNumbers() {
    var targets = document.querySelectorAll(
      '.txn .num, .txn-box .number, .hri-copyable'
    );
    if (!targets.length) return;

    var toast = document.createElement('div');
    toast.className = 'hri-copy-toast';
    toast.textContent = 'Copied to clipboard';
    document.body.appendChild(toast);

    var toastTimer = null;
    function showToast(text) {
      toast.textContent = text;
      toast.classList.add('show');
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(function () {
        toast.classList.remove('show');
      }, 1600);
    }

    targets.forEach(function (el) {
      el.classList.add('hri-copyable');
      el.title = el.title || 'Click to copy';
      el.addEventListener('click', function () {
        var text = el.textContent.trim();
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text)
            .then(function () { showToast('Copied: ' + text); })
            .catch(function () { fallbackCopy(text); });
        } else {
          fallbackCopy(text);
        }
      });
    });

    function fallbackCopy(text) {
      var tmp = document.createElement('textarea');
      tmp.value = text;
      tmp.style.position = 'fixed';
      tmp.style.opacity = '0';
      document.body.appendChild(tmp);
      tmp.select();
      try {
        document.execCommand('copy');
        showToast('Copied: ' + text);
      } catch (err) {
        showToast('Press Ctrl+C to copy');
      }
      tmp.remove();
    }
  }

  /* ------------------------------------------------------------
     8. Inject a "×" clear button into search-style text inputs
        (id="talentSearch" today; extendable via [data-hri-search])
  ------------------------------------------------------------ */
  function enableSearchClearButtons() {
    var inputs = document.querySelectorAll(
      '#talentSearch, input[type="search"], [data-hri-search]'
    );
    inputs.forEach(function (input) {
      if (input.closest('.hri-search-wrap')) return;

      var wrap = document.createElement('div');
      wrap.className = 'hri-search-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'hri-search-clear';
      clearBtn.innerHTML = '&times;';
      clearBtn.setAttribute('aria-label', 'Clear search');
      wrap.appendChild(clearBtn);

      function sync() {
        wrap.classList.toggle('has-value', input.value.trim().length > 0);
      }
      input.addEventListener('input', sync);
      clearBtn.addEventListener('click', function () {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
        sync();
      });
      sync();
    });
  }

  /* ------------------------------------------------------------
     9. Floating "back to top" button once the page scrolls down
  ------------------------------------------------------------ */
  function enableBackToTop() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'hri-to-top';
    btn.innerHTML = '<i class="bi bi-arrow-up"></i>';
    btn.setAttribute('aria-label', 'Back to top');
    document.body.appendChild(btn);

    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        btn.classList.toggle('show', window.scrollY > 420);
        ticking = false;
      });
    });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ------------------------------------------------------------
     10. Generic "no results" message for any client-side filter
         that follows the talent-pool pattern: a text input whose
         `input` event toggles `.style.display` on sibling cards
         carrying a shared class (e.g. .talent-card). We watch for
         that pattern and show/hide a message automatically.
  ------------------------------------------------------------ */
  function enableExternalSearchNoResults() {
    var grid = document.getElementById('talentPoolGrid');
    var search = document.getElementById('talentSearch');
    if (!grid || !search) return;

    var empty = document.createElement('div');
    empty.className = 'col-12 hri-no-results';
    empty.textContent = 'No candidates match your search.';
    grid.appendChild(empty);

    function evaluate() {
      var cards = grid.querySelectorAll('.talent-card');
      var visible = Array.prototype.filter.call(cards, function (c) {
        return c.style.display !== 'none';
      });
      empty.style.display = (cards.length && visible.length === 0) ? 'block' : 'none';
    }

    search.addEventListener('input', function () {
      window.setTimeout(evaluate, 0);
    });
  }
})();
