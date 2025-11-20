<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $settingsArr['name'] ?? 'Platform' }} | Set/Reset Password</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color:#333; font-size:16px; }
    .container { max-width:600px; margin: 30px auto; border:1px solid #d8d8d8; background:#fff; padding:35px; }
    .btn { background:#1dbf73; color:#fff; padding:12px 30px; border-radius:5px; text-decoration:none; font-weight:600; }
    .brand-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
    .brand-title { font-size:2.2em; font-weight:700; color:#1877c2; letter-spacing:-0.04em; }
    @media (max-width:600px) {.container{padding:16px;}.btn{padding:10px 16px;}}
  </style>
</head>
<body>
<div class="container">
  <div class="brand-header">
      <span style="display:inline-block;height:38px;width:38px;">
          @if(!empty($settingsArr['favicon']))
            <img src="{{ $settingsArr['favicon'] }}" height="38" width="38" style="display:block;">
          @else
            <svg width="38" height="38"><circle cx="19" cy="19" r="19" fill="#36d399"/><title>Logo</title></svg>
          @endif
      </span>
      <span class="brand-title">{{ $settingsArr['name'] ?? 'mochanai platform' }}</span>
  </div>
  <p>
    Hi {{ $name ?? 'there' }},<br><br>
    To set or reset your password for <strong>{{ $settingsArr['name'] ?? 'our platform' }}</strong>, click below:
  </p>
  @php
    $resetUrl = rtrim($settingsArr['head_code'] ?? 'https://mochanai.com/', '/')
        . '/reset_password/' . $token . '?email=' . urlencode($email);
  @endphp
  <p style="margin:40px 0 0 0; text-align:center;">
    <a href="{{ $resetUrl }}" class="btn" target="_blank">Set / Reset Password</a>
  </p>
  <p style="font-size:15px; margin:30px 0 10px 0; color:#888; text-align:center;">
    If the button does not work, copy and paste this link:<br>
    {{ $resetUrl }}
  </p>
  <hr style="margin:35px 0;">
  <p style="font-size:13px; color:#aaa; text-align:center;">&copy; {{ date('Y') }} {{ $settingsArr['name'] ?? 'mochanai platform' }}</p>
</div>
</body>
</html>