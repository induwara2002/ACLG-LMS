<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $libraryName }}</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#1f2937;font-family:Arial,sans-serif;line-height:1.6;">
    <main style="max-width:600px;margin:24px auto;padding:28px;background:#fff;border-radius:8px;">
        <h1 style="margin-top:0;color:#1f4d3a;font-size:22px;">{{ $libraryName }}</h1>
        <p>Hello {{ $memberName }},</p>

        @if ($alertType === 'member_registered')
            <p>Welcome. Your library membership has been registered successfully.</p>
        @elseif ($alertType === 'book_issued')
            <p>The following book has been issued to you.</p>
        @elseif ($alertType === 'due_date_reminder')
            <p>This is a reminder that the following borrowed book is approaching its return due date.</p>
        @else
            <p>This is a reminder that your library membership is approaching its expiry date.</p>
        @endif

        <table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0;">
            @foreach ($details as $label => $value)
                <tr>
                    <th style="padding:9px 12px;border-bottom:1px solid #e5e7eb;text-align:left;">{{ $label }}</th>
                    <td style="padding:9px 12px;border-bottom:1px solid #e5e7eb;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p>Regards,<br>{{ $libraryName }}</p>
    </main>
</body>
</html>
