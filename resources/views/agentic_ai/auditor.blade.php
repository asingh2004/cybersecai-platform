@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                      
                      <h1 class="form-title">
                         
                                <strong>Internal Auditor AI Agent - Produce Board Level Report</strong>
                            </h1>
          
                      
                      	<div class="alert alert-info mb-4">
    <b>About the Internal Auditor AI Agent - For Board Level Reporting:</b><br><br>
    The Cybersecai.io Internal Auditor AI Agent provides an independent, board-ready audit report based on the risk data gathered across your region.<br>
    Once you select your region and generate the report, the Agent will:<br>
    <ul class="mb-1">
        <li>* The report analyzes file risk summaries to reveal and compare key risk trends over the last month versus the previous two-month window.</li>
        <li>* It highlights new, ongoing, and escalating risks, clearly marking urgent items and making actionable recommendations for the Board.</li>
        <li>* The analysis shows how risks are evolving with easy-to-read tables and commentary to help the Board quickly understand what has changed or stayed the same.</li>
      	<li>* It outlines any immediate legal or regulatory obligations triggered by recent risk findings, ensuring the Board is aware of required actions.</li>
      
      <br>
    </ul>
    <small class="text-muted">This expert audit AI agent is designed to support your board's oversight, compliance, and strategic risk management responsibilities.</small>
</div>
                        <form method="POST" action="{{ route('agentic_ai.auditor.run') }}">
                            @csrf
                            <div class="mb-3">
                                <label>Which region are you conducting this audit in?</label>
                                <select name="region" class="form-control">
                                    <option>Australia</option>
                                    <option>USA</option>
                                    <option>Canada</option>
                                    <option>UK</option>
                                    <option>Europe</option>
                                    <option>New Zealand</option>
                                    <option>Singapore</option>
                                </select>
                            </div>
                            <button class="btn btn-primary" {{ !empty($disable_button) ? 'disabled' : '' }} id="auditRunBtn">
        Generate Audit Report
    </button>
</form>
@if(isset($disable_button) && $disable_button)
    <script>
        // Confirm disable on page load (protect against JS reloads)
        document.addEventListener('DOMContentLoaded', function(){
            let btn = document.getElementById('auditRunBtn');
            if(btn) btn.disabled = true;
        });
    </script>
@endif
                        @if(isset($markdown_html))
                        <hr/>
                        <h4>AI-Generated Audit Report ({{ $region }})</h4>
                        <!-- Copy Button -->
                        <button class="btn btn-secondary mb-2" onclick="copyAuditReport()" id="copyAuditBtn">
                            Copy Report to Clipboard
                        </button>
                        <div class="border p-3" id="auditReportContainer" style="background: #fafafc">
                            {!! $markdown_html !!}
                        </div>
                        <script>
                        function copyAuditReport() {
                            // Get the report HTML
                            var el = document.getElementById('auditReportContainer');
                            // Create temporary textarea to copy as plain text for best compatibility
                            var tempTextArea = document.createElement('textarea');
                            // Option 1: Plain text (will flatten formatting)
//                            tempTextArea.value = el.innerText;
                            // Option 2: HTML (preserves some formatting)
                            tempTextArea.value = el.innerText;
                            document.body.appendChild(tempTextArea);
                            tempTextArea.select();
                            try {
                                document.execCommand('copy');
                                document.getElementById('copyAuditBtn').innerText = "Copied!";
                                setTimeout(function() {
                                    document.getElementById('copyAuditBtn').innerText = "Copy Report to Clipboard";
                                }, 1500);
                            } catch (err) {
                                alert('Failed to copy text');
                            }
                            document.body.removeChild(tempTextArea);
                        }
                        </script>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection