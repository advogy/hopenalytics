<!DOCTYPE html>
<html>
    <body style="margin:0; padding:0; background:#f8f4ec; font-family: sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding: 32px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" style="max-width: 480px; background:#ffffff; border-radius: 16px; padding: 32px;">
                        <tr>
                            <td>
                                <p style="margin:0 0 16px; font-size:16px; color:#0f172a;">
                                    {{ __('email_broadcasts.mail_greeting', ['name' => $recipientName]) }}
                                </p>
                                <div style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#334155; white-space:pre-line;">{{ $messageBody }}</div>
                                <p style="margin:0; font-size:12px; color:#94a3b8;">
                                    {{ __('email_broadcasts.mail_footer', ['app' => config('app.name')]) }}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
