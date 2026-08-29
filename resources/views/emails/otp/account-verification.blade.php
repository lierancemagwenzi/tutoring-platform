<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email address</title>
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
                                Verify your email address
                            </h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #4b5563;">
                                Hi {{ $firstName }}, use the code below to verify your account. Enter it on the Verify Account screen to finish setting up your account.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 16px 0; background-color: #f3f4f6; border-radius: 8px;">
                                        <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #111827;">
                                            {{ $code }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0; font-size: 13px; line-height: 20px; color: #6b7280;">
                                This code expires in {{ $expiresInMinutes }} minutes. If you didn't create an account, you can ignore this email.
                            </p>
                        </td>
                    </tr>

                    @include('emails.partials.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
