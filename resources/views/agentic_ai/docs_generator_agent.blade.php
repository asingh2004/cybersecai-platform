@extends('template')

@php
    $savedOrganisation = old('organisation_name')
        ?? session('last_org_name')
        ?? '';
@endphp

@push('styles')
<style>
  :root {
    --accent: #5b21b6;
    --accent-dark: #3b0e92;
    --accent-soft: #ede9fe;
    --success: #16a34a;
    --warning: #f97316;
    --danger: #dc2626;
    --muted: #6b7280;
    --bg-light: #f8fafc;
  }
  .hero-card {
    background: linear-gradient(120deg, #312e81, #5b21b6);
    border-radius: 18px;
    padding: 32px;
    color: #fff;
    box-shadow: 0 20px 55px rgba(49,46,129,.45);
    position: relative;
    overflow: hidden;
  }
  .hero-card:after {
    content: "";
    position: absolute;
    width: 200px; height: 200px;
    background: rgba(255,255,255,.12);
    border-radius: 50%;
    top: -60px; right: -60px;
  }
  .hero-card h1 { font-size: 2rem; margin-bottom: 10px; }
  .hero-pill {
    background: rgba(255,255,255,.2);
    border-radius: 999px;
    padding: 8px 18px;
    font-weight: 600;
    display: inline-flex;
    gap: 8px;
    align-items: center;
  }
  .snapshot-card {
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 20px;
    box-shadow: 0 12px 32px rgba(15,23,42,.08);
    height: 100%;
  }
  .snapshot-card h5 {
    font-size: .9rem;
    text-transform: uppercase;
    color: #5b21b6;
    letter-spacing: .08em;
    margin-bottom: 12px;
  }
  .snapshot-meta {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .snapshot-meta li {
    padding: 10px 0;
    border-bottom: 1px dashed #e5e7eb;
    font-size: .95rem;
  }
  .snapshot-meta li:last-child { border-bottom: none; }
  .snapshot-meta span {
    display: block;
    color: var(--muted);
    text-transform: uppercase;
    font-size: .75rem;
    letter-spacing: .08em;
  }
  .input-card {
    border-radius: 18px;
    padding: 24px;
    border: 1px solid #dbeafe;
    background: linear-gradient(135deg,#f8fafc,#eef2ff);
    box-shadow: 0 18px 45px rgba(15,23,42,.09);
  }
  .btn-gradient {
    background: linear-gradient(135deg, #5b21b6, #7c3aed);
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 12px 32px;
    font-weight: 600;
    box-shadow: 0 18px 35px rgba(91,33,182,.35);
  }
  .btn-gradient:disabled { opacity: .6; }
  .docs-wrapper { margin-top: 32px; }
  .doc-group-card {
    border: 1px solid #e4e4f7;
    border-radius: 16px;
    background: #fff;
    padding: 22px;
    box-shadow: 0 15px 48px rgba(15,23,42,.08);
  }
  .doc-chip {
    border-radius: 999px;
    padding: 4px 12px;
    font-size: .8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .doc-chip.mandatory { background: #fee2e2; color: #b91c1c; }
  .doc-chip.best-practice { background: #e0e7ff; color: #312e81; }
  .doc-row { border-bottom: 1px solid #f1f5f9; padding: 14px 0; }
  .doc-row:last-child { border-bottom: none; }
  .doc-actions a,
  .doc-actions button {
    border-radius: 999px !important;
  }
  .empty-state {
    background: #f8fafc;
    border: 1px dashed #cbd5f5;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
  }
</style>
@endpush

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row margin-top-85">
      <div class="row m-0">
        @include('users.sidebar')

        <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
          <div class="hero-card mb-4">
            <h1>Governance Document Generator</h1>
            <p class="mb-3">
              Produce a complete, regulator-ready suite of data breach governance artefacts
              tuned to your business profile.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <div class="hero-pill">
                <i class="fa fa-id-card"></i>
                Business ID: <strong>{{ $businessId ?? 'N/A' }}</strong>
              </div>
              <div class="hero-pill">
                <i class="fa fa-briefcase"></i>
                Industry: <strong>{{ $profile->industry ?? 'Not set' }}</strong>
              </div>
              <div class="hero-pill">
                <i class="fa fa-globe"></i>
                Country: <strong>{{ $profile->country ?? 'Not set' }}</strong>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
          @endif
          @if(session('error') || ($error_message ?? false))
            <div class="alert alert-danger shadow-sm">{{ session('error') ?? $error_message }}</div>
          @endif
          @if($errors->any())
            <div class="alert alert-danger shadow-sm">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="row g-3 mb-4">
            <div class="col-md-5">
              <div class="snapshot-card h-100">
                <h5>Business snapshot</h5>
                <ul class="snapshot-meta">
                  <li>
                    <span>Industry</span>
                    {{ $profile->industry ?? 'Not provided' }}
                  </li>
                  <li>
                    <span>Country / Jurisdiction</span>
                    {{ $profile->country ?? 'Not provided' }}
                  </li>
                  <li>
                    <span>About the business</span>
                    {{ $profile->about_company ?? 'Add a short description in Essential Setup to improve accuracy.' }}
                  </li>
                </ul>
                <a href="{{ route('wizard.essentialSetup') }}" class="btn btn-link px-0 mt-2">
                  Update profile &rarr;
                </a>
              </div>
            </div>

            <div class="col-md-7">
              <div class="input-card">
                <div class="mb-3">
                  <h4 class="mb-1">Generate governance pack</h4>
                  <div class="text-muted small">
                    Only the business name is editable. Requested policies will use the industry/country data already on file.
                  </div>
                </div>
                <form method="POST" action="{{ route('agenticai.docs_agent.generate') }}" id="docsGeneratorForm">
                  @csrf
                  <div class="mb-3">
                    <label for="organisation_name" class="form-label fw-semibold">
                      Business / trading name to show on documents
                    </label>
                    <input type="text" name="organisation_name" id="organisation_name"
                      class="form-control form-control-lg"
                      value="{{ $savedOrganisation }}"
                      placeholder="e.g., CyberSecAI Pty Ltd" required>
                  </div>
                  <button class="btn-gradient" id="generateBtn" {{ empty($profile) ? 'disabled' : '' }}>
                    <i class="fa fa-sparkles me-2"></i>Build Document Suite
                  </button>
                  @if(empty($profile))
                    <div class="text-danger small mt-2">
                      Business profile incomplete. Update Essential Setup to enable the generator.
                    </div>
                  @endif
                </form>
              </div>
            </div>
          </div>

          @if(($groupedDocs ?? []) || ($results ?? []))
            <div class="docs-wrapper">
              <div class="d-flex align-items-center mb-3">
                <h3 class="mb-0">Generated documents</h3>
                <span class="ms-large badge bg-light text-dark">
                  {{ count($results ?? []) }} files
                </span>
              </div>

              @foreach(($groupedDocs ?? []) as $orgName => $groups)
                <div class="mb-4">
                  <h5 class="text-uppercase text-muted mb-2">{{ $orgName }}</h5>
                  <div class="row g-3">
                    @foreach($groups as $groupLabel => $docs)
                      <div class="col-12">
                        <div class="doc-group-card">
                          <div class="d-flex align-items-center mb-3">
                            <h4 class="mb-0">{{ $groupLabel }}</h4>
                            <span class="badge bg-indigo-100 text-indigo-800 ms-3">{{ count($docs) }} docs</span>
                          </div>
                          @foreach($docs as $doc)
                            <div class="doc-row">
                              <div class="d-flex flex-column flex-md-row align-items-md-center">
                                <div class="flex-grow-1">
                                  <div class="fw-bold">{{ $doc['file_display_name'] ?? '-' }}</div>
                                  <div class="text-muted small">
                                    {{ $doc['DocumentType'] ?? '' }}
                                  </div>
                                </div>
                                <div class="me-3">
                                  @if(!empty($doc['is_mandatory']))
                                    <span class="doc-chip mandatory">Mandatory</span>
                                  @else
                                    <span class="doc-chip best-practice">Best Practice</span>
                                  @endif
                                </div>
                                <div class="doc-actions d-flex gap-2">
                                  @if(!empty($doc['docx_download_url']))
                                    <a href="{{ $doc['docx_download_url'] }}" class="btn btn-sm btn-outline-primary">
                                      <i class="fa fa-file-word"></i> Word
                                    </a>
                                  @endif
                                  @if(!empty($doc['json_download_url']))
                                    <a href="{{ $doc['json_download_url'] }}" class="btn btn-sm btn-outline-secondary">
                                      <i class="fa fa-code"></i> JSON
                                    </a>
                                  @endif
                                  <form method="POST" action="{{ route('agenticai.docs_agent.delete') }}">
                                    @csrf
                                    <input type="hidden" name="json_path" value="{{ $doc['json_download_url'] }}">
                                    <input type="hidden" name="docx_path" value="{{ $doc['docx_download_url'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                      onclick="return confirm('Delete this generated document?');">
                                      <i class="fa fa-trash"></i>
                                    </button>
                                  </form>
                                </div>
                              </div>
                            </div>
                          @endforeach
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="empty-state">
              <h4>No governance kit yet</h4>
              <p class="text-muted mb-0">
                Launch your first generation using the form above. Documents are tailored to
                your industry, country and business profile.
              </p>
            </div>
          @endif

        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('docsGeneratorForm');
  const btn = document.getElementById('generateBtn');
  if(form && btn){
    form.addEventListener('submit', () => {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    });
  }
});
</script>
@endpush