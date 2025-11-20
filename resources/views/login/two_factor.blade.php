@extends('template') 
@section('main')
<div class="container mb-4 margin-top-85 min-height">
	<div class="d-flex justify-content-center">
		<div class="p-5 mt-5 mb-5 border w-450" >
    @if(Session::has('message'))
        <div class="alert alert-info">{{ Session::get('message') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h3>Verify your login</h3>
    <p>We’ve sent a 6-digit verification code to your email. Enter it below to continue.</p>

    <form method="POST" action="{{ route('2fa.verify') }}">
        @csrf
        <div class="form-group">
            <label for="code">Verification code</label>
            <input id="code" type="text" name="code" class="form-control" maxlength="6" minlength="6" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Verify</button>
    </form>

    <form method="POST" action="{{ route('2fa.resend') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link">Resend code</button>
    </form>
</div>
      </div>
  </div>
@stop
