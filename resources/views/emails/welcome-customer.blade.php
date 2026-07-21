<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Shelfie!</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">
    <table cellpadding="0" cellspacing="0" width="100%" align="center" style="background-color: #fff; width: 100%;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; width: 100%; border-collapse: collapse;">
                    <tr>
                        <td align="center" bgcolor="#2DBB54" style="padding: 20px 0;">
                            <h1 style="font-size: 24px; margin: 0; color: #FFFFFF;">Shelfie</h1>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#FFFFFF" style="padding: 40px 30px;">
                            <p style="font-size: 16px; margin-top: 20px; color: #333333;">
                                Welcome to Shelfie, {{ $user_name }}!
                            </p>
                            <p style="font-size: 16px; color: #333333;">
                                Thank you for creating an account. We are excited to have you on board.
                            </p>
                            <p style="font-size: 16px; color: #333333;">
                                Please verify your email address to get started. If you have any questions, feel free to contact our support team.
                            </p>
                            <p style="font-size: 16px; margin-top: 20px; color: #333333;">
                                Best Regards,
                            </p>
                            <p style="font-size: 16px; color: #333333; margin-top: -13px;">
                                The Shelfie Team
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#2DBB54" align="center" style="padding: 20px 0;">
                            <p style="font-size: 14px; color: #FFFFFF;">&copy; {{ date('Y') }} Shelfie. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
