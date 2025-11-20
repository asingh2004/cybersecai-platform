@extends('template')

@push('styles')
<style>
    .event-view-card {
        background: #f7fafd;
        border-radius: 16px;
        box-shadow: 0 2px 10px #e3e7ee;
        padding: 2.33rem 2.33rem 1.33rem 2.33rem;
        margin-bottom: 2.2rem;
        border: 1px solid #e6ebf0;
    }
    .event-label {
        font-weight: bold;
        color: #2a547d;
        font-size: 1.1em;
    }
    .event-status-badge.complete    { background: #2dce89; color: #fff; }
    .event-status-badge.inprogress  { background: #ffc107; color: #333; }
    .event-status-badge             { font-size: 1.05em; font-weight: 600; vertical-align: middle;}
    .badge-risk.high { background: #e74c3c; color: #fff; }
    .badge-risk.medium { background: #fd9644; color: #fff; }
    .badge-risk.low { background: #1abc9c; color: #fff; }
    .badge-risk.unscored { background: #bdc3c7; color: #444; }
    .event-field-ul { list-style: none; padding: 0; margin: 0 0 1rem 0;}
    .event-field-ul li { padding: .3em .1em; border-bottom: 1px dotted #e5eaf1;}
    .event-view-section-title { font-size: 1.12em; margin-top: 1.4em; color: #22497d; letter-spacing: 0.01em;}
    .ai-reason-box {
        background: #f2f5ff;
        padding: 1.1rem 1.5rem;
        border-radius: 12px;
        margin: 1.3rem 0 1.6rem 0;
        font-size: 1.03em;
        color: #2d5267;
    }
    .notif-draft-box {
        background: #eef9ef;
        border: 1.5px dashed #a8c1ae;
        border-radius: 10px;
        padding: 1.1rem 1.3rem;
        font-size: 1.04em;
        white-space: pre-wrap;
        margin-bottom: 1.4rem;
    }
    .back-link {
        display:inline-block;
        margin-top:.5em;
        font-size:1.07em;
        color:#116ec2;
        text-decoration:none;
    }
    .back-link:hover { text-decoration: underline;}
    .bpmn-title {
        font-weight: 500;
        margin-bottom: 8px;
        color: #388;
        font-size: 1.08em;
    }
    #bpmn-canvas { border-radius: 7px; box-shadow: 0 4px 16px #ddeefa55; margin-bottom:1.3em; }
  
  
 /* Polished Status & Risk Badges, and Created Date */
.event-status-badge {
    display:inline-block;
    font-size: 1.06em;
    font-weight: 700;
    vertical-align: middle;
    padding: 7px 18px;
    border-radius: 1em;
    box-shadow: 0 2px 8px #e7f9f4aa;
    background: #fff;
    margin-right:6px;
    border: 1.8px solid #bee6d9;
}
.event-status-badge.complete    { border-color: #2dce89; color: #2dce89; background: #f7fefa; }
.event-status-badge.inprogress  { border-color: #ffc107; color: #ffc107; background: #fffbe6; }

.badge-risk {
    display:inline-block;
    background: #fff !important;
    border-radius: 1em;
    font-weight:700;
    font-size: 1.08em;
    padding: 7px 14px;
    margin-right:6px;
    border: 2.1px solid #bdc3c7;
    box-shadow: 0 2px 8px #f6eeeebb;
    color: #444 !important;
}
.badge-risk.high    { border-color: #e74c3c !important; background:#fff5f4 !important; color:#e74c3c !important;}
.badge-risk.medium  { border-color: #fd9644 !important; background:#fff9f3 !important; color:#fd9644 !important;}
.badge-risk.low     { border-color: #1abc9c !important; background:#f5fffd !important; color:#1abc9c !important;}
.badge-risk.unscored{ border-color: #bdc3c7 !important; background:#f6f6f7 !important; color:#949aab !important; }

/* Smaller, lighter Created/Created At field in list */
.event-created, .event-field-created {
    font-size: 0.93em !important;
    color: #8a8a99 !important;
    font-style: italic;
    letter-spacing:.01em;
}
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
                    <div class="event-view-card mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <h1 class="flex-grow-1 mb-0 text-primary">
                                <i class="fa fa-eye"></i>
                                <strong>{{ ucfirst($event->event_type) }}</strong>
                                <small class="ms-2 badge bg-info text-dark" style="font-size: 1em;">
                                    {{ $event->standard->jurisdiction }} - {{ $event->standard->standard }}
                                </small>
                            </h1>
                            @php
                                $stat = strtolower($event->status ?? '');
                                $risk = strtolower($event->risk ?? 'unscored');
                            @endphp
                            <span class="event-status-badge {{ $stat === 'complete' ? 'complete' : 'inprogress' }} ms-3" title="Status">
                                <small class="ms-2 badge bg-info text-dark" style="font-size: 1em;"><i class="fa fa-check-circle"></i> {{ ucfirst($event->status ?? 'In Progress') }}</small>
                            </span>
                            <span class="badge badge-risk {{ $risk }} ms-2" title="Risk Level">
                               <small class="ms-2 badge bg-info text-dark" style="font-size: 1em;"> <i class="fa fa-exclamation-triangle"></i> {{ ucfirst($event->risk ?? 'Unscored') }}</small>
                            </span>
                        </div>

                        <div class="event-view-section-title mt-3"><i class="fa fa-database"></i> Event Data</div>
                        <ul class="event-field-ul mb-1">
                            @forelse(($event->data ?? []) as $k => $v)
                               <li><span class="event-label">{{ ucwords(str_replace('_',' ', $k)) }}:</span>
                                   {{ $v ?: '—' }}</li>
                            @empty
                               <li><em>No event data found.</em></li>
                            @endforelse
                        </ul>

                        <div class="event-view-section-title"><i class="fa fa-robot"></i> AI Reasoning</div>
                        <div class="ai-reason-box">
                            {!! nl2br(e($event->ai_decision_details)) ?: '<em>Not available</em>' !!}
                        </div>

                        <div class="event-view-section-title"><i class="fa fa-envelope"></i> Notification Letter Draft</div>
                        <div class="notif-draft-box">
                            {{ $event->notification_letter ?? 'Not available.' }}
                        </div>

                        <a href="{{ route('databreach.events.index') }}" class="back-link">
                            <i class="fa fa-arrow-left"></i> Back to Events
                        </a>
                    </div>

                    <div class="mb-3">
                        <span class="bpmn-title"><i class="fa fa-project-diagram"></i> Compliance Process Model</span>
                        <div id="bpmn-canvas" style="height:320px;min-width:100%;background:#fbfbff;"></div>
                    </div>

                  </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/bpmn-js@11.0.0/dist/bpmn-viewer.production.min.js"></script>
<script>
const viewer = new BpmnJS({ container: '#bpmn-canvas' });
fetch('/bpmn/{{ $event->standard->jurisdiction }}_{{ strtolower($event->standard->standard) }}.bpmn')
    .then(res => res.text()).then(xml => viewer.importXML(xml));
</script>
@endpush