<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test email from your platform</title>
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
                                Email configuration is working
                            </h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #4b5563;">
                                This is a test email sent from the Admin Quick Setup screen. If you're reading this, your platform's email configuration is working correctly.
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
