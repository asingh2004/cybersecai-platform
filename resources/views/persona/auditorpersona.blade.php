@extends('template')

@push('css')
<style>
.auditor-hero {
    background: linear-gradient(120deg, #233350 60%, #35f3c6 120%);
    color: #fff;
    text-align:center;
    border-radius:0 0 22px 22px;
    padding: 54px 0 28px;
    box-shadow:0 2px 21px #16d58d28;
}
.auditor-hero h2{
    font-weight:900;font-size:2.3em;margin:15px 0 12px 0;letter-spacing:-.02em;
}
.auditor-hero .persona-svg {
    width: 86px;height:86px;border-radius:50%;margin-bottom:8px;background: #fff;
}
.audit-cards-wrap{
    display:flex;flex-wrap:wrap;gap:36px;max-width:1080px;margin:53px auto 20px auto;
    justify-content:space-between;align-items:stretch;
}
.audit-card{
    flex:1 1 215px;background:#fff;padding:34px 26px;border-radius:13px;box-shadow:0 3px 30px #2379c212;position:relative;
    min-width:196px;max-width:265px;text-align:left;transition: .17s;cursor:pointer;border-left:8px solid #16d58d;
}
.audit-card:hover {box-shadow:0 7px 30px #35f3c65a;border-color:#36d399; transform: translateY(-3px) scale(1.015);}
.audit-card .audit-icon {width:2.15em; height:2.15em; margin-bottom:13px;}
.audit-card h4{font-size:1.13em;margin:0 0 6px 0;color:#1877c2;font-weight:600;}
.audit-card p{color:#233350;font-size:1.08em;min-height:51px;}
.audit-card .step-arrow {
    position:absolute;right:15px;bottom:18px;font-size:1.4em;color:#35f3c6;opacity:.82;
}
@media (max-width:950px){
    .audit-cards-wrap{flex-wrap:wrap;gap:25px;}
    .audit-card{max-width:98vw;}
}
@media (max-width:680px){
    .audit-cards-wrap{flex-direction:column;}
}
</style>
@endpush

@section('main')

<div class="auditor-hero">
    <svg class="persona-svg" viewBox="0 0 90 90">
        <ellipse cx="45" cy="47" rx="38" ry="38" fill="#fff" stroke="#36d399" stroke-width="3"/>
        <ellipse cx="45" cy="50" rx="30" ry="27.5" fill="#e8fff9" stroke="#1877c2" stroke-width="2"/>
        <rect x="32" y="59" width="25" height="10" rx="3.6" fill="#b4e6fb"/>
        <ellipse cx="45" cy="47" rx="11" ry="8" fill="#1877c2" fill-opacity=".18"/>
        <ellipse cx="45" cy="45" rx="16" ry="19.5" fill="none" stroke="#36d399" stroke-width="2"/>
        <ellipse cx="45" cy="57" rx="10" ry="3.6" fill="#b4e6fb" fill-opacity="0.75"/>
        <!-- Spectacles! -->
        <rect x="32" y="36" width="10" height="8" rx="3.2" fill="#1877c2"/>
        <rect x="48" y="36" width="10" height="8" rx="3.2" fill="#1877c2"/>
    </svg>
    <h2>Compliance Auditor <span style="color:#36d399">Persona</span></h2>
    <div style="font-size:1.15em;max-width:510px;margin:16px auto;">
        This dashboard represents every auditor's dream—<b>framework-aligned, AI-driven, always-on compliance</b> with one click.
        <br>Click any step to review audits or kick off evidence.
    </div>
</div>

<div class="audit-cards-wrap">
    <a href="{{ route('wizard.dashboard') }}" class="audit-card">
      
        <span class="audit-icon">
            <!-- Radar / discovery SVG -->
            <svg viewBox="0 0 39 39"><ellipse cx="19.5" cy="19.5" rx="17" ry="17" fill="#b4e6fb" stroke="#36d399" stroke-width="2"/>
                <path d="M19.5 19.5 v-8 M19.5 19.5 l8,8" stroke="#1877c2" stroke-width="2" /><circle cx="19.5" cy="11.5" r="2" fill="#36d399"/></svg>
        </span>
        <h4>1. Asset & Data Discovery</h4>
        <p>
            See which repositories are configured and scanned for sensitive files. <b>In Unified Dashboard</b> </p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.visuals_dashboard') }}" class="audit-card">
        <span class="audit-icon">
            <!-- Tags SVG -->
            <svg viewBox="0 0 39 39"><rect x="7" y="16" width="24" height="8" rx="4" fill="#36d399" stroke="#1877c2" stroke-width="2"/><rect x="14" y="9" width="11" height="7" rx="3.1" fill="#b4e6fb" stroke="#36d399" stroke-width="2"/><circle cx="19.5" cy="13" r="2" fill="#1877c2"/></svg>
        </span>
        <h4>2. Classification & Tagging</h4>
        <p>View insights on risk rating of files based on applicable regulations/ compliance. <b>View Insights.</b></p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="audit-card">
        <span class="audit-icon">
            <!-- Bell/alert SVG -->
            <svg viewBox="0 0 39 39"><rect x="14" y="26" width="11" height="5" rx="2.2" fill="#e8fff9" stroke="#36d399" stroke-width="2"/><ellipse cx="19.5" cy="18" rx="7.5" ry="7" fill="#fff" stroke="#1877c2" stroke-width="2"/><ellipse cx="19.5" cy="18" rx="3.8" ry="3.7" fill="#36d399"/><rect x="18" y="7" width="3" height="7" rx="1.2" fill="#1877c2"/></svg>
        </span>
        <h4>3. Monitoring & Alerts</h4>
        <p>Track drift. Real-time alerts for risky or misconfigured data.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('persona.dashboard') }}" class="audit-card">
        <span class="audit-icon">
            <!-- Document SVG -->
            <svg viewBox="0 0 36 36"><rect x="8" y="8" width="20" height="20" rx="6" fill="#e8fff9" stroke="#36d399" stroke-width="2"/><polyline points="13,21 18,26 23,16" fill="none" stroke="#1877c2" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        <h4>4. Audit Prep & Evidence</h4>
        <p>One-click logs, evidence, and historical views for audit “proof of control”.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('persona.dashboard') }}" class="audit-card">
        <span class="audit-icon">
            <!-- Fix/Remediate (Shield) -->
            <svg viewBox="0 0 36 36"><path d="M18 5c8.5 7.3 14 4.5 14 13.2C32 33.3 18 31 18 31S4 33.3 4 18.2C4 9.5 9.5 12.3 18 5z" fill="#1877c2" stroke="#fff" stroke-width="1.7"/><line x1="18" y1="15" x2="18" y2="25" stroke="#36d399" stroke-width="2"/><circle cx="18" cy="26" r="2" fill="#36d399"/></svg>
        </span>
        <h4>5. Remediation Guidance</h4>
        <p>AI-driven recommendations and SOAR/ITSM workflows for closure.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
</div>
                      
@endsection