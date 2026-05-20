{{-- Blade view for the reset-password-link page in the RSRS application. --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin:0; padding:0; background:#eef8ff; font-family:Arial, Helvetica, sans-serif; color:#1d2a36;">
    <div style="width:100%; background:linear-gradient(180deg, #f9fdff 0%, #eef8ff 100%); padding:24px 12px;">
        <div style="max-width:580px; margin:0 auto; background:rgba(255,255,255,0.98); border:1px solid rgba(16,152,212,0.16); border-radius:24px; overflow:hidden; box-shadow:0 20px 40px rgba(13,111,155,0.12);">
            <div style="padding:24px 22px 16px; background:linear-gradient(135deg, #f9feff 0%, #eefaff 45%, #ffffff 100%); border-bottom:1px solid rgba(16,152,212,0.12); text-align:center;">
                <div style="display:inline-block; padding:8px 14px; border-radius:999px; background:rgba(16,152,212,0.08); border:1px solid rgba(16,152,212,0.14); color:#0d6f9b; font-size:12px; font-weight:400; letter-spacing:0.08em; text-transform:uppercase;">
                    RSRS Password Recovery
                </div>
                <h1 style="margin:16px 0 10px; font-size:28px; line-height:1.2; color:#102f45;">Reset Your Password</h1>
                <p style="margin:0 auto; max-width:430px; font-size:15px; line-height:1.75; color:#516d80;">
                    A request was received to reset the password for your Road Safety Reporting System account.
                </p>
            </div>

            <div style="padding:24px 22px;">
                <p style="margin:0 0 14px; font-size:15px; line-height:1.75; color:#516d80; word-break:break-word;">
                    Hello{{ !empty($user->name) ? ' ' . e($user->name) : '' }},
                </p>
                <p style="margin:0 0 18px; font-size:15px; line-height:1.75; color:#516d80;">
                    Click the button below to choose a new password. This reset link will expire in {{ $expireMinutes }} minutes.
                </p>

                <div style="text-align:center; margin:26px 0;">
                    <a href="{{ $resetUrl }}" style="display:inline-block; padding:14px 28px; border-radius:999px; background:#1098d4; color:#ffffff; text-decoration:none; font-size:15px; font-weight:400; box-shadow:0 16px 30px rgba(13,111,155,0.18);">
                        Reset Password
                    </a>
                </div>

                <div style="padding:16px; border-radius:18px; background:#f6fcff; border:1px solid rgba(16,152,212,0.12);">
                    <p style="margin:0 0 10px; font-size:14px; font-weight:400; color:#102f45;">If the button does not work, use this link:</p>
                    <p style="margin:0;">
                        <a href="{{ $resetUrl }}" style="display:block; max-width:100%; color:#0d6f9b; text-decoration:none; font-size:12px; line-height:1.8; word-break:break-all; overflow-wrap:anywhere;">{{ $resetUrl }}</a>
                    </p>
                </div>

                <div style="margin-top:20px; padding:16px 18px; border-radius:18px; background:rgba(255, 243, 205, 0.58); border:1px solid rgba(255, 193, 7, 0.28);">
                    <p style="margin:0; font-size:14px; line-height:1.75; color:#7a5a00;">
                        If this request was not made by you, please do not take any action. You can safely ignore this email and your current password will remain unchanged.
                    </p>
                </div>
            </div>

            <div style="padding:18px 22px 22px; border-top:1px solid rgba(16,152,212,0.12); background:#fcfeff; text-align:center;">
                <p style="margin:0 0 6px; font-size:13px; font-weight:400; color:#102f45;">Road Safety Reporting System</p>
                <p style="margin:0; font-size:12px; line-height:1.7; color:#6b8597;">
                    RSRS Monitoring Dashboard
                </p>
            </div>
        </div>
    </div>
</body>
</html>
