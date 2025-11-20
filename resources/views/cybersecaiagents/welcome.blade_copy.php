
@extends('template')
@section('main')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h2>Welcome!</h2>
    <div class="alert alert-info mt-3">
        I am <b>cybersecai.io sensitive data management agent</b>.<br>
        I'll guide you through sensitive data source connection, policy registration, discovery, and classification.
    </div>
    <a href="{{ route('cybersecaiagents.step1') }}" class="btn btn-lg btn-primary mt-4">Get started</a>
</div></div></div></div></div></div>
@endsection