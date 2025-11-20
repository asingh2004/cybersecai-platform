
@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h3>Step 2: Policy Info</h3>
    <form method="POST" action="{{route('cybersecaiagents.policySubmit')}}" enctype="multipart/form-data">
        @csrf
        <label>Policy name: <input type="text" name="policy_name" required class="form-control"></label><br>
        <label>Policy URL: <input type="url" name="policy_url" class="form-control"></label><br>
        <label>Or upload a file: <input type="file" name="policy_file" class="form-control"></label><br>
        <button class="btn btn-primary" type="submit">Save and Continue</button>
    </form>
</div></div></div></div></div></div>
@endsection