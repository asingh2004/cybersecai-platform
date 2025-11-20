<!-- resources/views/sendmail/saas_admin_notify.blade.php -->
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>New User Approval Needed</title>
</head>
<body>
  <div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 30px auto; border:1px solid #d8d8d8; background: #fff; padding: 35px;">
    <div style="display:flex;align-items:center;gap:10px; margin-bottom: 20px;">
      <span style="display:inline-block;height:38px;width:38px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="38" height="38" aria-label="mochanai.com logo">
          <defs>
            <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0%" stop-color="#1877c2"/>
              <stop offset="100%" stop-color="#36d399"/>
            </linearGradient>
          </defs>
          <path d="M48 8C67 17 84 20 84 37c0 38-27.5 50-36 54C39.5 87 12 75 12 37c0-17 17-20 36-29z"
              fill="#fff" stroke="url(#g1)" stroke-width="5"/>
          <g stroke="#1877c2" stroke-width="1.7">
            <circle cx="48" cy="27" r="5" fill="#36d399"/>
            <circle cx="33" cy="40" r="3.5" fill="#95dbfa"/>
            <circle cx="63" cy="40" r="3.5" fill="#95dbfa"/>
            <circle cx="41" cy="56" r="3.5" fill="#5ad7ba"/>
            <circle cx="55" cy="56" r="3.5" fill="#5ad7ba"/>
            <line x1="48" y1="27" x2="33" y2="40"/>
            <line x1="48" y1="27" x2="63" y2="40"/>
            <line x1="33" y1="40" x2="41" y2="56"/>
            <line x1="63" y1="40" x2="55" y2="56"/>
            <line x1="41" y1="56" x2="55" y2="56"/>
          </g>
          <title>mochanai.com Security + AI Logo</title>
        </svg>
      </span>
      <span style="font-size:2.2em;font-weight:700;color:#1877c2;letter-spacing:-0.04em;">mochanai platform</span>
    </div>
    <p>
      <b>New user registration requires approval:</b><br><br>
      <b>Name:</b> {{ $name }}<br>
      <b>Email:</b> {{ $email }}<br><br>
      <a href="https://cybersecai.io/admin/dashboard" target="_blank"
         style="display:inline-block; padding:12px 30px; background:#1dbf73; color:#fff; text-decoration:none; border-radius:4px;">
        Go to Admin Dashboard
      </a>
    </p>
    <p style="color:#aaa; font-size:13px; margin-top:30px">&copy; {{ date('Y') }} mochanai platform</p>
  </div>
</body>
</html>