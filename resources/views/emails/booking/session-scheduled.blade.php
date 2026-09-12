<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your {{ $subjectName }} session is scheduled</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                    @include('emails.partials.header')

                    <tr>
                        <td style="padding: 40px 40px 24px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; line-height: 28px; color: #111827;">
                                Session scheduled
                            </h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #4b5563;">
                                Hi {{ $studentFirstName }}, {{ $tutorName }} scheduled your <strong>{{ $subjectName }}</strong> session.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 16px; background-color: #f3f4f6; border-radius: 8px;">
                                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #374151;">
                                            {{ $date }}<br>
                                            {{ $startTime }} &ndash; {{ $endTime }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $bookingUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #f59e0b; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 999px;">
                                            View Booking
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @include('emails.partials.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
