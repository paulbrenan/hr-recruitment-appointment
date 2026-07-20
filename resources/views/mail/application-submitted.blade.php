<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#003087; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#e6ecf7; border:2px dashed #0047b3; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.35rem; font-weight:800; color:#003087; letter-spacing:.05em; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .detail-row { display:flex; padding:5px 0; border-bottom:1px solid #f5f5f5; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:170px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .req-block { background:#fafbfc; border:1px solid #e3e8ec; border-radius:6px; padding:18px 22px; margin-top:8px; font-size:.82rem; }
  .req-block h4 { margin:0 0 10px; font-size:.88rem; color:#003087; font-weight:700; }
  .req-block ol { margin:0; padding-left:18px; }
  .req-block ol li { margin-bottom:6px; line-height:1.55; }
  .req-block ul { margin:4px 0 8px 0; padding-left:16px; }
  .req-block ul li { margin-bottom:3px; }
  .req-block .sub-title { font-weight:600; margin:10px 0 4px; color:#333; }
  .add-req { background:#fff8e1; border:1px solid #ffe082; border-radius:6px; padding:18px 22px; margin-top:12px; font-size:.82rem; }
  .add-req h4 { margin:0 0 10px; font-size:.88rem; color:#b45309; font-weight:700; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
  .deped-logo { text-align:center; margin-bottom:8px; }
</style>
</head>
<body>
<div class="wrap">

  {{-- Header --}}
  <div class="header">
    <span class="check-icon">&#10003;</span>
    <h1>Application Submitted Successfully!</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>
    <p>
      Thank you for submitting your application to the DepEd Schools Division Office of Cavite Province.
      Your submission has been received and is now under initial review by the Human Resource Unit.
    </p>

    <div style="text-align:center; margin:20px 0;">
      <a href="{{ url('/?txn=' . $transactionNumber) }}"
         style="background:#003087; color:#fff; text-decoration:none; padding:12px 32px;
                border-radius:6px; font-weight:700; font-size:.9rem; display:inline-block;">
        View My Application Status
      </a>
    </div>
    <p style="text-align:center; font-size:.78rem; color:#888; margin-top:-8px;">
      Or copy this link: {{ url('/?txn=' . $transactionNumber) }}
    </p>

    @if (isset($jobPosting) && $jobPosting->memoPdfUrl())
    <div style="text-align:center; margin:14px 0 4px;">
      <a href="{{ $jobPosting->memoPdfUrl() }}" target="_blank" rel="noopener"
         style="color:#003087; font-weight:700; font-size:.85rem; text-decoration:underline;">
        📄 View the Official Memo (PDF)
      </a>
    </div>
    @endif

    {{-- Transaction Number --}}
    <div class="txn">
      <div class="lbl">Your Transaction Number</div>
      <div class="num">{{ $transactionNumber }}</div>
    </div>
    <p style="font-size:.82rem;color:#555;text-align:center;margin-top:-8px;">
      Please keep this transaction number for your records and follow-up inquiries.
    </p>

    {{-- Application Details --}}
    <div class="section-title">Application Details</div>
    <div class="detail-row"><span class="lbl">Position Applied For</span><span class="val">{{ $position }}</span></div>
    <div class="detail-row"><span class="lbl">Name</span><span class="val">{{ $candidate->full_name }}</span></div>
    <div class="detail-row"><span class="lbl">Email Address</span><span class="val">{{ $candidate->email }}</span></div>
    <div class="detail-row"><span class="lbl">Contact No.</span><span class="val">{{ $candidate->phone ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Address</span><span class="val">{{ $candidate->address ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Age</span><span class="val">{{ $candidate->age ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Sex</span><span class="val">{{ $candidate->sex ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Civil Status</span><span class="val">{{ $candidate->civil_status ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Religion</span><span class="val">{{ $candidate->religion ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Disability</span><span class="val">{{ $candidate->disability ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Ethnic Group</span><span class="val">{{ $candidate->ethnic_group ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Highest Education</span><span class="val">{{ $candidate->education ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Training Hours</span><span class="val">{{ $candidate->training_hours ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Years of Experience</span><span class="val">{{ $candidate->years_experience ?? '—' }}</span></div>
    <div class="detail-row"><span class="lbl">Eligibility</span><span class="val">{{ $candidate->eligibility ?? '—' }}</span></div>

    {{-- Mandatory Requirements --}}
    <div class="section-title">Mandatory Requirements to Submit</div>
    <p style="font-size:.82rem;color:#555;margin:-4px 0 10px;">
      Please prepare and submit the following documents to the Schools Division Office:
    </p>
    <div class="req-block">
      <h4>Mandatory Requirements:</h4>
      <ol type="A">
        <li>Letter of intent addressed to the Schools Division Superintendent</li>
        <li>Duly Accomplished Personal Data Sheet (CSC Form No. 212, Revised 2025) with latest passport size picture and Work Experience Sheet, if applicable</li>
        <li>Photocopy of valid and updated PRC License/ID, if applicable</li>
        <li>Photocopy of Certificate of Eligibility/Rating, if applicable</li>
        <li>Photocopy of scholastic/academic record such as but not limited to Transcript of Records (TOR) and Diploma, <strong>with computation of General Weighted Average (GWA)</strong>, including completion of graduate and post graduate units/degrees, if available</li>
        <li>Photocopy of Certificates of Training, if applicable</li>
        <li>Photocopy of Certificate of Employment, Contract of Service, or duly signed Service Record, whichever is/are applicable</li>
        <li>Photocopy of the latest appointment, if applicable</li>
        <li>Photocopy of Performance Rating in the last rating period(s) covering one (1) year performance in the current/latest position, if applicable</li>
        <li>
          Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, <strong>signed by authorized official (e.g., Brgy. Captain)</strong>.<br>
          Access and download here: <a href="http://tinyurl.com/ChecklistOfReqtOmnibus" style="color:#003087;">http://tinyurl.com/ChecklistOfReqtOmnibus</a>
        </li>
      </ol>
    </div>

    {{-- Additional Requirements --}}
    <div class="add-req">
      <h4>Additional Requirements:</h4>
      <p style="margin:0 0 8px;">A. Means of Verification showing Outstanding Accomplishments, Application of Education, and Application of Learning and Development, reckoned from the date of last issuance of appointment (if any):</p>

      <div class="sub-title">1. Awards and Recognition</div>
      <ul>
        <li><strong>a. Citation or Commendation</strong> — Letter of Citation or Commendation from previous employer</li>
        <li><strong>b. Academic or Inter-School Awards</strong> — Academic or inter-school award; Ten Outstanding Students of the Philippines (TOSP) Award; or Certification of belonging to Top 10 in Board or Civil Service Eligibility Examination</li>
        <li><strong>c. Outstanding Employee Award</strong> — Any issuance, memorandum or document showing the Criteria for the Search; and Certificate of Recognition/Merit</li>
      </ul>

      <div class="sub-title">2. Research and Innovation</div>
      <ul>
        <li>Proposal duly approved by the Head of Office or designated Research Committee per DepEd Order No. 16, s. 2017</li>
        <li>Accomplishment Report verified by the Head of Office</li>
        <li>Certification of utilization of the innovation or research within the school/office duly signed by the Head of Office</li>
        <li>Certification of adoption of the innovation or research by another school/office duly signed by the Head of Office</li>
        <li>Proof of citation by other researchers (approved by authorized body)</li>
      </ul>

      <div class="sub-title">3. Subject Matter Expert / Membership in National Technical Working Groups or Committees</div>
      <ul>
        <li>Issuance/Memorandum showing the membership in NTWG or Committees</li>
        <li>Certificate of Participation or Attendance</li>
        <li>Output/Adoption by the organization/DepEd</li>
      </ul>

      <div class="sub-title">4. Resource Speakership / Learning Facilitation</div>
      <ul>
        <li>Issuance/Memorandum/Invitation/Training Matrix</li>
        <li>Certificate of Recognition Merit/Commendation/Appreciation</li>
        <li>Slide deck/s used and/or Session guide/s</li>
      </ul>

      <div class="sub-title">5. NEAP Accredited Learning Facilitator</div>
      <ul>
        <li>Certificate of Recognition as Learning Facilitator issued by NEAP Central or Regional Office</li>
      </ul>

      <div class="sub-title">6. Application of Education</div>
      <ul>
        <li>Action Plan approved by the Head of Office</li>
        <li>Accomplishment Report verified by the Head of Office</li>
        <li>Certification of the utilization/adoption signed by the Head of Office</li>
      </ul>

      <div class="sub-title">7. Application of Learning and Development</div>
      <ul>
        <li>Certificate of Training or Certification on any applicable L&D intervention aligned with the Individual Development Plan (IDP)</li>
        <li>Action Plan/Re-entry Action Plan (REAP), Job Embedded Learning (JEL)/Impact Project applying the learnings from the L&D intervention, duly approved by the Head of Office</li>
        <li>Accomplishment Report together with a General Certification that the L&D intervention was used/adopted at the local level</li>
        <li>Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by a different office at the local/higher level</li>
      </ul>

      <div class="sub-title">8. Performance Rating</div>
      <ul>
        <li>Photocopy of the Performance Rating obtained from the relevant work experience if latest performance rating is not relevant to the position applying for</li>
      </ul>
    </div>

    <div class="note">
      <strong>📌 Data Privacy Notice:</strong><br>
      All data gathered from this form shall be used solely by the Human Resource Unit for Initial Evaluation
      and Assessment of Applicants' documents purposes and shall be treated as confidential in compliance
      to the Data Privacy Act of 2012.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      For inquiries, please contact the Human Resource Unit at:<br>
      📍 Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite<br>
      📞 (046) 419-1286, 412-0349<br>
      🌐 <a href="http://www.depedcavite.com.ph" style="color:#003087;">www.depedcavite.com.ph</a><br>
      ✉️ deped.cavite@deped.gov.ph
    </p>
  </div>

  <div class="footer">
    <img src="{{ $message->embed(public_path('images/deped-logo.png')) }}" alt="DepEd Logo" width="36" height="36" style="width:36px;height:36px;border-radius:50%;margin-bottom:8px;display:inline-block;">
    <br>
    DepEd Schools Division Office of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply directly to this message.
  </div> 
</div>
</body>
</html>