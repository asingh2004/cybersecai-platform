@extends('template')

@push('styles')
<style>
    .date-sm { font-size: 0.85em; color: #555; }
    .btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
    .event-type-badge { font-size: .93em; }
    .badge-risk { font-size: .92em; }
    .badge-risk.high { background: #e74c3c; color: #fff; }
    .badge-risk.medium { background: #f39c12; color: #222; }
    .badge-risk.low { background: #27ae60; color: #fff; }
    .badge-risk.unscored { background: #c9d2de; color: #555; }
    .badge-status.complete { background: #0a8; color: #fff; }
    .badge-status.inprogress { background: #ffc107; color: #222; }
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

                        <h1 class="mb-2"><strong>Data Breach Events Register</strong></h1>

                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <a href="{{ route('databreach.events.create') }}" class="btn btn-success btn-lg mb-3">
                            + Create New Event
                        </a>

                        @if($events->isEmpty())
                            <p>You have not logged any compliance events yet.</p>
                        @else
                        <table class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th style="width:3%;">#</th>
                                    <th>Jurisdiction</th>
                                    <th>Standard</th>
                                    <th>Event Type</th>
                                    <th>AI Assessed Risk Level</th>
                                    <th>Status</th>
                                    <th class="text-center">Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($events as $i => $event)
                                <tr>
                                    <td class="text-center">{{ $events->firstItem() + $i }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $event->standard->jurisdiction }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $event->standard->standard }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary event-type-badge text-white">
                                            {{ ucfirst($event->event_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $risk = strtolower($event->risk ?? 'unscored');
                                        @endphp
                                        <span class="badge bg-secondary {{ $risk }}">
                                            {{ ucfirst($event->risk ?? 'Unscored') }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $stat = strtolower($event->status ?? '');
                                        @endphp
                                        <span class="badge bg-secondary {{ $stat === 'complete' ? 'complete' : 'inprogress' }}">
                                            {{ ucfirst($event->status ?? 'In Progress') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-sm">
                                            {{ $event->created_at ? $event->created_at->format('Y-m-d H:i') : '--' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('databreach.events.show', $event->id) }}" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $events->links() }}
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection