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
                                    {{ __('auth.otp_mail_greeting', ['name' => $name]) }}
                                </p>
                                <p style="margin:0 0 24px; font-size:14px; color:#475569;">
                                    {{ __('auth.otp_mail_body') }}
                                </p>
                                <div style="margin:0 0 24px; padding:16px; background:#f1f5f9; border-radius:12px; text-align:center;">
                                    <span style="font-size:28px; font-weight:700; letter-spacing:0.3em; color:#0f172a;">{{ $code }}</span>
                                </div>
                                <p style="margin:0 0 8px; font-size:13px; color:#64748b;">
                                    {{ __('auth.otp_mail_expiry') }}
                                </p>
                                <p style="margin:0; font-size:13px; color:#64748b;">
                                    {{ __('auth.otp_mail_ignore') }}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
