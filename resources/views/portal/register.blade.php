<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DepEd Cavite – Online Recruitment Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/deped-theme.css') }}">
<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  .form-body { padding:32px 36px; }
  .q-num { color:var(--teal); font-weight:600; }
  .form-label { font-size:.88rem; color:#333; font-weight:500; }
  .required-star { color:#c0392b; }
  .form-control, .form-select { font-size:.88rem; border-color:#d0d7de; border-radius:6px; }
  .form-control:focus, .form-select:focus { border-color:var(--teal-mid); box-shadow:0 0 0 3px rgba(43,122,120,.12); }
  .radio-option { display:flex; align-items:center; gap:10px; padding:9px 14px; margin-bottom:6px; border:1.5px solid #e0e6ea; border-radius:6px; cursor:pointer; transition:.15s; font-size:.88rem; }
  .radio-option:hover { border-color:var(--teal-mid); background:#f0fbfa; }
  .radio-option input[type=radio] { accent-color:var(--teal); width:17px; height:17px; flex-shrink:0; cursor:pointer; }
  .radio-option.selected { border-color:var(--teal); background:var(--teal-light); }
  .position-list { columns:2; gap:12px; }
  @media(max-width:576px){ .position-list { columns:1; } }
  .hint { font-size:.78rem; color:#666; margin-top:3px; }
  .login-link { text-align:center; margin-top:18px; font-size:.85rem; }
  .is-invalid-custom { border-color:#dc3545 !important; }
  .no-openings-card { text-align:center; padding:48px 32px; }
  .no-openings-card i { font-size:2.6rem; color:var(--teal-mid); margin-bottom:14px; display:block; }
  .no-openings-card h5 { font-weight:600; margin-bottom:8px; }
  .no-openings-card p { color:#666; font-size:.92rem; margin-bottom:0; }

</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
  .select2-container .select2-selection--single {
      height: 38px;
      border-color: #d0d7de;
      border-radius: 6px;
      font-size: .88rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px;
      color: #333;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
  }
  .select2-container { width: 100% !important; }
</style>
</head>
<body class="deped-watermark">

<div class="form-card">
  {{-- Header --}}
  <div class="deped-header">
    <img src="/sdo-logo.png" alt="DepEd Logo" class="deped-logo">
    <div class="deped-header-text">
      <h1>Department of Education – Division of Cavite Province<br>Online Recruitment Form</h1>
      <p class="sub">Answer the following information truthfully and with honesty.</p>
    </div>
  </div>

  {{-- Privacy notice --}}
  <div class="privacy">
    <strong>Data Privacy Notice:</strong><br>
    All data gathered from this form shall be used solely by the Human Resource Unit for Initial
    Evaluation and Assessment of Applicants' documents purposes and shall be treated as confidential
    in compliance to Data Privacy Act of 2012.<br><br>
    When you submit this form, a confirmation email with your transaction number will be sent to
    the email address you provide.
  </div>

  {{-- Errors --}}
  @if ($errors->any())
  <div class="mx-4 mt-3">
    <div class="alert alert-danger py-2" style="font-size:.83rem;">
      <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  </div>
  @endif

  @php
    // Position dropdown: ONE entry per open job posting (title only --
    // place of assignment is picked separately in the dependent Place
    // field below, once a position is chosen). Uses $openPostings passed
    // in from the controller so we don't re-query the same data twice.
    $openPostingOptions = $openPostings
        ->sortBy('title')
        ->map(fn ($posting) => [
            'value' => $posting->id,
            'label' => $posting->title,
        ])
        ->values();

    // Hoisted above the @if below: the <script> block near the bottom of
    // this page reads $oldEduYear via @json() unconditionally, but the
    // form (and the @php block that used to be the only place these were
    // set) only renders in the @else branch. Compute them here too so
    // they're always defined, even on the "No Openings Right Now" branch.
    $oldEduParts  = old('education') ? array_map('trim', explode(' - ', old('education'), 3)) : [];
    $oldEduLevel  = $oldEduParts[0] ?? null;
    $oldEduCourse = $oldEduParts[1] ?? null;
    $oldEduYear   = $oldEduParts[2] ?? null;
  @endphp

  @if ($openPostingOptions->isEmpty())
    <div class="form-body">
      <div class="no-openings-card">
        <i class="bi bi-info-circle"></i>
        <h5>No Openings Right Now</h5>
        <p>There are currently no open positions available. Please check back later — new postings are added regularly.</p>
      </div>
    </div>
  @else
  <div class="form-body">
    <form action="{{ route('portal.register.attempt') }}" method="POST" id="recruitForm">
      @csrf

      {{-- ── PERSONAL INFORMATION ─────────────────────────────────── --}}
      <div class="section-title">Personal Information</div>
      <p style="font-size:.8rem;color:#888;margin:-10px 0 16px;">* Required</p>

      {{-- 1. Name --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">1.</span> Name (Surname, First Name MI) <span class="required-star">*</span></label>
        <div class="row g-2">
          <div class="col-sm-4">
            <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
              placeholder="Surname" value="{{ old('last_name') }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-4">
            <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
              placeholder="First Name" value="{{ old('first_name') }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-4">
            <input type="text" name="middle_name" class="form-control"
              placeholder="Middle Name (optional)" value="{{ old('middle_name') }}">
          </div>
        </div>
      </div>

      {{-- 2. Position --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">2.</span> Position Applying For <span class="required-star">*</span></label>
        @error('job_posting_id')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        <select name="job_posting_id"
            id="jobPostingSelect"
            class="form-select @error('job_posting_id') is-invalid @enderror"
              required>
            <option value="" disabled {{ old('job_posting_id') ? '' : 'selected' }}>— Select a position —</option>
                @foreach($openPostingOptions as $option)
              <option value="{{ $option['value'] }}" {{ (string) old('job_posting_id') === (string) $option['value'] ? 'selected' : '' }}>
                {{ $option['label'] }}
              </option>
            @endforeach
        </select>
      </div>

      <script>
        window.__oldJobPostingLocationId = @json(old('job_posting_location_id'));
      </script>

      {{-- 3. Address --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">3.</span> Address <span class="required-star">*</span></label>
        <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
          placeholder="Enter your complete address" value="{{ old('address') }}" required>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 4. Age --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">4.</span> Age <span class="required-star">*</span></label>
        <input type="number" name="age" min="18" max="70"
          class="form-control @error('age') is-invalid @enderror" style="max-width:120px;"
          placeholder="e.g. 28" value="{{ old('age') }}" required>
        @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 5. Sex --}}
      <div class="mb-4">
        <label class="form-label d-block"><span class="q-num">5.</span> Sex <span class="required-star">*</span></label>
        @error('sex')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        @foreach(['Male','Female'] as $opt)
        <label class="radio-option {{ old('sex')===$opt?'selected':'' }}" style="max-width:200px;">
          <input type="radio" name="sex" value="{{ $opt }}" {{ old('sex')===$opt?'checked':'' }} required>
          {{ $opt }}
        </label>
        @endforeach
      </div>

      {{-- 6. Civil Status --}}
      <div class="mb-4">
        <label class="form-label d-block"><span class="q-num">6.</span> Civil Status <span class="required-star">*</span></label>
        @error('civil_status')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        @foreach(['Single','Married','Legally Separated','Widowed'] as $opt)
        <label class="radio-option {{ old('civil_status')===$opt?'selected':'' }}" style="max-width:260px;">
          <input type="radio" name="civil_status" value="{{ $opt }}" {{ old('civil_status')===$opt?'checked':'' }} required>
          {{ $opt }}
        </label>
        @endforeach
      </div>

      {{-- 7. Religion --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">7.</span> Religion (e.g. Catholic, Iglesia ni Cristo) <span class="required-star">*</span></label>
        <input type="text" name="religion"
          class="form-control @error('religion') is-invalid @enderror"
          placeholder="Enter your religion" value="{{ old('religion') }}" required>
        @error('religion')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 8. Disability --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">8.</span> Disability <span class="required-star">*</span></label>
        <p class="hint">If not applicable, put N/A</p>
        <input type="text" name="disability"
          class="form-control @error('disability') is-invalid @enderror"
          placeholder="e.g. N/A" value="{{ old('disability') }}" required>
        @error('disability')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 9. Ethnic Group --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">9.</span> Ethnic Group (e.g. Aeta, Mangyan) <span class="required-star">*</span></label>
        <p class="hint">If not applicable, put N/A</p>
        <input type="text" name="ethnic_group"
          class="form-control @error('ethnic_group') is-invalid @enderror"
          placeholder="e.g. N/A" value="{{ old('ethnic_group') }}" required>
        @error('ethnic_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 10. Contact No --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">10.</span> Contact No. <span class="required-star">*</span></label>
        <input type="text" name="phone"
          class="form-control @error('phone') is-invalid @enderror"
          placeholder="e.g. 09171234567" value="{{ old('phone') }}" required>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 11. Email --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">11.</span> Email Address <span class="required-star">*</span></label>
        <input type="email" name="email"
          class="form-control @error('email') is-invalid @enderror"
          placeholder="Enter your email address" value="{{ old('email') }}" required>
        <p class="hint">A confirmation with your transaction number will be sent here.</p>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- ── QUALIFICATIONS ───────────────────────────────────────── --}}
      <div class="section-title">Qualifications</div>

      {{-- 12. Education --}}
      <div class="mb-4">
        <label class="form-label d-block"><span class="q-num">12.</span> Highest Educational Attainment <span class="required-star">*</span></label>
        @error('education')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        {{-- 12a. Educational Level (radio) --}}
        <p class="hint" style="margin-top:0;">Educational Level</p>
        @php
          $eduLevels = ['Elementary','Secondary','Vocational / Trade Course','College','Graduate Studies'];
          // old('education') was saved as "Level - Course - Year"; split it back out for repopulation
          $oldEduParts = old('education') ? array_map('trim', explode(' - ', old('education'), 3)) : [];
          $oldEduLevel = $oldEduParts[0] ?? null;
          $oldEduCourse = $oldEduParts[1] ?? null;
          $oldEduYear = $oldEduParts[2] ?? null;
        @endphp
        <div class="mb-3">
          @foreach($eduLevels as $lvl)
          <label class="radio-option edu-level-option {{ $oldEduLevel===$lvl?'selected':'' }}" style="max-width:320px;">
            <input type="radio" name="edu_level" value="{{ $lvl }}" {{ $oldEduLevel===$lvl?'checked':'' }} required>
            {{ $lvl }}
          </label>
          @endforeach
        </div>

        {{-- 12b. Course / Degree (textbox) --}}
        <div class="mb-3" id="eduCourseWrap">
          <label class="form-label">Course / Degree (write in full)</label>
          <p class="hint">e.g. Bachelor of Science in Secondary Education major in English; Master of Education major in Administration and Supervision</p>
          <input type="text" id="eduCourseInput"
            class="form-control"
            placeholder="Write in full" value="{{ $oldEduCourse }}">
        </div>

        {{-- 12c. Year Level (radio, options depend on 12a) --}}
        <div class="mb-1" id="eduYearWrap">
          <label class="form-label d-block">Year Level</label>
          <div id="eduYearOptions"></div>
        </div>

        {{-- Hidden combined field actually submitted to the server --}}
        <input type="hidden" name="education" id="eduCombinedInput" value="{{ old('education') }}">
      </div>

      {{-- 13. Training Hours --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">13.</span> Number of TRAINING HOURS relevant to the position applying for <span class="required-star">*</span></label>
        <p class="hint">e.g. 24 hours</p>
        <input type="text" name="training_hours"
          class="form-control @error('training_hours') is-invalid @enderror"
          placeholder="e.g. 24 hours" value="{{ old('training_hours') }}" required>
        @error('training_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 14. Years of Experience --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">14.</span> Number of YEARS OF EXPERIENCE relevant to the position applying for <span class="required-star">*</span></label>
        <p class="hint">e.g. 5 years and 10 months</p>
        <input type="text" name="years_experience"
          class="form-control @error('years_experience') is-invalid @enderror"
          placeholder="e.g. 5 years and 10 months" value="{{ old('years_experience') }}" required>
        @error('years_experience')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      {{-- 15. Eligibility --}}
      <div class="mb-4">
        <label class="form-label d-block"><span class="q-num">15.</span> Eligibility <span class="required-star">*</span></label>
        @error('eligibility')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @php
          $eligibilities = [
            '1st Level Eligibility (Career Service Sub-Professional)',
            '2nd Level Eligibility (Career Service Professional)',
            'Bar/Board Eligibility (RA1080)',
            'Honor Graduate Eligibility (PD907)',
            'Barangay Health Worker Eligibility (RA7883)',
            'Barangay Official Eligibility (RA 7160)',
            'Sanggunian Member Eligibility (RA 10156)',
            'Electronic Data Processing Specialist Eligibility (CSC Res. 90-083)',
            'Foreign School Honor Graduate Eligibility (CSC Res. 1302714)',
            'Scientific and Technological Specialist Eligibility (PD 997)',
            'Skills Eligibility - Category II and IV (CSC MC 11, s. 1996, as Amended)',
            'Barangay Nutrition Scholar Eligibility (PD1569)',
            'None',
          ];
        @endphp
        @foreach($eligibilities as $elig)
        <label class="radio-option {{ old('eligibility')===$elig?'selected':'' }}">
          <input type="radio" name="eligibility" value="{{ $elig }}" {{ old('eligibility')===$elig?'checked':'' }} required>
          {{ $elig }}
        </label>
        @endforeach
      </div>

      {{-- Submit --}}
      <p style="font-size:.8rem;color:#777;margin-bottom:16px;">
        You will receive a confirmation email with your transaction number after submission.
      </p>
      <button type="submit" class="btn-submit">Submit</button>
    </form>

  </div>
  @endif

  <div class="form-footer">
    DepEd Division of Cavite Province
  </div>
</div>

<script>
// Keep radio-option highlight in sync on page load (for old() repopulation)
document.querySelectorAll('.radio-option input[type=radio]').forEach(r => {
  if (r.checked) r.closest('.radio-option').classList.add('selected');
  r.addEventListener('change', () => {
    document.querySelectorAll(`[name="${r.name}"]`).forEach(o =>
      o.closest('.radio-option').classList.remove('selected'));
    if (r.checked) r.closest('.radio-option').classList.add('selected');
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  $(document).ready(function() {
      $('select[name="job_posting_id"]').select2({
          placeholder: '— Select a position —',
          allowClear: true,
      });
  });
</script>

<script>
// ── Question 12: Highest Educational Attainment ────────────────────────
// Level (radio) -> Course/Degree (textbox) -> Year Level (radio, options
// depend on Level). All three combine into the single hidden #education
// field ("Level - Course - Year") which is what actually gets POSTed and
// saved to the existing candidates.education text column -- no DB changes
// needed.
(function () {
  const yearOptionsByLevel = {
    'Elementary':               ['Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Graduate'],
    'Secondary':                ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12','Graduate'],
    'Vocational / Trade Course':['1st Year','2nd Year','Graduate'],
    'College':                  ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate'],
    'Graduate Studies':         ['With Units','Candidate','Graduate'],
  };

  const $levelRadios  = $('input[name="edu_level"]');
  const $courseWrap   = $('#eduCourseWrap');
  const $courseInput  = $('#eduCourseInput');
  const $yearWrap      = $('#eduYearWrap');
  const $yearOptionsEl = $('#eduYearOptions');
  const $combined      = $('#eduCombinedInput');

  const oldYear = @json($oldEduYear);

  function renderYearOptions(level, preselect) {
      const opts = yearOptionsByLevel[level] || [];
      $yearOptionsEl.empty();
      opts.forEach(function (opt) {
          const checked = opt === preselect;
          const $label = $('<label class="radio-option edu-year-option" style="max-width:220px;display:inline-flex;margin-right:8px;"></label>');
          if (checked) $label.addClass('selected');
          const $input = $('<input type="radio" name="edu_year">').val(opt).prop('checked', checked);
          $input.on('change', function () {
              $yearOptionsEl.find('.edu-year-option').removeClass('selected');
              $label.addClass('selected');
              syncCombined();
          });
          $label.append($input).append(' ' + opt);
          $yearOptionsEl.append($label);
      });
  }

  function currentLevel() {
      return $levelRadios.filter(':checked').val() || '';
  }

  function currentYear() {
      return $yearOptionsEl.find('input[name="edu_year"]:checked').val() || '';
  }

  function syncCombined() {
      const level  = currentLevel();
      const course = $.trim($courseInput.val());
      const year   = currentYear();
      const parts = [level, course, year].filter(function (p) { return p; });
      $combined.val(parts.join(' - '));
  }

  $levelRadios.on('change', function () {
      $('.edu-level-option').removeClass('selected');
      $(this).closest('.edu-level-option').addClass('selected');
      renderYearOptions(this.value, null);
      syncCombined();
  });

  $courseInput.on('input', syncCombined);

  // Initial render (handles validation-error repopulation via old())
  const initialLevel = currentLevel();
  if (initialLevel) {
      renderYearOptions(initialLevel, oldYear);
  }
  syncCombined();

  // Belt-and-suspenders: recompute right before the form actually submits
  $('#recruitForm').on('submit', function () {
      syncCombined();
      if (!$combined.val()) {
          // Shouldn't happen since level+year are required radios, but
          // guard anyway so we never silently submit an empty education.
          $combined.val($.trim($courseInput.val()));
      }
  });
})();
</script>
</body>
</html>