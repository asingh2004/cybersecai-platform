@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h1><strong>Compliance Events Dashboard</strong></h1>
    <a href="{{ route('gdpr.events.create') }}">Create New Event</a>
    @foreach($events as $event)
       <div>
          <b>{{ $event->event_type }}</b>
          ({{ $event->standard->jurisdiction }} - {{ $event->standard->standard }})
          | Risk: {{ $event->risk ?? 'Unscored' }}
          <a href="{{ route('gdpr.events.show', $event->id) }}">View</a>
          <div>Status: {{ $event->status }}</div>
       </div>
    @endforeach
    {{ $events->links() }}
</div></div></div></div></div></div>
@endsection