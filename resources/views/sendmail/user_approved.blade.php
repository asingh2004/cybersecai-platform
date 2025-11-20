<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $settingsArr['name'] ?? 'Platform' }} | Account Approved</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color: #222; font-size: 16px; }
    .container { max-width: 600px; margin: 30px auto; border:1px solid #d8d8d8; background: #fff; padding: 35px; }
    .btn { background:#1dbf73; color: #fff; padding:12px 30px; border-radius:5px; text-decoration:none; }
    .brand-header { display: flex; align-items: center; gap: 14px; margin-bottom: 28px;}
    .brand-title { font-size:2.2em;font-weight:700;color:#1877c2;letter-spacing:-0.04em; }
    @media (max-width: 600px) {
      .container { padding: 16px; }
      .btn { padding: 10px 16px; }
    }
  </style>
</head>
<body>
@php
    $loginUrl = rtrim($settingsArr['head_code'] ?? 'https://mochanai.com/', '/') . '/login';
@endphp
<div class="container">
  <div class="brand-header">
      <span style="display:inline-block;height:38px;width:38px;">
        @if(isset($settingsArr['favicon']))
          <img src="{{ $settingsArr['favicon'] }}" height="38" width="38" style="display:block;">
        @else
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="38" height="38"><circle cx="48" cy="48" r="38" fill="#36d399"/><title>Logo</title></svg>
        @endif
      </span>
      <span class="brand-title">{{ $settingsArr['name'] ?? 'mochanai platform' }}</span>
  </div>
  <p>
    Hi {{ $name }},
    <br><br>
    <b>Your account has been approved!</b><br>
    You can now log in to 
    <a href="{{ $loginUrl }}" target="_blank">{{ $settingsArr['name'] ?? 'platform' }}</a> and start using the platform.
  </p>
  <p style="margin:40px 0 0 0">
    <a class="btn" href="{{ $loginUrl }}" target="_blank">Log In Now</a>
  </p>
  <hr style="margin:35px 0;">
  <p style="font-size:12px; color:#888;">&copy; {{ date('Y') }} {{ $settingsArr['name'] ?? 'mochanai platform' }}</p>
</div>
</body>
</html>