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
                         
                                <strong>Compliance Advisor AI Agent - Conduct Preliminary Assessment</strong>
                            </h1>
                      		<div class="alert alert-info mb-4">
                              
 
                              
    <b>About Data Event/ Incident - Data Compliance and Privacy Preliminary Assessor:</b> <br>
    <h4> * When you complete the form below, the Agencti AI Assesor will analyze your selected regulatory standard, jurisdiction, and event details, 
    along with the monitored data sources and incident information you provide. <br><br>
    * The Assessor will:<br>
    <ul class="mb-1">
        <li>+ Evaluate the incident for privacy and compliance risk</li>
        <li>+ Recommend next-step actions required by law or policy (such as reporting, notifications, or further investigation)</li>
      </ul></h4>
    <small class="text-muted">
        Your inputs remain confidential and the agent’s results help ensure you meet your compliance obligations quickly and accurately.
    </small>
</div>
                        <form method="POST" action="{{ route('agentic_ai.compliance.run') }}">
                            @csrf
                            <div class="row g-3">
                                <!-- Standard dropdown -->
                                <div class="col-md-3">
                                    <label for="standard">Standard</label>
                                    <select name="standard" id="standard" class="form-control" required>
                                        <option value="">-- Select Standard --</option>
                                        @foreach($standards ?? [] as $std)
                                            <option value="{{ $std }}"
                                            {{ (old('standard', $input['standard'] ?? '') == $std) ? 'selected' : '' }}>
                                                {{ $std }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <!-- Jurisdiction dropdown -->
                                <div class="col-md-3">
                                    <label for="jurisdiction">Jurisdiction</label>
                                    <select name="jurisdiction" id="jurisdiction" class="form-control" required>
                                        <option value="">-- Select Jurisdiction --</option>
                                        @foreach($jurisdictions ?? [] as $jur)
                                            <option value="{{ $jur }}"
                                            {{ (old('jurisdiction', $input['jurisdiction'] ?? '') == $jur) ? 'selected' : '' }}>
                                                {{ $jur }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <!-- Event Type -->
                                <div class="col-md-3">
                                    <label for="event_type">Event Type(ie Data Spill, Data Breach)</label>
                                    <input type="text" name="event_type" id="event_type" class="form-control"
                                        value="{{ old('event_type', $input['event_type'] ?? '') }}"
                                        placeholder="Describe event type" required />
                                </div>
                                <!-- Data Sources Multi Select -->
                                
                                <!-- Incident text area -->
                                <div class="col-md-12 mt-2">
                                    <label for="incident_info"><b>Provide Information about Incident (or simply copy and paste email content here!)</b></label>
                                    <textarea name="incident_info" id="incident_info" class="form-control" rows="10"
                                        style="min-height:160px" placeholder="Describe the incident in detail" required>{{ old('incident_info', $input['incident_info'] ?? '') }}</textarea>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-lg btn-lg-custom mt-3" {{ !empty($disable_button) ? 'disabled' : '' }} id="assessmentRunBtn">
    <i class="fa fa-bolt"></i> Hand-off to Agentic AI 
</button>
                          


@if(isset($disable_button) && $disable_button)
<script>
    document.addEventListener('DOMContentLoaded', function(){
        let btn = document.getElementById('assessmentRunBtn');
        if(btn) btn.disabled = true;
    });
</script>
@endif

                        <!-- AI Decision Copy Block -->
                        @if(isset($markdown_html))
                            <hr/>
                            <h4>AI-Generated Compliance Decision</h4>
                            <button class="btn btn-secondary mb-2" onclick="copyAIDecision()" id="copyAuditBtn">
                                Copy Report to Clipboard
                            </button>
                            <div class="border p-3" id="complianceAssessmentContainer" style="background: #fafafc">
                                {!! $markdown_html !!}
                            </div>
                            <script>
                                function copyAIDecision() {
                                    const el = document.getElementById('complianceAssessmentContainer');
                                    const text = el.innerText;
                                    navigator.clipboard.writeText(text).then(
                                        () => alert('Content copied to clipboard!'),
                                        err => alert('Copy failed: ' + err)
                                    );
                                }
                            </script>
                        @endif

                        {{-- Dependent Dropdowns Script --}}
                        <script>
                            const standardJurisdictionMap = @json($standardJurisdictionMap);
                            const jurisdictionStandardMap = @json($jurisdictionStandardMap);

                            function setJurisdictionsForStandard(selectedStandard) {
                                let jurisdictionSelect = document.getElementById('jurisdiction');
                                let previous = jurisdictionSelect.value;

                                jurisdictionSelect.innerHTML = '<option value="">-- Select Jurisdiction --</option>';
                                let allowedJuris = standardJurisdictionMap[selectedStandard] || [];

                                allowedJuris.forEach(jur => {
                                    let selected = (jur === previous) ? 'selected' : '';
                                    jurisdictionSelect.innerHTML += `<option value="${jur}" ${selected}>${jur}</option>`;
                                });
                                if(allowedJuris.length === 1 && previous !== allowedJuris[0]){
                                    jurisdictionSelect.value = allowedJuris[0];
                                    jurisdictionSelect.dispatchEvent(new Event('change'));
                                }
                            }

                            function setStandardsForJurisdiction(selectedJurisdiction) {
                                let standardSelect = document.getElementById('standard');
                                let previous = standardSelect.value;

                                standardSelect.innerHTML = '<option value="">-- Select Standard --</option>';
                                let allowedStds = jurisdictionStandardMap[selectedJurisdiction] || [];

                                allowedStds.forEach(std => {
                                    let selected = (std === previous) ? 'selected' : '';
                                    standardSelect.innerHTML += `<option value="${std}" ${selected}>${std}</option>`;
                                });
                                if(allowedStds.length === 1 && previous !== allowedStds[0]){
                                    standardSelect.value = allowedStds[0];
                                    standardSelect.dispatchEvent(new Event('change'));
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function(){
                                document.getElementById('standard').addEventListener('change', function(){
                                    setJurisdictionsForStandard(this.value);
                                });
                                document.getElementById('jurisdiction').addEventListener('change', function(){
                                    setStandardsForJurisdiction(this.value);
                                });

                                // Prefill logic if old input exists
                                @if(old('standard', $input['standard'] ?? ''))
                                    setJurisdictionsForStandard("{{ old('standard', $input['standard'] ?? '') }}");
                                @elseif(old('jurisdiction', $input['jurisdiction'] ?? ''))
                                    setStandardsForJurisdiction("{{ old('jurisdiction', $input['jurisdiction'] ?? '') }}");
                                @endif
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection