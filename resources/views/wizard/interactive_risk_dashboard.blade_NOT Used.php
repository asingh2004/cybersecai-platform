@extends('template')

@push('css')
<link rel="stylesheet" href="https://cdn.datatables.net/v/bs4/dt-1.13.8/datatables.min.css" />
<style>
.risk-table th, .risk-table td { vertical-align: top;}
.risk-flex-action {display: flex; gap:18px;}
.action-btn {
    background: #36d399; color:#fff; padding:5px 14px; border-radius:6px; font-weight:600; border:none; display:inline-flex; align-items:center; gap:7px; font-size:.96em;
}
.action-btn.siem { background: #2475df; }
.action-btn.soar { background: #fbcb04; color:#222;}
.action-btn.grc  { background: #222; }
.risk-cell.low { color: #219260; font-weight:bold;}
.risk-cell.medium { color: #f2be2d; font-weight:bold;}
.risk-cell.high { color: #e44d3a; font-weight:bold;}
llm-pre {font-size:0.98em; color:#232E3A; background:#f5fcff; padding:9px 10px; border-radius:9px; display:block;}
</style>
@endpush

@section('main')

<div class="container" style="max-width:1200px; margin: 40px auto;">
    <h2 class="mb-4">Sensitive Data Risk Events Dashboard</h2>
    <table id="riskTable" class="risk-table table table-striped table-bordered table-hover" style="width:100%;">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Type</th>
                <th>Source</th>
                <th>Last Modified</th>
                <th>Size</th>
                <th>LLM Risk/Compliance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($filesData as $file)
            <tr>
                <td>{{ $file['file_name'] ?? $file['key'] ?? 'N/A' }}</td>
                <td>{{ $file['file_type'] ?? 'N/A' }}</td>
                <td>{{ $file['_datasource'] ?? 'Unknown' }}</td>
                <td>{{ $file['last_modified'] ?? '' }}</td>
                <td>
                    @if(isset($file['size_bytes']))
                        {{ number_format($file['size_bytes'] / (1024*1024), 2) }} MB
                    @endif
                </td>
                <td>
                   <!-- <pre class="llm-pre">{!! htmlspecialchars($file['llm_response'] ?? '') !!}</pre>-->
                </td>
                <td class="risk-flex-action">
                    <button class="action-btn siem" onclick="pushToSIEM({{ json_encode($file) }})" title="Push this event to SIEM">
                        <svg width="15" height="15"><circle cx="7" cy="7" r="7" fill="#fff" stroke="#2475df" stroke-width="2"/></svg> SIEM
                    </button>
                    <button class="action-btn soar" onclick="window.open('/soar-playbook?file={{ urlencode($file['file_name']??'') }}', '_blank')" title="Open SOAR Playbook"> 
                        <svg width="15" height="15"><rect x="2" y="5" width="11" height="5" rx="1.5" fill="#fff" stroke="#ffe140" stroke-width="2"/></svg> SOAR
                    </button>
                    <button class="action-btn grc" onclick="submitToGRC({{ json_encode($file) }})" title="Submit to GRC Evidence">
                        <svg width="15" height="15"><rect x="3" y="2" width="9" height="11" fill="#36d399" stroke="#fff" stroke-width="2"/></svg> GRC
                    </button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection

@push('scripts')
<script src="https://cdn.datatables.net/v/bs4/dt-1.13.8/datatables.min.js"></script>
<script>
$(document).ready(function() {
    $('#riskTable').DataTable({
        "pageLength": 20,
        "order": [[3, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [5,6] }
        ]
    });
});

// EXAMPLE: Integrate these with AJAX endpoints as needed
function pushToSIEM(file) {
    if (confirm('Push to SIEM?')) {
        $.ajax({
          url: "/api/integrations/siem", method: "POST",
          data: JSON.stringify(file),
          contentType: 'application/json',
          headers:{'X-CSRF-TOKEN':'{{csrf_token()}}'},
          success:function(data){ alert("Pushed to SIEM!"); },
          error:function(){ alert("Failed to push to SIEM"); }
        });
    }
}
function submitToGRC(file) {
    if (confirm('Submit to GRC?')) {
        $.ajax({
          url: "/api/integrations/grc", method: "POST",
          data: JSON.stringify(file),
          contentType: 'application/json',
          headers:{'X-CSRF-TOKEN':'{{csrf_token()}}'},
          success:function(data){ alert("Submitted to GRC!"); },
          error:function(){ alert("Failed to submit to GRC"); }
        });
    }
}
</script>
@endpush