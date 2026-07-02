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

  <div class="form-body">
    <form action="{{ route('portal.register.attempt') }}" method="POST" id="recruitForm">
      @csrf

      {{-- ── PERSONAL INFORMATION ──────────────────────────────── --}}
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
        @error('position_applied')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        @php
        $positions = [
            'Contract of Service (COS)',
            'Accountant I','Accountant III',
            'Administrative Aide I','Administrative Aide III','Administrative Aide IV','Administrative Aide VI',
            'Administrative Assistant I','Administrative Assistant II',
            'Administrative Assistant II (Disbursing Officer)','Administrative Assistant II (Verifier)',
            'Administrative Assistant III','Administrative Assistant III (Senior Bookkeeper)',
            'Administrative Officer I','Administrative Officer II','Administrative Officer IV','Administrative Officer V',
            'Assistant School Principal II','Attorney III',
            'Chief Education Program Supervisor',
            'Dental Aide','Dentist II','Driver',
            'Education Program Specialist','Education Program Supervisor',
            'Elementary School Principal III',
            'Engineer III',
            'Farmworker I',
            'Guidance Coordinator I','Guidance Coordinator II','Guidance Coordinator III',
            'Guidance Counselor I','Guidance Counselor II','Guidance Counselor III',
            'Handicraft Worker',
            'Head Teacher I','Head Teacher II','Head Teacher III','Head Teacher IV','Head Teacher V','Head Teacher VI',
            'Information Technology Officer I','Legal Assistant I',
            'Medical Officer III','Nurse II','Planning Officer III',
            'Project Development Officer I','Project Development Officer II',
            'Public Schools District Supervisor','Registrar I',
            'School Librarian I','School Librarian II',
            'School Principal I','School Principal II','School Principal III',
            'Security Guard I','Security Guard II',
            'Senior Education Program Specialist',
            'Teacher I','Teacher II','Teacher III',
            'Master Teacher I','Master Teacher II',
            'Special Science Teacher I',
            'Special Education Teacher I','Special Education Teacher II','Special Education Teacher III',
            'Watchman I',
          ];
      @endphp
        <select name="position_applied"
            class="form-select @error('position_applied') is-invalid @enderror"
              required>
            <option value="" disabled {{ old('position_applied') ? '' : 'selected' }}>— Select a position —</option>
                @foreach($positions as $pos)
              <option value="{{ $pos }}" {{ old('position_applied') === $pos ? 'selected' : '' }}>
                {{ $pos }}
              </option>
            @endforeach
        </select>
      </div>

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

      {{-- ── QUALIFICATIONS ────────────────────────────────────── --}}
      <div class="section-title">Qualifications</div>

      {{-- 12. Education --}}
      <div class="mb-4">
        <label class="form-label"><span class="q-num">12.</span> Highest Educational Attainment (write in full) <span class="required-star">*</span></label>
        <p class="hint">e.g. Bachelor of Science in Secondary Education major in English; Master of Education major in Administration and Supervision</p>
        <input type="text" name="education"
          class="form-control @error('education') is-invalid @enderror"
          placeholder="Write in full" value="{{ old('education') }}" required>
        @error('education')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
      $('select[name="position_applied"]').select2({
          placeholder: '— Select a position —',
          allowClear: true,
      });
  });
</script>
</body>
</html>