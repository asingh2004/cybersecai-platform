<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $settingsArr['name'] ?? 'Platform' }} - New User Approval Needed</title>
</head>
<body>
  <div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 30px auto; border:1px solid #d8d8d8; background: #fff; padding: 35px;">
    <div style="display:flex;align-items:center;gap:10px; margin-bottom: 20px;">
      <span style="display:inline-block;height:38px;width:38px;">
        @if(isset($settingsArr['favicon']))
            <img src="{{ $settingsArr['favicon'] }}" height="38" width="38" style="display:block;">
        @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="38" height="38"><circle cx="48" cy="48" r="38" fill="#36d399"/><title>Logo</title></svg>
        @endif
      </span>
      <span style="font-size:2.2em;font-weight:700;color:#1877c2;letter-spacing:-0.04em;">
          {{ $settingsArr['name'] ?? 'mochanai platform' }}
      </span>
    </div>
    <p>
      <b>New user registration requires approval:</b><br><br>
      <b>Name:</b> {{ $name }}<br>
      <b>Email:</b> {{ $email }}<br><br>
      <a href="{{ rtrim($settingsArr['head_code'] ?? 'https://cybersecai.io/', '/') . '/admin/dashboard' }}" target="_blank"
         style="display:inline-block; padding:12px 30px; background:#1dbf73; color:#fff; text-decoration:none; border-radius:4px;">
        Go to Admin Dashboard
      </a>
    </p>
    <p style="color:#aaa; font-size:13px; margin-top:30px">
        &copy; {{ date('Y') }} {{ $settingsArr['name'] ?? 'mochanai platform' }}
    </p>
  </div>
</body>
</html>