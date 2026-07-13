<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qualification Result</title>
</head>
<body style="margin:0; padding:0; background:#eef1f6; font-family:'Segoe UI', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10);">

                    {{-- Header --}}
                    <tr>
                        <td style="background:linear-gradient(120deg, #003087 0%, #0a1a33 100%); border-bottom:4px solid #ffd700; padding:26px 32px 22px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="56" valign="middle">
                                        <img src="{{ asset('images/deped-logo.png') }}" alt="DepEd" width="48" height="48" style="border-radius:50%; background:#fff; padding:4px; display:block;">
                                    </td>
                                    <td valign="middle" style="padding-left:14px;">
                                        <p style="margin:0; color:#fff; font-size:1.05rem; font-weight:800; line-height:1.3;">
                                            Schools Division Office &ndash; Cavite Province
                                        </p>
                                        <p style="margin:4px 0 0; color:#fff; opacity:.9; font-size:.8rem;">
                                            HR Recruitment and Appointment System
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <p style="margin:0 0 4px; font-size:.85rem; color:#888;">Transaction No.</p>
                            <p style="margin:0 0 20px; font-family:monospace; font-weight:700; color:#003087;">{{ $application->transaction_number }}</p>

                            <p style="margin:0 0 16px; font-size:.95rem; color:#222;">
                                Dear {{ $candidate->full_name }},
                            </p>

                            @if ($passed)
                                <div style="background:#e9f9ef; border-left:4px solid #1a7d3a; padding:16px 20px; border-radius:6px; margin-bottom:20px;">
                                    <p style="margin:0; font-weight:700; color:#1a7d3a; font-size:1rem;">
                                        You meet the qualification standards for this position.
                                    </p>
                                </div>
                                <p style="margin:0 0 16px; font-size:.9rem; color:#444; line-height:1.6;">
                                    Congratulations! Based on our review of your submitted documents against the
                                    qualification standards for <strong>{{ $jobPosting->title ?? 'the position' }}</strong>,
                                    your application has been marked <strong>Qualified</strong>. Your application will now proceed
                                    to the next stage of the recruitment process. We will notify you of further updates,
                                    including any scheduled interviews or assessments.
                                </p>
                            @else
                                <div style="background:#fdeceb; border-left:4px solid #b3261e; padding:16px 20px; border-radius:6px; margin-bottom:20px;">
                                    <p style="margin:0; font-weight:700; color:#b3261e; font-size:1rem;">
                                        You do not currently meet the qualification standards for this position.
                                    </p>
                                </div>
                                <p style="margin:0 0 16px; font-size:.9rem; color:#444; line-height:1.6;">
                                    Thank you for your interest in <strong>{{ $jobPosting->title ?? 'this position' }}</strong>
                                    at the Schools Division Office of Cavite Province. After careful review of your
                                    submitted documents against the position's qualification standards, we regret to
                                    inform you that your application has been marked <strong>Disqualified</strong>
                                    at this stage. We encourage you to apply again for future postings that match
                                    your qualifications.
                                </p>
                            @endif

                            <p style="margin:24px 0 0; font-size:.85rem; color:#888;">
                                If you have questions about this result, please contact the HR office of the
                                Schools Division Office of Cavite Province.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 24px; border-top:1px solid #eee; text-align:center;">
                            <p style="margin:0; font-size:.75rem; color:#999;">
                                This is an automated message from the DepEd Cavite HR Recruitment and Appointment System.
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
