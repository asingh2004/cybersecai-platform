@extends('template')
@section('main')

<div class="col-md-10"> <div class="main-panel min-height mt-4"> <div class="row"> <div class="margin-top-85"> <div class="row m-0"> @include('users.sidebar')
                <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                    <div class="d-flex align-items-center mb-3">
                        <h1 class="form-title mb-0">
                            <strong>Compliance Advisor AI Agent - Conduct Preliminary Assessment</strong>
                        </h1>
                    </div>

                    <div class="alert alert-info mb-4 shadow-sm">
                        <div class="fw-bold mb-1">About Data Event/Incident - Data Compliance and Privacy Preliminary Assessor</div>
                        <div class="small text-dark">
                            When you complete the form below, the Agentic AI Assessor will analyze your selected regulatory standard, jurisdiction, and event details,
                            along with the monitored data sources and incident information you provide. It will:
                        </div>
                        <ul class="mt-3 mb-2">
                            <li>[A] Evaluate the incident for privacy and compliance risk</li>
                            <li>[B] Recommend next-step actions required by law or policy (such as reporting, notifications, or further investigation)</li>
                        </ul>
                        <div class="text-muted small mb-1">
                            Your inputs remain confidential and the agent’s results help ensure you meet your compliance obligations quickly and accurately.
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="{{ route('agentic_ai.compliance.run') }}">
                                @csrf
                                <div class="row g-3">
                                    <!-- Standard dropdown -->
                                    <div class="col-12 col-md-4">
                                        <label for="standard" class="form-label">Standard</label>
                                        <select name="standard" id="standard" class="form-select" required>
                                            <option value="">-- Select Standard --</option>
                                            @foreach($standards ?? [] as $std)
                                                <option value="{{ $std }}"
                                                    {{ (old('standard', $input['standard'] ?? '') == $std) ? 'selected' : '' }}>
                                                    {{ $std }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('standard')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Choose the applicable regulatory or security framework.</div>
                                    </div>

                                    <!-- Jurisdiction dropdown -->
                                    <div class="col-12 col-md-4">
                                        <label for="jurisdiction" class="form-label">Jurisdiction</label>
                                        <select name="jurisdiction" id="jurisdiction" class="form-select" required>
                                            <option value="">-- Select Jurisdiction --</option>
                                            @foreach($jurisdictions ?? [] as $jur)
                                                <option value="{{ $jur }}"
                                                    {{ (old('jurisdiction', $input['jurisdiction'] ?? '') == $jur) ? 'selected' : '' }}>
                                                    {{ $jur }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('jurisdiction')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Select the country/state/region governing the assessment.</div>
                                    </div>

                                    <!-- Event Type -->
                                    <div class="col-12 col-md-4">
                                        <label for="event_type" class="form-label">Event Type </label>
                                        <input
                                            type="text"
                                            name="event_type"
                                            id="event_type"
                                            class="form-control"
                                            value="{{ old('event_type', $input['event_type'] ?? '') }}"
                                            placeholder="Describe event type, i.e., Data Spill, Exfiltration"
                                            required
                                        />
                                        @error('event_type')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">A short label to categorize this incident.</div>
                                    </div>

                                    <!-- Data Sources Multi Select -->
                                    {{-- Add your data source multiselect here if/when available --}}

                                    <!-- Incident text area -->
                                    <div class="col-12">
                                        <label for="incident_info" class="form-label"><b>Provide Information about Incident (or simply copy and paste email content here!)</b></label>
                                        <textarea
                                            name="incident_info"
                                            id="incident_info"
                                            class="form-control"
                                            rows="10"
                                            style="min-height: 160px"
                                            placeholder="Describe the incident in detail"
                                            required
                                        >{{ old('incident_info', $input['incident_info'] ?? '') }}</textarea>
                                        @error('incident_info')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Include what happened, systems and data involved, dates/times, and any known impact.</div>
                                    </div>
                                </div>

                                <button
                                    class="btn btn-primary btn-lg btn-lg-custom mt-3"
                                    {{ !empty($disable_button) ? 'disabled' : '' }}
                                    id="assessmentRunBtn"
                                >
                                    <i class="fa fa-bolt"></i> Hand-off to Agentic AI
                                </button>
                            </form>
                        </div>
                    </div>

                    @if(isset($disable_button) && $disable_button)
                    <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            let btn = document.getElementById('assessmentRunBtn');
                            if(btn) btn.disabled = true;
                        });
                    </script>
                    @endif

                    @if(isset($markdown_html))
                        <hr class="my-4"/>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h4 class="mb-0">AI-Generated Compliance Decision</h4>
                            <button class="btn btn-outline-secondary btn-sm" onclick="copyAIDecision()" id="copyAuditBtn">
                                Copy Report to Clipboard
                            </button>
                        </div>
                        <div class="border rounded p-3 bg-light" id="complianceAssessmentContainer" style="background:#fafafc">
                            {!! $markdown_html !!}
                        </div>
                        <script>
                            function copyAIDecision() {
                                const el = document.getElementById('complianceAssessmentContainer');
                                const text = el ? el.innerText : '';
                                navigator.clipboard.writeText(text).then(
                                    () => alert('Content copied to clipboard!'),
                                    err => alert('Copy failed: ' + err)
                                );
                            }
                        </script>
                    @endif

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
                            if (allowedJuris.length === 1 && previous !== allowedJuris[0]) {
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
                            if (allowedStds.length === 1 && previous !== allowedStds[0]) {
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