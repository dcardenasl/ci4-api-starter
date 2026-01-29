<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f4f4f4;
            padding: 30px;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #e74c3c;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #c0392b;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
        }
        .security-notice {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>

        <div class="content">
            <h2>Hello!</h2>

            <p>You are receiving this email because we received a password reset request for your account.</p>

            <div style="text-align: center;">
                <a href="<?= esc($reset_link) ?>" class="button">Reset Password</a>
            </div>

            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #e74c3c;">
                <?= esc($reset_link) ?>
            </p>

            <div class="warning">
                <p style="margin: 0;">
                    <strong>⏰ This password reset link expires in <?= esc($expires_in ?? '60 minutes') ?></strong>
                </p>
            </div>

            <div class="security-notice">
                <p style="margin: 0;">
                    <strong>🔒 Security Notice</strong>
                </p>
                <p style="margin: 5px 0 0 0;">
                    If you did not request a password reset, please ignore this email or contact support if you have concerns about your account security.
                </p>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated message, please do not reply.</p>
            <p>&copy; <?= date('Y') ?> <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
