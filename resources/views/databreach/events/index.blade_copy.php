@extends('template')

@push('styles')
<style>
    .events-dashboard-section {
        background: #f7fafe;
        border-radius: 15px;
        box-shadow: 0 2px 10px #e3e7ee;
        padding: 2.2rem 2.2rem 1.2rem 2.2rem;
        margin-bottom: 2rem;
    }
    .event-card {
        border-radius: 9px;
        background: #fff;
        box-shadow: 0 1px 7px #dde3ee;
        padding: 1.25rem 1.7rem 1.1rem 1.7rem;
        margin: 0 0 18px 0;
        overflow: hidden;
        transition: box-shadow 0.3s;
        border: 1px solid #e6ebf0;
        position: relative;
    }
    .event-card:hover {
        box-shadow: 0 4px 20px #bdd8fa45;
        border-color: #80bdff;
    }
    .event-card .badge {
        font-size: 0.95em;
        vertical-align: baseline;
        margin-left: 8px;
    }
    .event-title {
        font-size: 1.25em;
        font-weight: bold;
        color: #1466a3;
        vertical-align: middle;
    }
    .event-status-badge.complete    { background: #0a8; color: #fff; }
    .event-status-badge.unscored    { background: #b0b4e3; color: #333; }
    .event-status-badge.inprogress  { background: #ffc107; color: #333; }
    .event-status-badge.high        { background: #e74c3c; color: #fff; }
    .event-status-badge.medium      { background: #f58b1e; color: #fff; }
    .event-status-badge.low         { background: #2dce98; color: #fff; }
    .event-card .fa {
        font-size: 1.13em;
        margin-right: 5px;
    }
    .create-event-btn {
        float: right;
        margin-bottom: 1.1rem;
        margin-top: 1rem;
        border-radius: 34px;
        font-size: 1.11em;
        padding: 9px 22px;
    }
    @media (max-width: 576px) {
        .events-dashboard-section { padding: 1rem; }
        .event-card { padding: 1rem; }
        .create-event-btn { float: none; margin-top: 0.7rem;}
    }
</style>
@endpush

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row gy-4">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 p-3">
                        <div class="events-dashboard-section">

                            <div class="d-flex align-items-center mb-3">
                                <h1 class="mb-0 text-primary flex-grow-1"><i class="fa fa-list-alt"></i>
                                    <strong>Compliance Events Dashboard</strong>
                                </h1>
                                <a href="{{ route('gdpr.events.create') }}"
                                   class="btn btn-success create-event-btn"
                                   title="Create a new compliance event">
                                    <i class="fa fa-plus-circle"></i> New Event
                                </a>
                            </div>

                            {{-- Success/alerts --}}
                            @if(session('success'))
                                <div class="alert alert-success mb-2">{{ session('success') }}</div>
                            @endif

                            {{-- Responsive card grid --}}
                            @if($events->isEmpty())
                                <div class="alert alert-info mt-4">
                                    <i class="fa fa-info-circle"></i> No compliance events logged yet. Click “New Event” to add your first!
                                </div>
                            @else
                                <div class="row row-cols-1 row-cols-lg-2 g-2">
                                    @foreach($events as $event)
                                        <div class="col">
                                            <div class="event-card mb-2">
                                                <div>
                                                    <span class="event-title">
                                                        <i class="fa fa-shield-alt"></i>
                                                        {{ ucfirst($event->event_type) }}
                                                        <span class="badge bg-light text-dark ms-2">{{ $event->standard->jurisdiction }} - {{ $event->standard->standard }}</span>
                                                        <span class="badge event-status-badge {{ strtolower($event->risk ?? 'unscored') }}">
                                                            @if($event->risk)
                                                                Risk: {{ ucfirst($event->risk) }}
                                                            @else
                                                                Unscored
                                                            @endif
                                                        </span>
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center flex-wrap">
                                                    <div class="me-3 mt-1">
                                                        <span class="badge {{ $event->status === 'complete' ? 'event-status-badge complete' : 'event-status-badge inprogress' }}">
                                                            Status: {{ ucfirst($event->status ?? 'Pending') }}
                                                        </span>
                                                    </div>
                                                    <div class="me-3 mt-1 small text-muted">
                                                        <i class="fa fa-calendar"></i>
                                                        {{ $event->created_at->format('Y-m-d H:i') }}
                                                    </div>
                                                    <div class="ms-auto mt-1">
                                                        <a href="{{ route('gdpr.events.show', $event->id) }}"
                                                           class="btn btn-outline-primary btn-sm"
                                                           title="View full event details">
                                                           <i class="fa fa-eye"></i> View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-3">
                                {{-- Laravel pagination --}}
                                {{ $events->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection