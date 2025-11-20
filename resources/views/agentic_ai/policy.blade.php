@extends('layouts.app')
@section('content')
<div class="container">
    <h2 class="mb-4">Policy Enforcer & SIEM Logger</h2>
    <form method="POST" action="{{ route('agentic_ai.policy.run') }}">
        @csrf
        <button class="btn btn-danger">Run Policy & SIEM Check</button>
    </form>
    @if(isset($changes))
        <hr/>
        <h5>Detected Change Files:</h5>
        @if(count($changes))
            <ul>
            @foreach($changes as $chg)
                <li>{{ $chg }}</li>
            @endforeach
            </ul>
        @else
            <p>No .json changes found.</p>
        @endif
    @endif
    @if(isset($policyResults) && count($policyResults))
        <hr/>
        <h5>Policy/Agent Actions:</h5>
        <ul>
            @foreach($policyResults as $pr)
                <li><pre>{{ json_encode($pr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></li>
            @endforeach
        </ul>
    @endif
    @if(isset($siemEvents) && count($siemEvents))
        <hr/>
        <h5>Events sent to SIEM:</h5>
        <ul>
            @foreach($siemEvents as $se)
                <li><pre>{{ json_encode($se, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></li>
            @endforeach
        </ul>
    @endif
</div>
@endsection