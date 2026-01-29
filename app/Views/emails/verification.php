<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
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
            background-color: #3498db;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>!</h1>
        </div>

        <div class="content">
            <h2>Hello, <?= esc($username ?? 'User') ?>!</h2>

            <p>Thank you for registering. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>

            <div style="text-align: center;">
                <a href="<?= esc($verification_link) ?>" class="button">Verify Email Address</a>
            </div>

            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #3498db;">
                <?= esc($verification_link) ?>
            </p>

            <div class="warning">
                <p style="margin: 0;">
                    <strong>⏰ This link expires on <?= esc($expires_at ?? 'soon') ?></strong>
                </p>
                <p style="margin: 5px 0 0 0;">
                    If you didn't create an account, you can safely ignore this email.
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
