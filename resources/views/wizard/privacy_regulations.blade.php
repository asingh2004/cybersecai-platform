@extends('template')

@push('styles')
<style>
    .result-json { background: #ececec; padding: 1em; border-radius: 8px; margin-top: 14px; white-space: pre-wrap; }
</style>
@endpush

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h1><strong>Relevant Privacy Regulations &amp; Standards</strong></h1>
                        <p class="mb-4">
                            Country: <b>{{ $country }}</b><br>
                            Industry/Sector: <b>{{ $industry }}</b>
                        </p>
                        @if(isset($error) && $error)
                            <div class="alert alert-danger">{{ $error }}</div>
                        @endif

                        @if(isset($result) && $result)
                            <div class="alert alert-success mb-3">AI Response:</div>
                            <pre class="result-json">{{ $result }}</pre>
                        @else
                            <div class="alert alert-info">No response yet.</div>
                        @endif

                        <form method="POST" action="{{ route('wizard.privacyRegulations') }}">
                            @csrf
                            <button class="btn btn-primary btn-lg mt-4" type="submit">Request Privacy Regulations/Standards</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection