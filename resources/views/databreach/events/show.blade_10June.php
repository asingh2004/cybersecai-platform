@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h1><strong>{{ $event->event_type }} ({{ $event->standard->jurisdiction }} - {{ $event->standard->standard }})</strong></h1>
    <div>Status: <strong>{{ $event->status }}</strong></div>
    <div>Risk Level: <strong>{{ $event->risk }}</strong></div>
    <div>
        <b>Event Data/Fields</b>
        <ul>
        @foreach(($event->data ?? []) as $k => $v)
           <li>{{ $k }}: {{ $v }}</li>
        @endforeach
        </ul>
    </div>
    <div><b>AI Reason:</b> {!! nl2br(e($event->ai_decision_details)) !!}</div>
    <div style="background:#eef;padding:1em;">
        <b>Notification Letter Draft:</b>
        <pre style="white-space:pre-wrap">{{ $event->notification_letter }}</pre>
    </div>
    <a href="{{ route('gdpr.events.index') }}">← Back</a>
</div></div></div></div></div></div>

<div id="bpmn-canvas" style="height:300px"></div>

@endsection

<script src="https://unpkg.com/bpmn-js@11.0.0/dist/bpmn-viewer.production.min.js"></script>
<script>
const viewer = new BpmnJS({ container: '#bpmn-canvas' });
fetch('/bpmn/{{ $event->standard->jurisdiction }}_{{ strtolower($event->standard->standard) }}.bpmn')
    .then(res => res.text()).then(xml => viewer.importXML(xml));
</script>