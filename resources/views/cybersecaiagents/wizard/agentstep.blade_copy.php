@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h3>Step: {{ ucfirst($step) }}</h3>
    @if(isset($resp['next_prompt']))
        <div class="alert alert-info">{!! $resp['next_prompt'] !!}</div>
    @endif

    <form method="POST" action="{{ route('cybersecaiagents.agentStep') }}">
        @csrf
        @if($step == 'policy' && !isset($wizard['policy_url']))
        <label>Policy URL: <input name="policy_url" type="url" class="form-control"></label>
        <button class="btn btn-primary" name="step" value="policy" type="submit">Analyze Policy URL</button>
        @endif

        @if($step == 'policy' && ($wizard['policy_url'] ?? false))
        <button class="btn btn-primary mt-2" name="step" value="discover" type="submit">Start Discovery</button>
        @endif

        @if($step == 'discover' && ($wizard['discovered'] ?? false))
        <button class="btn btn-primary mt-2" name="step" value="classify" type="submit">Start Classification</button>
        @endif

        @if($step == 'classify' && ($wizard['classified'] ?? false))
        <button class="btn btn-success mt-3" name="step" value="visuals" type="submit">Show Visuals</button>
        @endif
    </form>
</div></div></div></div></div></div>
@endsection