@extends('template')

@php
    $result = session('breach_result') ?? ($result ?? null);
    $breachInput = session('breach_input') ?? ($breachInput ?? null);

    $selectedRegulationsRaw = $selected_regulations ?? $selectedRegulations ?? [];
    if (is_string($selectedRegulationsRaw)) {
        $decoded = json_decode($selectedRegulationsRaw, true);
        $selectedRegulations = is_array($decoded) ? $decoded : [];
    } elseif (is_array($selectedRegulationsRaw)) {
        $selectedRegulations = $selectedRegulationsRaw;
    } else {
        $selectedRegulations = [];
    }
@endphp

@push('styles')
<style>
  :root {
    --accent: #5b21b6;
    --accent-dark: #3b0e92;
    --accent-soft: #ede9fe;
    --success: #16a34a;
    --warning: #ea580c;
    --danger: #dc2626;
    --muted: #6b7280;
  }
  .hero-card {
    background: linear-gradient(135deg, #312e81, #5b21b6);
    border-radius: 18px;
    padding: 28px;
    color: #fff;
    box-shadow: 0 15px 45px rgba(49,46,129,.35);
    position: relative;
    overflow: hidden;
  }
  .hero-card:after {
    content: "";
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    top: -30px; right: -30px;
  }
  .hero-card h1 { font-size: 1.9rem; margin: 0 0 10px; }
  .hero-card p { max-width: 600px; font-size: 1rem; }
  .reg-panel {
    border-radius: 14px;
    border: 1px solid #e0e7ff;
    background: #f8fafc;
    padding: 18px;
  }
  .reg-panel h6 { font-size: .9rem; font-weight: 700; text-transform: uppercase; color: #4c1d95; letter-spacing: .05em; }
  .reg-chip {
    background: #ede9fe;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: .85rem;
    color: #4c1d95;
    margin: 4px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #d9c7ff;
  }
  .reg-chip span {
    font-size: .75rem;
    background: #fff;
    padding: 1px 7px;
    border-radius: 999px;
    color: #4c1d95;
  }
  .input-card {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 12px 40px rgba(15,23,42,.07);
  }
  .dropzone {
    border: 2px dashed #cbd5f5;
    border-radius: 14px;
    padding: 28px;
    text-align: center;
    transition: border-color .2s, background .2s;
    background: #f9fafb;
  }
  .dropzone.dragover {
    border-color: var(--accent);
    background: rgba(91,33,182,.05);
  }
  .dropzone input { display: none; }
  .dropzone .icon {
    width: 52px; height: 52px;
    background: var(--accent-soft);
    color: var(--accent);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 1.6rem;
  }
  .btn-primary-gradient {
    background: linear-gradient(135deg, #5b21b6, #7c3aed);
    border: none;
    color: #fff;
    border-radius: 999px;
    padding: 12px 34px;
    font-size: 1rem;
    box-shadow: 0 10px 30px rgba(91,33,182,.35);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .btn-primary-gradient:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 35px rgba(91,33,182,.4);
    color: #fff;
  }
  .result-section {
    margin-top: 32px;
  }
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap: 16px;
  }
  .summary-card {
    border-radius: 14px;
    padding: 18px;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 8px 25px rgba(15,23,42,.06);
  }
  .summary-card h5 {
    font-size: .95rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
  }
  .summary-card .value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
  }
  .steps-wrapper {
    margin-top: 26px;
  }
  .step-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap: 14px;
  }
  .step-tile {
    border-radius: 14px;
    padding: 18px;
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
    position: relative;
    min-height: 150px;
  }
  .step-tile.active {
    border-color: var(--accent);
    box-shadow: 0 12px 35px rgba(91,33,182,.18);
  }
  .step-tile h6 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
  }
  .step-tile .tagline {
    color: var(--muted);
    font-size: .85rem;
    margin-top: 6px;
  }
  .step-tile .status-dot {
    width: 10px; height: 10px;
    border-radius: 999px;
    position: absolute;
    top: 16px; right: 18px;
  }
  .step-tile.required .status-dot { background: var(--success); }
  .step-tile.optional .status-dot { background: var(--warning); }
  .step-tile.not_applicable { opacity: .5; }
  .step-tile.not_applicable .status-dot { background: #94a3b8; }
  .step-details {
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 24px;
    box-shadow: 0 15px 40px rgba(15,23,42,.08);
    margin-top: 18px;
  }
  .notification-draft {
    border-radius: 12px;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    padding: 16px;
    font-family: "IBM Plex Sans", sans-serif;
    white-space: pre-wrap;
  }
  .determination-card {
    margin-top: 28px;
    border-radius: 18px;
    padding: 24px;
    background: linear-gradient(135deg, #111827, #312e81);
    color: #fff;
    position: relative;
    overflow: hidden;
  }
  .determination-card:after {
    content: "";
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
    top: -45px; right: -45px;
  }
  .determination-card h4 { margin: 0 0 12px; }
  .determination-pill {
    background: rgba(255,255,255,.2);
    border-radius: 999px;
    padding: 6px 16px;
    font-size: .9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .copy-btn {
    border-radius: 999px;
    border: 1px solid #c4b5fd;
    color: #5b21b6;
    padding: 8px 18px;
    background: #fff;
    font-weight: 600;
  }
  .copy-btn:hover { color: #3b0e92; border-color: #a78bfa; }
  .list-tight li { margin-bottom: 6px; }
  .file-meta {
    font-size: .9rem;
    color: #475569;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 10px 14px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
  }
  .chip {
    display: inline-flex;
    align-items: center;
    background: #eef2ff;
    color: #3730a3;
    font-size: .85rem;
    padding: 4px 10px;
    border-radius: 999px;
    margin: 3px;
    border: 1px solid rgba(55,48,163,.1);
  }
  @media (max-width: 767px) {
    .hero-card { padding: 22px; }
    .input-card { padding: 18px; }
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

            <div class="hero-card mb-4">
              <h1>Incident Response Copilot</h1>
              <p>Rapidly assess suspected privacy or security incidents. Paste narrative or upload evidence (PDF / DOCX / email), and let the Agentic AI map the right breach obligations based on your company profile.</p>
              <div class="mt-3 d-flex flex-wrap gap-3">
                <div class="determination-pill" style="background: rgba(255,255,255,.18);">
                  <span>Business ID:</span> <strong>{{ $businessId ?? 'N/A' }}</strong>
                </div>
                <div class="determination-pill" style="background: rgba(255,255,255,.18);">
                  Regulations loaded: <strong>{{ is_countable($selectedRegulations) ? count($selectedRegulations) : 0 }}</strong>
                </div>
              </div>
            </div>

            @if (session('success'))
              <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
              <div class="alert alert-danger shadow-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
              <div class="alert alert-danger shadow-sm">
                <ul class="mb-0">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <div class="row g-3">
              <div class="col-12">
                <div class="input-card">
                  <div class="d-flex align-items-center mb-4">
                    <h4 class="mb-0">1. Describe what happened</h4>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="clearFormBtn">Clear</button>
                  </div>
                  <form method="POST" action="{{ route('agentic_ai.compliance.breach.run') }}" enctype="multipart/form-data" id="breachForm">
                    @csrf
                    <div class="mb-3">
                      <label for="event_title" class="form-label fw-semibold">Incident title</label>
                      <input type="text" name="event_title" id="event_title" class="form-control form-control-lg"
                        placeholder="e.g., Database export emailed externally"
                        value="{{ old('event_title', $breachInput['event_title'] ?? '') }}" required>
                    </div>
                    <div class="mb-4">
                      <label for="incident_text" class="form-label fw-semibold">Paste narrative / notes</label>
                      <textarea name="incident_text" id="incident_text" rows="10" class="form-control"
                        placeholder="Copy/paste email threads, ticket notes, or your own early incident log...">{{ old('incident_text', $breachInput['incident_text'] ?? '') }}</textarea>
                      <small class="text-muted">Include what was accessed, timelines, impacted systems, and any containment already done.</small>
                    </div>

                    <div class="mb-4">
                      <label class="form-label fw-semibold">Or attach supporting file (PDF / DOCX / email)</label>
                      <div class="dropzone" id="dropzone">
                        <input type="file" name="evidence_file" id="fileInput" accept=".pdf,.docx,.txt,.eml,.msg,.rtf,.html">
                        <div class="icon"><i class="fa fa-cloud-upload"></i></div>
                        <div><strong>Drag & drop</strong> or click to upload. Max 6 MB.</div>
                        <div class="text-muted mt-1" id="fileMetaText">We convert PDFs, DOCX, and emails to text securely.</div>
                      </div>
                      @if(isset($breachInput['file_meta']))
                        <div class="file-meta">
                          <i class="fa fa-paperclip text-muted"></i>
                          <div>{{ $breachInput['file_meta']['original_name'] ?? '' }} ({{ $breachInput['file_meta']['size_kb'] ?? '' }} KB)</div>
                        </div>
                      @endif
                    </div>

                    <div class="d-flex flex-wrap gap-3">
                      <button class="btn-primary-gradient" id="submitBtn">
                        <i class="fa fa-robot me-2"></i>Launch Expert Advisor
                      </button>
                      <div class="text-muted fst-italic align-self-center">Avg runtime: 15–25 seconds</div>
                    </div>
                  </form>
                </div>
              </div>

              <div class="col-12">
                <div class="reg-panel">
                  <div class="d-flex align-items-center mb-2">
                    <h6 class="mb-0">Regulations tied to your Business Profile</h6>
                    <a href="{{ route('wizard.essentialSetup') }}" class="ms-auto small text-indigo-600">Update profile</a>
                  </div>
                  @if(empty($selectedRegulations))
                    <div class="alert alert-warning mb-0">No regulations saved. Complete Essential Setup to enable the advisor.</div>
                  @else
                    <div class="d-flex flex-wrap">
                      @foreach($selectedRegulations as $reg)
                        <span class="reg-chip">
                          {{ $reg['standard'] ?? 'Unknown standard' }}
                          <span>{{ $reg['jurisdiction'] ?? 'N/A' }}</span>
                        </span>
                      @endforeach
                    </div>
                  @endif
                </div>
              </div>
            </div>

            @if($result)
              <div class="result-section" id="resultAnchor">
                <div class="d-flex align-items-center mb-3">
                  <h3 class="mb-0">AI Incident Assessment</h3>
                  <button class="copy-btn ms-auto" id="copyReportBtn"><i class="fa fa-copy me-1"></i> Copy Summary</button>
                </div>

                <div class="summary-grid mb-3">
                  <div class="summary-card">
                    <h5>Risk rating</h5>
                    <div class="value text-uppercase">{{ $result['assessment']['risk_rating'] ?? '—' }}</div>
                    <div class="text-muted small">{{ $result['assessment']['confidence'] ?? '' }} confidence</div>
                  </div>
                  <div class="summary-card">
                    <h5>Impacted data</h5>
                    <div class="value" style="font-size:1.1rem">{{ implode(', ', $result['assessment']['exposed_data'] ?? []) ?: 'Not specified' }}</div>
                  </div>
                  <div class="summary-card">
                    <h5>Key Findings</h5>
                    <ul class="list-tight">
                      @foreach(($result['assessment']['key_findings'] ?? []) as $finding)
                        <li>{{ $finding }}</li>
                      @endforeach
                    </ul>
                  </div>
                  <div class="summary-card">
                    <h5>Timeline</h5>
                    <div class="value" style="font-size:1.05rem">{{ $result['assessment']['timeline'] ?? 'Unknown' }}</div>
                  </div>
                </div>

                <div class="steps-wrapper">
                  <h4 class="mb-2">Data Breach Process Map</h4>
                  <p class="text-muted mb-3">Click on a step to view detailed guidance and any pre-drafted notifications.</p>
                  <div class="step-tiles" id="stepTiles">
                    @foreach(($result['process_steps'] ?? []) as $idx => $step)
                      <div class="step-tile {{ $step['status'] ?? '' }} {{ $idx === 0 ? 'active' : '' }}" data-target="step-detail-{{ $idx }}">
                        <div class="status-dot"></div>
                        <span class="badge bg-light text-dark small mb-2">Step {{ $step['step_number'] ?? ($idx + 1) }}</span>
                        <h6>{{ $step['title'] ?? 'Step' }}</h6>
                        <div class="tagline">{{ $step['tagline'] ?? '' }}</div>
                        <div class="mt-2 small fw-semibold">
                          {{ strtoupper(str_replace('_',' ', $step['status'] ?? 'required')) }}
                        </div>
                      </div>
                    @endforeach
                  </div>

                  @foreach(($result['process_steps'] ?? []) as $idx => $step)
                    <div class="step-details {{ $idx === 0 ? '' : 'd-none' }}" id="step-detail-{{ $idx }}">
                      <div class="d-flex align-items-center mb-2">
                        <h5 class="mb-0">{{ $step['title'] ?? 'Step detail' }}</h5>
                        @if(!empty($step['reference_regulation']))
                          <span class="chip ms-auto">{{ $step['reference_regulation'] }} {{ $step['reference_clause'] ?? '' }}</span>
                        @endif
                      </div>
                      <p class="mb-2">{{ $step['details'] ?? '' }}</p>
                      @if(!empty($step['communication_focus']))
                        <div class="small text-muted mb-2"><strong>Focus:</strong> {{ $step['communication_focus'] }}</div>
                      @endif
                      @if(!empty($step['notification_template']))
                        <div class="notification-draft mt-3">
                          <strong class="d-block mb-1">Polished notification/email draft:</strong>
                          {!! nl2br(e($step['notification_template'])) !!}
                        </div>
                      @endif
                      @if(($step['status'] ?? '') === 'not_applicable' && !empty($step['not_applicable_reason']))
                        <div class="alert alert-secondary small mb-0 mt-3">
                          <strong>Why grey?</strong> {{ $step['not_applicable_reason'] }}
                        </div>
                      @endif
                    </div>
                  @endforeach
                </div>

                <div class="determination-card mt-4">
                  <div class="d-flex align-items-center mb-2">
                    <h4 class="mb-0">Final determination</h4>
                    <span class="determination-pill ms-auto">
                      @if(($result['determination']['is_notifiable'] ?? false) === true)
                        <i class="fa fa-check-circle text-success"></i> Notifiable event
                      @else
                        <i class="fa fa-minus-circle text-warning"></i> Not notifiable (monitor)
                      @endif
                    </span>
                  </div>
                  <p>{{ $result['determination']['determination_summary'] ?? '' }}</p>
                  @if(!empty($result['determination']['evidence']))
                    <ul class="list-tight">
                      @foreach($result['determination']['evidence'] as $evidence)
                        <li>{{ $evidence }}</li>
                      @endforeach
                    </ul>
                  @endif
                </div>

                @if(!empty($result['determination']['authority_notifications']) || !empty($result['determination']['subject_notifications']))
                  <div class="row mt-3">
                    <div class="col-md-6">
                      <div class="summary-card h-100">
                        <h5>Authority notifications</h5>
                        <ul class="list-tight">
                          @forelse(($result['determination']['authority_notifications'] ?? []) as $notice)
                            <li>
                              <strong>{{ $notice['authority'] ?? '' }}</strong> — {{ $notice['deadline'] ?? '' }}
                              <br><small class="text-muted">{{ $notice['rationale'] ?? '' }}</small>
                            </li>
                          @empty
                            <li>No authority notifications required.</li>
                          @endforelse
                        </ul>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="summary-card h-100">
                        <h5>Data subject communications</h5>
                        <ul class="list-tight">
                          @forelse(($result['determination']['subject_notifications'] ?? []) as $notice)
                            <li>
                              <strong>{{ $notice['audience'] ?? 'Data Subjects' }}</strong> — {{ $notice['deadline'] ?? '' }}
                              <br><small class="text-muted">{{ $notice['message'] ?? '' }}</small>
                            </li>
                          @empty
                            <li>No subject notifications required.</li>
                          @endforelse
                        </ul>
                      </div>
                    </div>
                  </div>
                @endif

                @if(!empty($result['cyber_security_summary']))
                  <div class="summary-card mt-3">
                    <h5>Cybersecurity Analyst Synopsis</h5>
                    <p class="mb-2">{{ $result['cyber_security_summary']['headline'] ?? '' }}</p>
                    <ul class="list-tight mb-1">
                      @if(!empty($result['cyber_security_summary']['attack_vector']))
                        <li><strong>Attack vector:</strong> {{ $result['cyber_security_summary']['attack_vector'] }}</li>
                      @endif
                      @if(!empty($result['cyber_security_summary']['threat_actor_assessment']))
                        <li><strong>Threat actor:</strong> {{ $result['cyber_security_summary']['threat_actor_assessment'] }}</li>
                      @endif
                      @if(!empty($result['cyber_security_summary']['residual_risk']))
                        <li><strong>Residual risk:</strong> {{ $result['cyber_security_summary']['residual_risk'] }}</li>
                      @endif
                    </ul>
                    @if(!empty($result['cyber_security_summary']['containment_priority']))
                      <div class="small text-muted">Critical containment tasks:</div>
                      <ul class="list-tight">
                        @foreach($result['cyber_security_summary']['containment_priority'] as $task)
                          <li>{{ $task }}</li>
                        @endforeach
                      </ul>
                    @endif
                  </div>
                @endif

                @if(!empty($result['citations']))
                  <div class="summary-card mt-3">
                    <h5>Regulatory citations referenced</h5>
                    <ul class="list-tight mb-0">
                      @foreach($result['citations'] as $cite)
                        <li>
                          <strong>{{ $cite['regulation'] ?? '' }}</strong> — {{ implode(', ', $cite['clauses'] ?? []) }}
                          <br><small class="text-muted">{{ $cite['reason'] ?? '' }}</small>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                @endif

              </div>
            @endif

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const fileMetaText = document.getElementById('fileMetaText');
  const clearBtn = document.getElementById('clearFormBtn');
  const form = document.getElementById('breachForm');
  const submitBtn = document.getElementById('submitBtn');

  if(dropzone && fileInput){
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
      if(e.dataTransfer.files.length){
        fileInput.files = e.dataTransfer.files;
        updateFileMeta();
      }
    });
    fileInput.addEventListener('change', updateFileMeta);
  }

  function updateFileMeta(){
    if(fileInput.files.length){
      const file = fileInput.files[0];
      fileMetaText.innerHTML = `<strong>Attached:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
    } else {
      fileMetaText.innerHTML = 'We convert PDFs, DOCX, and emails to text securely.';
    }
  }

  if(clearBtn && form){
    clearBtn.addEventListener('click', function(){
      if(confirm('Clear current text and file?')){
        form.reset();
        updateFileMeta();
      }
    });
  }

  if(form && submitBtn){
    form.addEventListener('submit', function(){
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Running analysis...';
    });
  }

  const tiles = document.querySelectorAll('.step-tile');
  tiles.forEach(tile => {
    tile.addEventListener('click', function(){
      tiles.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      const target = this.dataset.target;
      document.querySelectorAll('.step-details').forEach(panel => panel.classList.add('d-none'));
      if(target){
        document.getElementById(target)?.classList.remove('d-none');
      }
    });
  });

  const copyBtn = document.getElementById('copyReportBtn');
  copyBtn && copyBtn.addEventListener('click', function(){
    const textSource = document.getElementById('resultAnchor');
    const text = textSource ? textSource.innerText : '';
    navigator.clipboard.writeText(text).then(() => {
      copyBtn.innerHTML = '<i class="fa fa-check text-success me-1"></i> Copied';
      setTimeout(() => copyBtn.innerHTML = '<i class="fa fa-copy me-1"></i> Copy Summary', 1500);
    });
  });

  @if($result)
    document.getElementById('resultAnchor')?.scrollIntoView({ behavior: 'smooth' });
  @endif
});
</script>
@endpush