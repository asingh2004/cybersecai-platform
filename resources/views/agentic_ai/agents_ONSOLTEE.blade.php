@extends('template')

@push('styles')
<style>
    .date-sm { font-size: 0.90em; color: #777; }
    .badge-agent { background: #1B72C0; color: #fff; font-weight:bold; font-size:1.10em; padding:6px 16px; display: flex; align-items: center; }
    .ai-svg-logo { width: 1.3em; height: 1.3em; vertical-align: middle; margin-right: 0.65em; }
    .btn-agent-new {
        font-size: 1.25em;
        font-weight:bold;
        padding: 10px 30px;
        background: linear-gradient(90deg, #3ea2ff 5%, #326fed 95%);
        color: #fff;
        border-radius:28px;
        box-shadow: 0 4px 18px rgba(50,127,237,0.17);
        transition:.2s;
        border: none;
    }
    .btn-agent-new:hover, .btn-agent-new:focus { background: #1B72C0; color: #fff;}
    .btn-agent-view {
        font-size:0.98em;
        font-weight:bold;
        background:linear-gradient(90deg,#3ea2ff,#3295ef);
        color:#fff;border-radius:24px;padding:7px 18px 7px 14px;
        border:none; transition:.16s;
        box-shadow:0 2px 10px rgba(50,127,237,0.07);
        position:relative;
    }
    .btn-agent-view .bi { margin-right:6px; font-size:1.15em; vertical-align:middle;}
    .btn-agent-view[aria-expanded="true"]{ background:#1B72C0;color:#fff;}
    /* Reduce font size for table */
    .table-logs { font-size: 0.93em; }
    .table-logs th, .table-logs td { vertical-align: middle; }
    .collapse-td { padding: 0; border: none; background: #f7f9fb; }
    .collapse-content { padding: 2em 1.2em 1.3em 1.5em; border-left: 4px solid #1B72C0; border-radius: 0 6px 6px 0;}
    .collapse-content .close-collapse { float:right; color:#1B72C0; text-decoration:underline; font-size:1.01em; background:none; border:none; }
    .collapse-content .close-collapse:hover { color:#2b1811; text-decoration:none;}
    .collapse-content h6 {margin-top: 0;}
    /* Fade in for expanded details row */
    .collapse.show .collapse-content { animation: fadeInRow .27s; }
    @keyframes fadeInRow { from{opacity:.27;} to{opacity:1;} }
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
                        <h1><strong>cybersecai.io Expert AI Agents Available to You</strong></h1>
                        <h4>Quickly access, initiate, and track your key Agentic AI workflows for compliance, audit, and policy management.</h4><br>
                      
                      
                      	@foreach($agents as $agent)
                                    <a href="{{ route($agent['route']) }}">
                                       <span class="fw-bold">{{ $agent['label'] }}: </span></a>
   									<h4 class="mb-0 flex-grow-1 text-dark d-inline"> {{ $agent['description'] }}</h4><br><br>
                                    
                      	@endforeach
                      

                        @foreach($agents as $agent)
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body py-4">
                                <div class="d-flex align-items-center mb-2">
                                 
                                  
                                  	<span class="fw-bold">{{ $agent['label'] }}: </span>
                             
                                  
                                    <h4 class="mb-0 flex-grow-1 text-dark d-inline"> {{ $agent['description'] }}</h4>
                                    <a href="{{ route($agent['route']) }}" class="btn btn-agent-new ms-3 float-end">
                                       
                                      <button type="submit" class="btn btn-primary btn-lg mb-3">+ New Request</button>
                                    </a>
                                  
                                  	
                                  
                                </div>
                                @if($agent['user_logs']->count())
                                <div class="table-responsive">
                                    <table class="table table-bordered table-logs mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width:38px;">#</th>
                                                <th style="width:370px;">Request Summary</th>
                                                <th style="width: 110px;">Date Modified</th>
                                                <th style="width:70px;">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($agent['user_logs'] as $idx => $log)
                                            <tr>
                                                <td class="text-center">{{ $idx+1 }}</td>
                                                <td>
                                                    @if($agent['endpoint'] === 'compliance_advisor')

                                                  		<span class="badge badge-agent text-dark me-2">Standard: {{ $log->request_data['standard'] ?? '' }}</span><br>
                                                  		<span class="badge badge-agent text-dark me-2">Jurisdiction: {{ $log->request_data['jurisdiction'] ?? '' }}</span><br>
                                                  		<span class="badge badge-agent text-dark me-2">Event: {{ $log->request_data['event_type'] ?? '' }}</span>
                             
                                                    @elseif($agent['endpoint'] === 'audit')
                    
                                                  		<span class="badge badge-agent text-dark me-2">Region: {{ $log->request_data['region'] ?? '' }}</span>
                                                    @elseif($agent['endpoint'] === 'policy_enforce')
                                                
                                                  	<span class="badge badge-agent text-dark me-2">Policy Enforcement</span>
                                                  		
                                                    @endif
                                                </td>
 
                                             <td> <span class="badge badge-agent text-dark me-2">{{ $log->created_at->format('Y-m-d H:i') }}</span></td>
                                                <td class="align-middle text-center">
                                                    <button
                                                        class="btn btn-agent-view"
                                                        type="button"
                                                        data-toggle="collapse"
                                                        data-target="#logDetails-{{ $agent['endpoint'] }}-{{ $log->id }}"
                                                        aria-expanded="false"
                                                        aria-controls="logDetails-{{ $agent['endpoint'] }}-{{ $log->id }}">
                                              
                                                      
                                                      <span class="fw-bold">View</span>
                                                      
                                                      
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class="collapse" id="logDetails-{{ $agent['endpoint'] }}-{{ $log->id }}">
                                                <td colspan="4" class="collapse-td">
                                                    <div class="collapse-content bg-white">
                                                        <button type="button" class="close-collapse" onclick="closeCollapseRow(this)" title="Hide Details">&uarr; Hide details</button>
                                                        {{-- REQUEST PARAMETERS --}}
                                                        <h6>Request parameters</h6>
                                                        @php
                                                            $req = $log->request_data;
                                                            if (is_string($req)) $req = json_decode($req, true) ?: [];
                                                        @endphp
                                                        <table class="table table-sm table-bordered mb-3">
                                                            <tbody>
                                                                @foreach($req as $k => $v)
                                                                    <tr>
                                                                        <th style="width:160px">{{ ucfirst(str_replace('_', ' ', $k)) }}</th>
                                                                        <td>
                                                                        @if(is_array($v))
                                                                            <pre class="mb-0" style="white-space:pre-wrap;font-size:90%;">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                        @else
                                                                            {{ $v }}
                                                                        @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                        
                                                        {{-- AI RESPONSE --}}
                                                        @php
                                                            $res = $log->response_data;
                                                            if (is_string($res)) $res = json_decode($res, true) ?: [];
                                                            $markdown = $res['markdown'] ?? null;
                                                        @endphp
                                                        <h6 class="mt-3">AI Response</h6>
                                                        @if($markdown)
                                                            <div class="border p-2 mb-2 bg-light" style="white-space:normal;">
                                                                <pre style="background:transparent;border:none;">{{ $markdown }}</pre>
                                                            </div>
                                                            <!-- Reveal raw JSON output -->
                                                            <a data-toggle="collapse" href="#respJSON-{{ $log->id }}" style="font-size:90%">Show raw JSON</a>
                                                            <div class="collapse mt-2" id="respJSON-{{ $log->id }}">
                                                                <pre style="white-space:pre-wrap;background:#f8f9fa;">{{ json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                            </div>
                                                        @else
                                                            <pre style="white-space:pre-wrap;background:#f8f9fa;">{{ json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        @endif

                                                        @if($log->error_message)
                                                        <div class="alert alert-warning mt-2">Error: {{ $log->error_message }}</div>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @else
                                    <span class="text-muted">No previous runs yet for this agent.</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
// Hide expanded rows on "Hide details", and ensure only one row expanded at a time
function closeCollapseRow(btn){
    var td = btn.closest('td.collapse-td');
    var collapseDiv = td.parentNode;
    if (collapseDiv.classList.contains('collapse')) {
        $(collapseDiv).collapse('hide');
    } else {
        $(td.parentNode).collapse('hide');
    }
}
$('.btn-agent-view').on('click', function(e){
    var target = $(this).data('target');
    $('.collapse').not(target).collapse('hide');
});
</script>
@endpush