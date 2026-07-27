<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 60px; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; line-height: 1.5; }
        .letterhead { text-align: center; margin-bottom: 24px; }
        .letterhead .office { font-weight: bold; font-size: 13pt; text-transform: uppercase; }
        .letterhead .sub { font-size: 10.5pt; }
        .date { margin-bottom: 18px; }
        .addr { margin-bottom: 18px; }
        p { margin: 0 0 12px; text-align: justify; }
        table.qs { width: 100%; border-collapse: collapse; margin: 14px 0 18px; font-size: 10pt; }
        table.qs th, table.qs td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        table.qs th { background: #e5e5e5; font-weight: bold; text-align: left; }
        .remarks-qualified { font-weight: bold; }
        .remarks-not-qualified { font-weight: bold; }
        .code-line { margin: 14px 0; }
        .closing { margin-top: 30px; }
        .chair { margin-top: 46px; font-weight: bold; text-transform: uppercase; }
        .chair .position { font-weight: normal; text-transform: none; font-size: 10.5pt; }
        .chair + .chair { margin-top: 34px; }
    </style>
</head>
<body>

    <div class="letterhead">
        <div class="office">Schools Division Office &ndash; Cavite Province</div>
        <div class="sub">HR Recruitment and Appointment System</div>
    </div>

    <div class="date">{{ \Carbon\Carbon::now()->format('F d, Y') }}</div>

    <div class="addr">
        {{ strtoupper($candidate->full_name) }}<br>
        {{ $candidate->address ?? '' }}
    </div>

    @if ($passed)
        <p>Dear {{ $candidate->full_name }}, Congratulations!</p>

        <p>
            We are pleased to inform you that based on the initial evaluation, we have found your
            qualifications to be substantial vis-&agrave;-vis the Civil Service Commission (CSC) approved
            Qualification Standards (QS) of <strong>{{ $jobPosting->title ?? '[position]' }}</strong> position
            under the Schools Division Office of Cavite Province. Below are the results of the initial
            evaluation conducted by the undersigned dated
            {{ !empty($check['evaluation_date']) ? \Carbon\Carbon::parse($check['evaluation_date'])->format('F d, Y') : \Carbon\Carbon::now()->format('F d, Y') }}:
        </p>
    @else
        <p>Dear {{ $candidate->full_name }},</p>

        <p>
            Please be informed of the results of the initial evaluation of your qualifications
            vis-&agrave;-vis the Civil Service Commission (CSC) approved Qualification Standards (QS) of
            <strong>{{ $jobPosting->title ?? '[position]' }}</strong> position under the Schools Division
            Office of Cavite Province, as follows:
        </p>
    @endif

    <table class="qs">
        <tr>
            <th style="width:20%;">Position Applied for</th>
            <th style="width:32%;">CSC-approved QS of the Position</th>
            <th style="width:32%;">Your Qualifications</th>
            <th style="width:16%;">Remarks</th>
        </tr>
        @foreach ($criteriaRows as $row)
        <tr>
            <td>
                {{ $jobPosting->title ?? '—' }}
                @if (!empty($check['item_number']))
                    <br><span style="font-size:8.5pt;">{{ $check['item_number'] }}</span>
                @endif
            </td>
            <td>{{ $row['label'] }}@if($row['required']):<br>{{ $row['required'] }}@endif</td>
            <td>{{ $row['actual'] ?? '—' }}</td>
            <td class="{{ $row['passed'] ? 'remarks-qualified' : 'remarks-not-qualified' }}">
                {{ $row['passed'] ? 'Qualified' : 'Not qualified' }}
            </td>
        </tr>
        @endforeach
    </table>

    @if ($passed)
        <p class="code-line">
            Please be advised of your assigned application code <strong>{{ $application->transaction_number }}</strong>
            which shall be used as you proceed with the next stage of the selection process. You may refer to
            the official issuances of the Schools Division Office of Cavite Province for additional announcements
            in this regard. For inquiries, you may communicate with the HR office of the Schools Division Office
            of Cavite Province.
        </p>
        <p>Thank you.</p>
    @else
        <p>
            While your qualifications made a favorable impression, we regret to inform you that you did not meet
            the minimum QS set for the <strong>{{ $jobPosting->title ?? '[position]' }}</strong> position. You may,
            however, continue to submit job applications in response to other vacancy announcements that we
            publish at www.csc.gov.ph/careers, DepEd bulletin boards, and our official website.
        </p>
        <p class="code-line">
            The results of the initial evaluation shall be released and posted for transparency purposes. You may
            refer to your assigned application code <strong>{{ $application->transaction_number }}</strong> in the
            official posting of the results.
        </p>
        <p>Thank you and we wish you the best of luck in your future endeavors.</p>
    @endif

    <div class="closing">
        Very truly yours,
        @php
            $qnSignatories = \App\Models\QualificationNoticeSignatory::all();
        @endphp
        @if ($qnSignatories->isNotEmpty())
            @foreach ($qnSignatories as $sig)
            <div class="chair">
                {{ strtoupper($sig->name) }}
                <div class="position">{{ $sig->position }}</div>
            </div>
            @endforeach
        @else
            {{-- No signatories configured yet at /signatories -- fall back
                 to whatever HR typed into the qualification check form. --}}
            <div class="chair">{{ $check['chair_name'] ?? '[Sub-Committee Chair]' }}</div>
        @endif
    </div>

</body>
</html>
