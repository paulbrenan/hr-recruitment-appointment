<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* dompdf only understands a subset of CSS -- keep this simple:
       table-based layout, no flexbox/grid, no CSS variables. */
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #212529;
        margin: 0;
        padding: 0;
    }
    .header {
        background-color: #003087;
        color: #ffffff;
        padding: 16px 24px;
    }
    .header .gold-bar {
        height: 4px;
        background-color: #ffcc00;
    }
    .header h1 {
        margin: 0;
        font-size: 16px;
    }
    .header p {
        margin: 2px 0 0 0;
        font-size: 10px;
        color: #cfd8ea;
    }
    .content {
        padding: 20px 24px;
    }
    .job-title {
        font-size: 15px;
        font-weight: bold;
        color: #003087;
        margin-bottom: 2px;
    }
    .job-meta {
        font-size: 10px;
        color: #555555;
        margin-bottom: 16px;
    }
    .section-title {
        font-size: 11px;
        font-weight: bold;
        color: #003087;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 3px;
        margin-top: 16px;
        margin-bottom: 8px;
    }
    table.info-table {
        width: 100%;
        border-collapse: collapse;
    }
    table.info-table td {
        padding: 3px 0;
        vertical-align: top;
    }
    table.info-table td.label {
        width: 140px;
        font-weight: bold;
        color: #495057;
    }
    .duties-list, .req-list {
        margin: 0;
        padding-left: 16px;
    }
    .duties-list li, .req-list li {
        margin-bottom: 4px;
    }
    .footer {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        font-size: 9px;
        color: #6c757d;
        text-align: center;
    }
</style>
</head>
<body>
    <div class="header">
        <h1>Job Description</h1>
        <p>Schools Division Office of Cavite &mdash; Department of Education</p>
        <div class="gold-bar"></div>
    </div>

    <div class="content">
        <div class="job-title">{{ $posting->title }}</div>
        <div class="job-meta">
            SG {{ $posting->salary_grade }}
            @if ($posting->employment_type) &middot; {{ $posting->employment_type }} @endif
            @if ($posting->place_of_assignment) &middot; {{ $posting->place_of_assignment }} @endif
        </div>

        @if ($posting->locations && $posting->locations->count() > 1)
        <div class="section-title">Place(s) of Assignment</div>
        <table class="info-table">
            @foreach ($posting->locations as $loc)
            <tr>
                <td>{{ $loc->place_of_assignment }}</td>
                <td style="text-align:right; width:100px;">{{ $loc->vacancies }} vacanc{{ $loc->vacancies == 1 ? 'y' : 'ies' }}</td>
            </tr>
            @endforeach
        </table>
        @endif

        <div class="section-title">Minimum Qualifications</div>
        <table class="info-table">
            <tr>
                <td class="label">Education</td>
                <td>{{ $posting->qualification_education ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Training</td>
                <td>{{ $posting->qualification_training ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Experience</td>
                <td>{{ $posting->qualification_experience ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Eligibility</td>
                <td>{{ $posting->qualification_eligibility ?: 'Not specified' }}</td>
            </tr>
        </table>

        @if ($posting->duties_responsibilities)
        <div class="section-title">Duties and Responsibilities</div>
        <ul class="duties-list">
            @foreach (array_filter(array_map('trim', explode(';', $posting->duties_responsibilities))) as $duty)
                <li>{{ $duty }}</li>
            @endforeach
        </ul>
        @endif

        @if (!empty($posting->mandatory_requirements))
        <div class="section-title">Mandatory Requirements</div>
        <ul class="req-list">
            @foreach (array_filter(array_map('trim', explode("\n", $posting->mandatory_requirements))) as $req)
                <li>{{ $req }}</li>
            @endforeach
        </ul>
        @endif
    </div>

    <div class="footer">
        Generated {{ now()->format('F d, Y') }} &mdash; DepEd Cavite HR Recruitment System
    </div>
</body>
</html>