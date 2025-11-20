@extends('template')

@push('css')
<style>
.persona-hero { background: linear-gradient(114deg, #326f84 62%, #f5fdc1 120%); color:#fff; text-align:center;
    padding:48px 0 26px; border-radius:0 0 18px 18px;}
.persona-hero h2{font-weight:900;font-size:2.1em;margin:12px 0 9px;}
.persona-hero .persona-svg { width:70px;height:70px;border-radius:50%;background:#fff;margin-bottom:7px;}
.risk-cards-wrap{ display:flex; flex-wrap:wrap; justify-content:space-between; gap:38px; max-width:1050px; margin:53px auto 20px;}
.risk-card{ flex:1 0 220px; background:#fff; border-radius:14px; box-shadow:0 2px 16px #58e19a33; padding:34px 24px 27px 24px; text-align: left; min-width:192px; max-width:265px; cursor:pointer;
    border-left:8px solid #e8ba3a; position:relative; transition:box-shadow .14s, border-color .16s;}
.risk-card:hover{ box-shadow:0 7px 27px #e8ba3a48; border-color:#36d399;}
.risk-card .rc-icon{ width:2em;height:2em;margin-bottom:10px; }
.risk-card h4{ font-size:1.13em;margin:0 0 7px 0;color:#e8ba3a;font-weight:700;}
.risk-card p{ color:#2d354d;font-size:1.07em;min-height:49px;}
.risk-card .step-arrow{position:absolute;bottom:18px;right:15px;font-size:1.25em; color:#1877c2;}
@media (max-width:900px){ .risk-cards-wrap{gap:19px} .risk-card{max-width:90vw;}}
@media (max-width:650px){ .risk-cards-wrap{flex-direction:column;gap:14px;}}
</style>
@endpush

@section('main')
<div class="persona-hero">
    <svg class="persona-svg" viewBox="0 0 70 70">
        <!-- Risk shield/eye SVG -->
        <ellipse cx="35" cy="35" rx="32" ry="32" fill="#fff" stroke="#e8ba3a" stroke-width="3"/>
        <ellipse cx="35" cy="36" rx="24.5" ry="21.5" fill="#daf7ff" stroke="#36d399" stroke-width="1.5"/>
        <ellipse cx="35" cy="36" rx="9" ry="9.7" fill="#e8ba3a"/>
        <ellipse cx="35" cy="36" rx="5" ry="5.5" fill="#fff"/>
        <ellipse cx="39" cy="38" rx="1.3" ry="1.3" fill="#b4e6fb"/>
    </svg>
    <h2>Internal Risk Management Persona</h2>
    <div style="font-size:1.14em;max-width:520px;margin:0 auto;">
        Centralized, AI-assisted platform to anticipate emerging risk, monitor exposure, enable quick steps for mitigation, and partner with compliance and infosec teams.
    </div>
</div>
<div class="risk-cards-wrap">
    <a href="{{ route('wizard.dashboard') }}" class="risk-card">
        <span class="rc-icon">
            <svg viewBox="0 0 39 39"><circle cx="19.5" cy="19.5" r="16.7" fill="#fff" stroke="#36d399" stroke-width="2"/><rect x="9" y="18" width="21" height="6" rx="3.4" fill="#e8ba3a"/><ellipse cx="19.5" cy="13" rx="7.5" ry="6.5" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><ellipse cx="19.5" cy="13" rx="3.5" ry="3.5" fill="#e8ba3a"/></svg>
        </span>
        <h4>1. Asset & Data Inventory</h4>
        <p>Unified asset ledger rated by risk—across clouds, endpoints, and SaaS.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="risk-card">
        <span class="rc-icon">
            <svg viewBox="0 0 36 36"><ellipse cx="18" cy="18" rx="15" ry="15" fill="#fafbe3" stroke="#e8ba3a" stroke-width="2"/><rect x="12" y="14" width="12" height="6" rx="2.2" fill="#e8ba3a"/><rect x="16" y="10" width="4" height="4" fill="#36d399"/></svg>
        </span>
        <h4>2. Risk Categorization</h4>
        <p>Auto-scoring/file labeling for business impact, compliance, and likelihood.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="risk-card">
        <span class="rc-icon">
            <svg viewBox="0 0 42 42"><rect x="9" y="29" width="24" height="4" rx="1.6" fill="#b4e6fb"/><ellipse cx="21" cy="19" rx="12" ry="9" fill="#e8ba3a"/><rect x="18" y="21" width="6" height="6" fill="#36d399"/><ellipse cx="21" cy="19" rx="3" ry="3.5" fill="#fff"/></svg>
        </span>
        <h4>3. Continuous KRI Monitoring</h4>
        <p>Expose control drift, policy exceptions, and residual risk in near real-time.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="risk-card">
        <span class="rc-icon">
            <svg viewBox="0 0 42 42"><ellipse cx="21" cy="21" rx="17" ry="15" fill="#fff" stroke="#36d399" stroke-width="2"/><path d="M14 32 l7-15 7 15" fill="#e8ba3a"/></svg>
        </span>
        <h4>4. Incident Response</h4>
        <p>Rapid notification, prioritization and playbook-driven escalation of adverse events.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="risk-card">
        <span class="rc-icon">
            <svg viewBox="0 0 40 40"><polygon points="20,6 28,34 12,34" fill="#36d399" stroke="#e8ba3a" stroke-width="1.5"/><ellipse cx="20" cy="36" rx="15" ry="4" fill="#e8ba3a" fill-opacity=".14"/></svg>
        </span>
        <h4>5. Controls & Mitigation</h4>
        <p>Actionable recommendations for configuration, privilege, and process updates.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
</div>
@endsection