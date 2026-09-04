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
                                    {{ __('admin_suggestions.mail_greeting', ['name' => $name]) }}
                                </p>
                                <p style="margin:0 0 24px; font-size:14px; color:#475569;">
                                    {{ __('admin_suggestions.mail_body') }}
                                </p>
                                <div style="margin:0 0 24px; padding:16px; background:#f1f5f9; border-radius:12px; text-align:center;">
                                    <span style="font-size:20px; font-weight:700; color:#0f172a;">{{ $churchName }}</span>
                                </div>
                                <p style="margin:0 0 24px; font-size:13px; color:#64748b;">
                                    {{ __('admin_suggestions.mail_next_steps') }}
                                </p>
                                <table role="presentation" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="border-radius:10px; background:#2563eb;">
                                            <a href="{{ route('login') }}" style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
                                                {{ __('admin_suggestions.mail_cta') }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
