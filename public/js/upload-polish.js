/* ============================================
   Upload PDF — interactive polish (JS)
   Save to: public/js/upload-polish.js

   Purely additive: doesn't touch the form's method/action/validation,
   just layers drag-and-drop feel + visual feedback on the existing
   <input type="file" name="pdf_file"> and submit button.
   ============================================ */

(function () {
    const fileInput = document.querySelector('input[type="file"][name="pdf_file"]');
    if (!fileInput) return;

    const form = fileInput.closest('form');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

    // ── Drag-over visual feedback (native file inputs already accept drops) ─
    ['dragenter', 'dragover'].forEach(function (evt) {
        fileInput.addEventListener(evt, function (e) {
            e.preventDefault();
            fileInput.classList.add('upload-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        fileInput.addEventListener(evt, function () {
            fileInput.classList.remove('upload-dragover');
        });
    });

    // ── Selected-file chip: filename + size, injected after the input ───────
    let chip = document.createElement('div');
    chip.className = 'upload-file-chip';
    chip.style.display = 'none';
    fileInput.insertAdjacentElement('afterend', chip);

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            chip.style.display = 'none';
            return;
        }

        const tooBig = file.size > 20 * 1024 * 1024; // matches server's 20MB max
        const notPdf = file.type && file.type !== 'application/pdf';

        chip.innerHTML = '<i class="bi ' + (tooBig || notPdf ? 'bi-exclamation-triangle-fill' : 'bi-file-earmark-pdf-fill') + '"></i> '
            + file.name
            + ' <span class="upload-file-size">(' + formatSize(file.size) + ')</span>';
        chip.classList.toggle('upload-file-warning', tooBig || notPdf);
        chip.style.display = 'inline-flex';

        if (tooBig) {
            chip.innerHTML += ' — exceeds 20MB limit';
        } else if (notPdf) {
            chip.innerHTML += ' — not a PDF file';
        }
    });

    // ── Submit button: loading state while the upload/OCR kicks off ─────────
    // The form's own action/redirect is untouched — this only adds a visual
    // spinner + disables double-submits during the (sometimes slow) upload.
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            if (!fileInput.files || !fileInput.files[0]) return;
            submitBtn.classList.add('upload-btn-loading');
            submitBtn.innerHTML = '<span class="upload-spinner"></span> Uploading...';
        });
    }
})();
