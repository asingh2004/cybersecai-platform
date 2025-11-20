@extends('template')

@push('css')
<style>
.demo-card {
    max-width: 410px;
    margin: 80px auto 48px auto;
    border-radius: 18px;
    background: rgba(255,255,255,0.98);
    box-shadow: 0 10px 40px #13284a25;
    padding: 38px 28px 32px 28px;
}
.demo-card h3 { margin-bottom: 27px; font-weight: 800; letter-spacing:-.02em; color: #1877c2;}
.demo-card label {font-weight:600; margin-top:11px; display:inline-block; color: #222;}
.demo-card input, .demo-card textarea {
    width: 100%; font-size: 1.09em; font-family: inherit; margin-top:7px;
    border:1.6px solid #cde3e0; border-radius:8px; padding:12px 13px; margin-bottom:13px;
    background: #f9feff; transition:border .15s;
}
.demo-card input:focus, .demo-card textarea:focus { outline:none; border-color:#36d399; background:#fff;}
.demo-success {background: #defbe6; color: #207364; border:1.6px solid #36d399; border-radius:8px; margin-bottom:13px; padding:12px;}
.demo-submit-btn {
    width:100%; background: linear-gradient(90deg,#36d399 70%,#14a37f 113%);
    border: none; color: #fff; font-weight: 800; font-size:1.13em; padding:15px 0;
    margin-top:17px; border-radius:9px; box-shadow:0 2px 14px #36d39912;
    transition:background .2s; letter-spacing:.03em;
}
.demo-submit-btn:hover { background: linear-gradient(90deg,#14a37f 70%,#33f6ca 113%);}
@media (max-width:500px) {
    .demo-card {padding: 23px 6vw 20px 6vw;}
}
</style>
@endpush

@section('main')
<div class="demo-card">
    <h3 class="w3-center">Request a Demo OR Call Back</h3>
    <form action="{{ route('demo.request') }}" method="POST" autocomplete="off">
        @csrf
        @if(session('message'))
          <div class="demo-success">{{ session('message') }}</div>
        @endif

        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required maxlength="75" value="{{ old('name') }}" placeholder="Your Name">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required maxlength="120" value="{{ old('email') }}" placeholder="your.company@email.com">

        <label for="company">Company / Organization</label>
        <input type="text" id="company" name="company" maxlength="90" value="{{ old('company') }}" placeholder="Org, School, Business...">

        <label for="message">What would you like to see or ask?</label>
        <textarea id="message" name="message" rows="3" required maxlength="900" placeholder="Describe your environment, use-case, or request">{{ old('message') }}</textarea>

        <button class="demo-submit-btn" type="submit">Submit Request</button>
    </form>
</div>
@endsection