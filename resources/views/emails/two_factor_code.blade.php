<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your verification code</title>
</head>
<body>
    <p>Hi {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }},</p>
    <p>Your verification code is:</p>
    <p style="font-size: 22px; font-weight: bold;">{{ $code }}</p>
    <p>This code will expire in 10 minutes. If you didn’t try to login, you can ignore this message.</p>
    <p>Thanks,</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>