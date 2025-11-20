@extends('template')

@push('css')
<style>
.cyber-hero { background: linear-gradient(110deg,#242a53 62%,#b4e6fb 120%); color:#fff; text-align:center;padding:48px 0 23px;border-radius:0 0 22px 22px; }
.cyber-hero h2{font-weight:900;font-size:2.07em;margin:12px 0 9px;}
.cyber-hero .persona-svg { width:70px;height:70px;border-radius:40px; background:#fff;margin-bottom:7px;}
.cyber-cards-wrap{ display:flex; flex-wrap:wrap; justify-content:space-between; gap:39px; max-width:1100px; margin:57px auto 18px;}
.cyber-card{ flex:1 0 220px; background:#fff; border-radius:14px; box-shadow:0 2px 16px #cad8f933; padding:36px 23px 28px 23px; text-align:left; min-width:194px; max-width:270px; cursor:pointer;
    border-left:8px solid #36d399;position:relative;transition: box-shadow .15s, border-color .14s;}
.cyber-card:hover{ box-shadow:0 5px 26px #2ea9b222; border-color:#1877c2;}
.cyber-card .c-icon{ width:2.05em; height:2em;margin-bottom:10px;}
.cyber-card h4{ font-size:1.15em;margin:0 0 7px 0;color:#1877c2;font-weight:700;}
.cyber-card p{ color:#353758;font-size:1.07em;min-height:48px;}
.cyber-card .step-arrow{position:absolute;bottom:20px;right:16px;font-size:1.19em;color:#36d399;}
@media (max-width:900px){ .cyber-cards-wrap{gap:20px;} .cyber-card{max-width:97vw;}}
@media (max-width:650px){ .cyber-cards-wrap{flex-direction:column;gap:11px;}}
</style>
@endpush

@section('main')
<div class="cyber-hero">
    <svg class="persona-svg" viewBox="0 0 70 70">
        <!-- Cybersecurity "shield + lock" SVG -->
        <ellipse cx="35" cy="35" rx="32" ry="32" fill="#fff" stroke="#36d399" stroke-width="2.7"/>
        <ellipse cx="35" cy="34" rx="23" ry="21" fill="#e8fff9" stroke="#1877c2" stroke-width="1.7"/>
        <ellipse cx="35" cy="42" rx="9" ry="8" fill="#36d399"/>
        <rect x="27" y="37" width="16" height="10" rx="6" fill="#fff"/>
        <rect x="32.7" y="43" width="4.6" height="4" rx="1.9" fill="#1877c2"/>
        <rect x="32.5" y="30" width="5" height="7" rx="2.6" fill="#1877c2"/>
        <!-- lines/circuit, cyber feel -->
        <line x1="15" y1="20" x2="30" y2="27" stroke="#1877c2" stroke-width="1.1"/>
        <line x1="55" y1="20" x2="40" y2="27" stroke="#1877c2" stroke-width="1.1"/>
    </svg>
    <h2>Cybersecurity Persona</h2>
    <div style="font-size:1.15em;max-width:520px;margin:0 auto;">
        Proactive, intelligent threat management for all sensitive data—across cloud and endpoints, with AI-powered detection and automated cyber defense.
    </div>
</div>
<div class="cyber-cards-wrap">
    <a href="{{ route('wizard.dashboard') }}" class="cyber-card">
        <span class="c-icon">
            <svg viewBox="0 0 37 37"><ellipse cx="18.5" cy="18.5" rx="15.5" ry="15.5" fill="#b4e6fb" stroke="#36d399" stroke-width="2"/><ellipse cx="18.5" cy="18.5" rx="5.5" ry="10.5" fill="#fff" stroke="#1877c2" stroke-width="2"/><circle cx="18.5" cy="18.5" r="2.3" fill="#1877c2"/></svg>
        </span>
        <h4>1. Asset Visibility</h4>
        <p>Full data map. Surface shadow IT files and unauthorized storage instantly.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="cyber-card">
        <span class="c-icon"><svg viewBox="0 0 36 36"><ellipse cx="18" cy="18" rx="13" ry="13" fill="#fff" stroke="#36d399" stroke-width="2"/><ellipse cx="18" cy="18" rx="8" ry="8" fill="#e8ba3a"/><rect x="15.5" y="27" width="5" height="3.5" fill="#36d399"/><rect x="17.7" y="9" width="1.7" height="5.9" fill="#1877c2"/></svg></span>
        <h4>2. Threat Detection</h4>
        <p>AI/ML detects data exfil, ransomware, insider data use, and automates alerts.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="cyber-card">
        <span class="c-icon">
            <svg viewBox="0 0 40 40"><ellipse cx="20" cy="20" rx="17" ry="17" fill="#e8fff9" stroke="#1877c2" stroke-width="2"/><rect x="17" y="10" width="6" height="20" rx="2.9" fill="#36d399"/><rect x="24" y="20" width="3.7" height="5.3" fill="#b4e6fb"/></svg>
        </span>
        <h4>3. Incident Response</h4>
        <p>One-click playbooks for quarantine, user control, and reporting. SOAR-ready.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="cyber-card">
        <span class="c-icon">
            <svg viewBox="0 0 38 38"><ellipse cx="19" cy="19" rx="15" ry="12.5" fill="#b4e6fb" stroke="#36d399" stroke-width="2"/><rect x="10" y="23" width="16" height="7" rx="3" fill="#fff"/><ellipse cx="19" cy="23" rx="4.4" ry="4" fill="#1877c2"/><rect x="18" y="11" width="2" height="7" fill="#e8ba3a"/></svg>
        </span>
        <h4>4. Forensics & Investigation</h4>
        <p>Historical views, audit chain and root-cause analysis for data breaches.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
    <a href="{{ route('wizard.dashboard') }}" class="cyber-card">
        <span class="c-icon">
            <svg viewBox="0 0 38 38"><circle cx="19" cy="19" r="15" fill="#36d399"/><rect x="8" y="24" width="22" height="6" rx="2.6" fill="#e8ba3a"/><rect x="21" y="11" width="7" height="2.2" fill="#1877c2"/><rect x="11" y="11" width="7" height="2.2" fill="#1877c2"/></svg>
        </span>
        <h4>5. Automated Defense</h4>
        <p>Auto actions—lock, alert, block, or ticket—used to minimize incident scope.</p>
        <span class="step-arrow">&rarr;</span>
    </a>
</div>
@endsection