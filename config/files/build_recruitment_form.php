<?php
/**
 * build_recruitment_form.php
 *
 * Transforms the portal registration page into the DepEd Division of Cavite
 * Province Online Recruitment Form, matching the MS Forms template.
 *
 * What this creates / patches:
 *   1. Migration  — adds 10 new columns to candidates table
 *   2. Candidate.php — new fields added to $fillable
 *   3. app/Mail/ApplicationSubmitted.php — Mailable class
 *   4. resources/views/mail/application-submitted.blade.php — email template
 *   5. resources/views/portal/register.blade.php — full recruitment form (REPLACED)
 *   6. resources/views/portal/submitted.blade.php — success page with txn number
 *   7. CandidateAuthController.php — register() rewritten to handle all fields + send email
 *
 * Usage: php build_recruitment_form.php  (from project root)
 *        php artisan migrate
 *        Delete this script.
 */

// ─── helpers ────────────────────────────────────────────────────────────────

function die_loud(string $msg): void {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n"); exit(1);
}

function backup(string $path): void {
    if (!file_exists($path)) die_loud("File not found: $path");
    $b = $path.'.bak'; $n = 1;
    while (file_exists($b)) { $n++; $b = $path.'.bak'.$n; }
    copy($path,$b) or die_loud("Cannot backup $path");
    echo "  backed up → ".basename($b)."\n";
}

function patch(string $src, string $old, string $new, string $lbl): string {
    $c = substr_count($src,$old);
    if ($c!==1) die_loud("Patch '$lbl': expected 1 match, found $c.");
    return str_replace($old,$new,$src);
}

function write(string $path, string $body, string $lbl, bool $over=false): void {
    if (!$over && file_exists($path)) die_loud("$lbl already exists — script may have run.");
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir,0755,true);
    file_put_contents($path,$body)!==false or die_loud("Cannot write $path");
    echo ($over?'  replaced':'  created')." $lbl\n";
}

$root = __DIR__;

// ─── 1. Migration ────────────────────────────────────────────────────────────

$ts = date('Y_m_d_His');
write("$root/database/migrations/{$ts}_add_recruitment_fields_to_candidates_table.php", <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('phone');
            $table->enum('sex', ['Male','Female'])->nullable()->after('age');
            $table->enum('civil_status', ['Single','Married','Legally Separated','Widowed'])->nullable()->after('sex');
            $table->string('religion', 100)->nullable()->after('civil_status');
            $table->string('disability', 255)->nullable()->after('religion');
            $table->string('ethnic_group', 100)->nullable()->after('disability');
            $table->text('education')->nullable()->after('ethnic_group');
            $table->string('training_hours', 100)->nullable()->after('education');
            $table->string('years_experience', 100)->nullable()->after('training_hours');
            $table->string('eligibility', 255)->nullable()->after('years_experience');
        });
    }

    public function down(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'age','sex','civil_status','religion','disability',
                'ethnic_group','education','training_hours','years_experience','eligibility',
            ]);
        });
    }
};
PHP, 'migration');

// ─── 2. Candidate model patch ────────────────────────────────────────────────

$modelPath = "$root/app/Models/Candidate.php";
$model = file_get_contents($modelPath) or die_loud("Cannot read $modelPath");
backup($modelPath);

// Add new fillable fields after 'address'
if (!str_contains($model, "'age'")) {
    $model = patch($model,
        "'address',",
        "'address',\n        'age',\n        'sex',\n        'civil_status',\n        'religion',\n        'disability',\n        'ethnic_group',\n        'education',\n        'training_hours',\n        'years_experience',\n        'eligibility',",
        'Candidate $fillable new fields'
    );
}
write($modelPath, $model, 'Candidate.php', over:true);

// ─── 3. Mailable ─────────────────────────────────────────────────────────────

$mailDir = "$root/app/Mail";
if (!is_dir($mailDir)) mkdir($mailDir, 0755, true);

write("$root/app/Mail/ApplicationSubmitted.php", <<<'PHP'
<?php

namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Candidate $candidate,
        public readonly string    $transactionNumber,
        public readonly string    $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'DepEd Cavite – Application Received (' . $this->transactionNumber . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-submitted',
        );
    }
}
PHP, 'ApplicationSubmitted Mailable');

// ─── 4. Email view ───────────────────────────────────────────────────────────

$mailViewDir = "$root/resources/views/mail";
if (!is_dir($mailViewDir)) mkdir($mailViewDir, 0755, true);

write("$root/resources/views/mail/application-submitted.blade.php", <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:600px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:#2b7a78; color:#fff; padding:28px 32px; }
  .header h1 { margin:0; font-size:1.2rem; font-weight:700; }
  .header p  { margin:4px 0 0; font-size:.85rem; opacity:.85; }
  .body { padding:28px 32px; color:#333; font-size:.92rem; line-height:1.6; }
  .txn { background:#e8f5f5; border-left:4px solid #2b7a78; border-radius:4px;
         padding:14px 18px; margin:20px 0; font-size:1.1rem; font-weight:600; color:#2b7a78; }
  .row { display:flex; gap:8px; padding:6px 0; border-bottom:1px solid #f0f0f0; }
  .row .lbl { color:#666; min-width:160px; font-size:.85rem; }
  .row .val { font-weight:500; font-size:.85rem; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.78rem; color:#888; text-align:center; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Department of Education – Division of Cavite Province</h1>
    <p>Online Recruitment Form – Submission Confirmation</p>
  </div>
  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>
    <p>
      Thank you for submitting your application to the DepEd Division of Cavite Province.
      Your submission has been received and is now under review by the Human Resource Unit.
    </p>

    <div class="txn">
      Transaction No.: {{ $transactionNumber }}
    </div>

    <p><strong>Please keep this transaction number for your records.</strong>
    You may use it when following up on the status of your application.</p>

    <p><strong>Application Details:</strong></p>
    <div class="row"><span class="lbl">Position Applied For</span><span class="val">{{ $position }}</span></div>
    <div class="row"><span class="lbl">Name</span><span class="val">{{ $candidate->full_name }}</span></div>
    <div class="row"><span class="lbl">Email Address</span><span class="val">{{ $candidate->email }}</span></div>
    <div class="row"><span class="lbl">Contact No.</span><span class="val">{{ $candidate->phone ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Address</span><span class="val">{{ $candidate->address ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Eligibility</span><span class="val">{{ $candidate->eligibility ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Highest Education</span><span class="val">{{ $candidate->education ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Training Hours</span><span class="val">{{ $candidate->training_hours ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Years of Experience</span><span class="val">{{ $candidate->years_experience ?? '—' }}</span></div>

    <p style="margin-top:24px; font-size:.82rem; color:#555;">
      All data gathered from this form shall be used solely by the Human Resource Unit for
      Initial Evaluation and Assessment of Applicants' documents purposes and shall be treated
      as confidential in compliance to the Data Privacy Act of 2012.
    </p>
  </div>
  <div class="footer">
    DepEd Division of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply.
  </div>
</div>
</body>
</html>
BLADE, 'mail/application-submitted.blade.php');

// ─── 5. Recruitment register form ────────────────────────────────────────────

write("$root/resources/views/portal/register.blade.php", <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DepEd Cavite – Online Recruitment Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root { --teal: #2b7a78; --teal-light: #def2f1; --teal-mid: #3aafa9; }
  body { background: linear-gradient(135deg, #d9f0ef 0%, #e8f5f5 50%, #c8e6e5 100%); min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; }
  .form-card { max-width:780px; margin:40px auto 60px; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }
  .form-header { background:var(--teal); color:#fff; padding:32px 36px 28px; }
  .form-header h1 { font-size:1.45rem; font-weight:800; margin:0 0 8px; line-height:1.3; }
  .form-header .sub { font-size:.85rem; opacity:.85; margin:0; line-height:1.6; }
  .privacy { background:var(--teal-light); border-left:4px solid var(--teal-mid); border-radius:0; padding:18px 24px; margin:0; font-size:.82rem; color:#1a5c5a; line-height:1.6; }
  .form-body { padding:32px 36px; }
  .section-title { color:var(--teal); font-size:1.05rem; font-weight:700; margin:28px 0 18px; padding-bottom:6px; border-bottom:2px solid var(--teal-light); }
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
  .btn-submit { background:var(--teal); color:#fff; font-weight:700; font-size:.95rem; padding:12px 36px; border-radius:8px; border:none; width:100%; transition:.2s; }
  .btn-submit:hover { background:#1d5e5c; color:#fff; }
  .form-footer { text-align:center; padding:18px 36px 24px; font-size:.78rem; color:#888; }
  .login-link { text-align:center; margin-top:18px; font-size:.85rem; }
  .is-invalid-custom { border-color:#dc3545 !important; }
</style>
</head>
<body>

<div class="form-card">
  {{-- Header --}}
  <div class="form-header">
    <h1>Department of Education – Division of Cavite Province<br>Online Recruitment Form</h1>
    <p class="sub">Answer the following information truthfully and with honesty.</p>
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
        <label class="form-label d-block"><span class="q-num">2.</span> Position Applying For <span class="required-star">*</span></label>
        @error('position_applied')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div class="position-list">
          @php
            $positions = [
              'Contract of Service (COS)',
              'Accountant I','Accountant III',
              'Administrative Aide I','Administrative Aide III','Administrative Aide IV','Administrative Aide VI',
              'Administrative Assistant I','Administrative Assistant II',
              'Administrative Assistant II (Disbursing Officer)','Administrative Assistant II (Verifier)',
              'Administrative Assistant III','Administrative Assistant III (Senior Bookkeeper)',
              'Administrative Officer I','Administrative Officer II','Administrative Officer IV','Administrative Officer V',
              'Assistant School Principal II',
              'Attorney III',
              'Chief Education Program Supervisor',
              'Dental Aide','Dentist II',
              'Driver',
              'Education Program Specialist','Education Program Supervisor',
              'Engineer III',
              'Farmworker I',
              'Guidance Coordinator I','Guidance Coordinator II','Guidance Coordinator III',
              'Guidance Counselor I','Guidance Counselor II','Guidance Counselor III',
              'Handicraft Worker',
              'Head Teacher I','Head Teacher II','Head Teacher III','Head Teacher IV','Head Teacher V','Head Teacher VI',
              'Information Technology Officer I',
              'Legal Assistant I',
              'Medical Officer III',
              'Nurse II',
              'Planning Officer III',
              'Project Development Officer I','Project Development Officer II',
              'Public Schools District Supervisor',
              'Registrar I',
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
          @foreach($positions as $pos)
          <label class="radio-option" onclick="this.classList.toggle('selected',this.querySelector('input').checked)">
            <input type="radio" name="position_applied" value="{{ $pos }}"
              {{ old('position_applied') === $pos ? 'checked' : '' }} required>
            {{ $pos }}
          </label>
          @endforeach
        </div>
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

      {{-- ── ACCOUNT ───────────────────────────────────────────── --}}
      <div class="section-title">Account Setup</div>
      <p style="font-size:.82rem;color:#555;margin:-10px 0 16px;">
        Create a password to log in and track your application status after submission.
      </p>

      <div class="mb-3">
        <label class="form-label">Password <span class="required-star">*</span></label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
          placeholder="Minimum 8 characters" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="mb-4">
        <label class="form-label">Confirm Password <span class="required-star">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Re-enter password" required>
      </div>

      {{-- Submit --}}
      <p style="font-size:.8rem;color:#777;margin-bottom:16px;">
        You can log in and print a copy of your answers after you submit.
      </p>
      <button type="submit" class="btn-submit">Submit</button>
    </form>

    <div class="login-link">
      Already have an account? <a href="{{ route('portal.login') }}" style="color:var(--teal);font-weight:600;">Log in here</a>
    </div>
  </div>

  <div class="form-footer">
    Never give out your password. &bull; DepEd Division of Cavite Province
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
</body>
</html>
BLADE, 'portal/register.blade.php', over:true);

// ─── 6. Submitted / success page ─────────────────────────────────────────────

write("$root/resources/views/portal/submitted.blade.php", <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Submitted – DepEd Cavite</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body { background: linear-gradient(135deg,#d9f0ef 0%,#e8f5f5 50%,#c8e6e5 100%); min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; display:flex; align-items:center; justify-content:center; }
  .card { max-width:520px; width:100%; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.1); overflow:hidden; }
  .card-header { background:#2b7a78; color:#fff; padding:24px 28px; text-align:center; }
  .card-header i { font-size:2.5rem; }
  .card-header h2 { font-size:1.2rem; font-weight:700; margin:8px 0 0; }
  .txn-box { background:#e8f5f5; border:2px dashed #2b7a78; border-radius:8px;
             text-align:center; padding:18px; margin:20px 0; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#2b7a78; letter-spacing:.04em; }
  .btn-portal { background:#2b7a78; color:#fff; font-weight:600; border:none; border-radius:8px; padding:10px 24px; width:100%; }
  .btn-portal:hover { background:#1d5e5c; color:#fff; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <i class="bi bi-check-circle-fill"></i>
    <h2>Application Submitted Successfully!</h2>
  </div>
  <div class="card-body p-4">
    <p class="text-center" style="font-size:.9rem;">
      Thank you, <strong>{{ $candidate->full_name }}</strong>!<br>
      Your application for <strong>{{ $position }}</strong> has been received.
    </p>

    <div class="txn-box">
      <div class="label">Your Transaction Number</div>
      <div class="number">{{ $transactionNumber }}</div>
    </div>

    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Please keep your transaction number for follow-up inquiries.
    </p>

    <a href="{{ route('portal.dashboard') }}" class="btn btn-portal mt-2">
      <i class="bi bi-house me-1"></i> Go to My Dashboard
    </a>

    <p style="font-size:.75rem;color:#888;text-align:center;margin-top:16px;">
      All data is treated as confidential in compliance with the Data Privacy Act of 2012.
    </p>
  </div>
</div>
</body>
</html>
BLADE, 'portal/submitted.blade.php');

// ─── 7. Patch CandidateAuthController ────────────────────────────────────────

$ctrlPath = "$root/app/Http/Controllers/CandidateAuthController.php";
$ctrl     = file_get_contents($ctrlPath) or die_loud("Cannot read $ctrlPath");
backup($ctrlPath);

// Add Mail import after the existing use statements
$ctrl = patch($ctrl,
    "use App\\Models\\Candidate;\n",
    "use App\\Models\\Application;\nuse App\\Models\\Candidate;\nuse App\\Mail\\ApplicationSubmitted;\nuse Illuminate\\Support\\Facades\\Mail;\n",
    'add use statements'
);

// Replace the register() method entirely
// Anchor: find the showRegister() + register() block
$oldRegister = <<<'OLD'
    public function showRegister()
    {
        return view('portal.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:candidates,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $candidate = Candidate::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        Auth::guard('candidate')->login($candidate);

        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }
OLD;

$newRegister = <<<'NEW'
    public function showRegister()
    {
        return view('portal.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            // Personal
            'first_name'       => ['required', 'string', 'max:255'],
            'middle_name'      => ['nullable', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'position_applied' => ['required', 'string', 'max:255'],
            'address'          => ['required', 'string', 'max:500'],
            'age'              => ['required', 'integer', 'min:18', 'max:70'],
            'sex'              => ['required', 'in:Male,Female'],
            'civil_status'     => ['required', 'in:Single,Married,Legally Separated,Widowed'],
            'religion'         => ['required', 'string', 'max:100'],
            'disability'       => ['required', 'string', 'max:255'],
            'ethnic_group'     => ['required', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:50'],
            'email'            => ['required', 'email', 'max:255', 'unique:candidates,email'],
            // Qualifications
            'education'        => ['required', 'string', 'max:500'],
            'training_hours'   => ['required', 'string', 'max:100'],
            'years_experience' => ['required', 'string', 'max:100'],
            'eligibility'      => ['required', 'string', 'max:255'],
            // Account
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $candidate = Candidate::create([
            'first_name'       => $validated['first_name'],
            'middle_name'      => $validated['middle_name'] ?? null,
            'last_name'        => $validated['last_name'],
            'email'            => $validated['email'],
            'password'         => $validated['password'],
            'phone'            => $validated['phone'],
            'address'          => $validated['address'],
            'age'              => $validated['age'],
            'sex'              => $validated['sex'],
            'civil_status'     => $validated['civil_status'],
            'religion'         => $validated['religion'],
            'disability'       => $validated['disability'],
            'ethnic_group'     => $validated['ethnic_group'],
            'education'        => $validated['education'],
            'training_hours'   => $validated['training_hours'],
            'years_experience' => $validated['years_experience'],
            'eligibility'      => $validated['eligibility'],
        ]);

        // Generate transaction number and create the application record
        $txn = Application::generateTransactionNumber();

        // We won't create an Application row here — the candidate can apply
        // to specific open postings from the portal. We store the txn on the
        // candidate profile temporarily for the confirmation page/email.

        Auth::guard('candidate')->login($candidate);
        $request->session()->regenerate();

        // Send confirmation email (non-blocking — catches any mail failure)
        try {
            Mail::to($candidate->email)
                ->send(new ApplicationSubmitted($candidate, $txn, $validated['position_applied']));
        } catch (\Throwable $e) {
            \Log::error('Recruitment confirmation email failed: ' . $e->getMessage());
        }

        return view('portal.submitted', [
            'candidate'         => $candidate,
            'transactionNumber' => $txn,
            'position'          => $validated['position_applied'],
        ]);
    }
NEW;

$ctrl = patch($ctrl, $oldRegister, $newRegister, 'register() method');
write($ctrlPath, $ctrl, 'CandidateAuthController.php', over:true);

// ─── Done ────────────────────────────────────────────────────────────────────
echo <<<TXT

✅ Done! Run:

  php artisan migrate
    → adds 10 new columns to candidates table

Then test:
  /portal/register  → fill the full recruitment form → submit
  → success page shows transaction number
  → confirmation email lands in Mailtrap inbox

TXT;
