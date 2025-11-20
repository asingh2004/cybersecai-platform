Hi {{ $user->first_name }},

Your account has been created on {{ config('app.name') }}.

You have been approved and can now start using your account!

Click below to set your password and log in for the first time:
<br><br>
<a href="{{ $url }}">{{ $url }}</a>
<br><br>
If you did not request this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }} Support Team


