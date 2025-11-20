@extends('template')

@push('css')
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<style>
body, h1, h2, h3, h4, h5, h6 {font-family: "Raleway", sans-serif}
body, html {height: 100%; line-height: 1.8;}
.bgimg-1 {background-position: center; background-size: cover; background-image: url("{{ asset('public/front/images/home/hero_image_1.png') }}"); min-height: 100%;}
.w3-bar .w3-button {padding: 16px;}
/* Timeline cards */
.roadmap-cards { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 32px; margin-top: 48px;}
.roadmap-card {
    background: #fff; border-radius: 14px; box-shadow: 0 2px 24px 0 rgba(22,37,74,.11);
    padding: 36px 32px; min-width: 250px; max-width: 330px; flex: 1;
    border-left: 8px solid #1877c2; position: relative;
}
.roadmap-card h4 {color: #1877c2; font-weight: 700; margin-top: 0;}
.roadmap-card h2 {font-size: 1.2em; margin-bottom: 10px;}
.roadmap-card .milestone-icon svg {width: 2.3em; height:2.3em; margin-bottom:10px; vertical-align: middle;}
.roadmap-card span.roadmap-date { font-size: 0.98em; font-weight: bold; color: #444; display: block; margin-bottom:2px;}
.w3-icon {margin-bottom:14px; display:block;}

/* Pricing cards */
.pricing-table { display: flex; justify-content: center; gap:32px; flex-wrap: wrap;}
.pricing-plan { background: #fff; border-radius: 12px; box-shadow: 0 2px 24px 0 rgba(22,37,74,.06); padding:34px 32px; min-width:240px; max-width:320px; flex:1; margin:14px 0; }
.pricing-plan * { color: #222 !important; }
.pricing-header { color: #1877c2 !important; }
.pricing-price { color: #2E9176 !important; }
.btn.btn-block { color: #fff !important; background:#36d399; }
.pricing-plan.popular {border:2.5px solid #1877c2; }
.pricing-plan .btn {background:#36d399; border: none; color: #fff; font-weight: 700;}
.pricing-features {
    list-style: none;
    margin: 22px 0 12px 0;
    padding: 0;
    text-align: left;
}
.pricing-features li {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    font-size: 1.13em;
    color: #222;
    font-weight: 500;
    margin: 0 0 10px 0;
}
.shield-bullet {
    width: 1.38em; height: 1.38em; flex-shrink: 0; margin-top: 3px;
    display: inline-block; vertical-align: middle;
}

/* Blog/demo CTA/cards */
.demo-card, .blog-card {
    max-width: 880px;
    margin: 60px auto 48px auto;
    border-radius: 18px;
    background: rgba(255,255,255,0.98);
    box-shadow: 0 10px 40px #13284a25;
    padding: 38px 28px 32px 28px;
}
.demo-card h3, .blog-card h3 { margin-bottom: 27px; font-weight: 800; letter-spacing:-.02em; color: #1877c2;}
.demo-card label {font-weight:600; margin-top:11px; display:inline-block; color: #222;}
.demo-card input, .demo-card textarea {
    width: 100%; font-size: 1.09em; font-family: inherit; margin-top:7px;
    border:1.6px solid #cde3e0; border-radius:8px; padding:12px 13px; margin-bottom:13px;
    background: #f9feff; transition:border .15s;
}
.demo-card input:focus, .demo-card textarea:focus { outline:none; border-color:#36d399; background:#fff;}
.demo-success {background: #defbe6; color: #207364; border:1.6px solid #36d399; border-radius:8px; margin-bottom:13px; padding:12px;}
.demo-submit-btn {
    width:100%; background: linear-gradient(90deg,#36d399 70%,#14a37f 113%);
    border: none; color: #fff; font-weight: 800; font-size:1.13em; padding:15px 0;
    margin-top:17px; border-radius:9px; box-shadow:0 2px 14px #36d39912;
    transition:background .2s; letter-spacing:.03em;
}
.demo-submit-btn:hover { background: linear-gradient(90deg,#14a37f 70%,#33f6ca 113%);}
@media (max-width:500px) {
    .demo-card, .blog-card {padding: 23px 6vw 20px 6vw;}
}
.blog-title {font-size:2em;font-weight:800;color:#1877c2;margin-bottom:.7em;}
.blog-meta {font-size:.97em;color:#567;margin-bottom:23px;}

/* Homepage Hero/demo polish */
.hero-demo-area {display:flex;align-items:center;gap:28px;}
.hero-demo-video {box-shadow:0 6px 30px #2222; border-radius:19px; background:#fff;}
.free-ai-scan-btn {
    display: inline-block;
    background: linear-gradient(90deg,#36d399 72%,#1877c2 120%);
    color: #fff; font-weight:800; font-size:1.27em; border:0;
    border-radius:11px; padding:19px 42px 17px 42px; margin-top:36px;
    margin-bottom:12px; letter-spacing:.01em; box-shadow:0 1px 16px #1877c222;
    transition:background .21s;
}
.free-ai-scan-btn:hover {
    background:linear-gradient(90deg,#1877c2 68%, #36d399 120%);
    color:#fff;
}
.ph-badge {margin-top:12px;}

/* Responsive */
@media (max-width: 992px) {
    .roadmap-cards {flex-direction: column; align-items: center; gap: 24px;}
    .roadmap-card, .pricing-plan {width: 100%; max-width: 400px;}
    .pricing-table {flex-direction: column; align-items: center;}
}
@media (max-width:900px) {
    .hero-demo-area {flex-direction:column;align-items: flex-start;}
    .hero-demo-video {width:95vw;}
}
@media (max-width:1000px) {
    .blog-card, .demo-card {max-width:97vw;}
}

</style>
@endpush

@section('main')
<!-- HERO HEADER WITH DEMO and CTA-->
<header class="bgimg-1 w3-display-container w3-grayscale-min" id="home">
  <div class="w3-display-left w3-text-white" style="padding:48px;max-width:950px;width:100%;">
   <div class="hero-demo-area">
    <div style="flex:2">
      <span class="w3-jumbo w3-hide-small" style="font-weight:800;">AI Compliance For Modern Data — Instantly</span><br>
      <span class="w3-xxlarge w3-hide-large w3-hide-medium">Unified Data Security & Compliance</span><br>
    <span class="w3-large" id="typewriter">
  <b>Legacy privacy tools promise classification—CyberSecAI delivers action, explanation, and autonomous compliance. Our agentic AI doesn’t just scan: it closes the loop, provides answer-ready evidence, and integrates with your reality—at cloud scale, at SMB cost, tomorrow-ready for any regulation.</b>
</span>
      <div>
        <a href="#ai-compliance-bot" class="free-ai-scan-btn">🚀 Try a Free AI File Risk Scan</a>
      </div>

      <!-- Product Hunt badge: replace the link with your live launch after submitting -->
      <!--<a href="https://www.producthunt.com/posts/cybersecai" rel="noopener" target="_blank" class="ph-badge">
        <img src="https://api.producthunt.com/widgets/embed-image/v1/launch.svg?product_id=your_producthunt_id&theme=light" alt="CyberSecAI - AI powered file compliance and audit" style="height:40px;border-radius:8px;">
      </a>
    </div>-->
    
      <!-- Or Embed YouTube: -->
      <!--
      <iframe width="360" height="205" src="https://www.youtube.com/embed/XXXXX?autoplay=0&loop=1" frameborder="0" allowfullscreen style="border-radius:19px;"></iframe>
      -->
   </div>
  </div>
</header>

<!-- FEATURES/ABOUT SECTION -->
<div class="w3-container" style="padding:96px 16px" id="about">
  <h3 class="w3-center">100% Continuous Sensitive Data Compliance</h3>
  <p class="w3-center w3-large">AI-driven, unified automation for all your modern multi-cloud data.</p>
  <div class="w3-row-padding w3-center" style="margin-top:64px">
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Discovery Icon -->
        <svg width="44" height="44" viewBox="0 0 44 44"><circle cx="20" cy="20" r="14" fill="#b4e6fb" stroke="#1877c2" stroke-width="3"/><rect x="30" y="30" width="10" height="3.2" rx="1" fill="#36d399" transform="rotate(45 30 30)"/><circle cx="20" cy="20" r="7" fill="none" stroke="#1877c2" stroke-width="2"/></svg>
      </span>
      <p class="w3-large">Enterprise-wide Discovery</p>
      <p>Find & extract sensitive data—PII, health, financial—across Microsoft 365, Google Drive, Box, AWS S3, & legacy stores.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Tags icon -->
        <svg width="44" height="44" viewBox="0 0 44 44"><rect x="9" y="19" width="25" height="12" rx="4" fill="#36d399" stroke="#1877c2" stroke-width="2"/><rect x="16" y="12" width="16" height="10" rx="4" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><circle cx="24" cy="17" r="1.8" fill="#1877c2"/></svg>
      </span>
      <p class="w3-large">Automated Labelling & Risk</p>
      <p>Instantly classify & label files by risk. Full support for GDPR, HIPAA, Privacy Act, and more.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Lightning bolt -->
        <svg width="44" height="44" viewBox="0 0 44 44"><polygon points="18,32 22,14 26,18 26,12 34,25 27,25 25,32" fill="#1877c2"/><polyline points="22,14 17,18 17,25 22,25 24,32" fill="none" stroke="#36d399" stroke-width="2" /></svg>
      </span>
      <p class="w3-large">Real-Time Controls</p>
      <p>Enforce policies and send alerts/remediation the moment risk changes—zero lag, zero blindspots.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Dashboard/report -->
        <svg width="44" height="44" viewBox="0 0 44 44"><rect x="11" y="13" width="22" height="18" rx="4" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><rect x="16" y="23" width="2.6" height="5" fill="#1877c2"/><rect x="21" y="19" width="2.6" height="9" fill="#36d399"/><rect x="26" y="16" width="2.6" height="12" fill="#1877c2"/></svg>
      </span>
      <p class="w3-large">Unified Reporting & Audit</p>
      <p>Actionable dashboard and audit trails—evidence for every change, every standard, every file, always.</p>
    </div>
  </div>
  <div class="w3-row-padding w3-center" style="margin-top:64px">
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Cog -->
        <svg width="44" height="44" viewBox="0 0 44 44"><circle cx="22" cy="22" r="10" fill="#36d399" stroke="#1877c2" stroke-width="2"/><path d="M22 10v-7M22 41v-7M10 22h-7M41 22h-7M15.9 15.9l-4.7-4.7M32.8 32.8l-4.7-4.7M15.9 28.1l-4.7 4.7M32.8 11.2l-4.7 4.7" stroke="#1877c2" stroke-width="2" fill="none"/></svg>
      </span>
      <p class="w3-large">Continuous Compliance</p>
      <p>AI scans and risk rates 100% of files, all the time. No more sampling, no more manual audits.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Shield + check -->
        <svg width="44" height="44" viewBox="0 0 44 44"><path d="M22 7c6 6 14 6 14 18 0 20-14 24-14 24S8 45 8 25c0-12 8-12 14-18z" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><polyline points="17,27 21,31 29,22" fill="none" stroke="#36d399" stroke-width="3" stroke-linecap="round"/></svg>
      </span>
      <p class="w3-large">Incident Forensics</p>
      <p>Get instant clarity on which data/standards would be impacted by a breach or exfil tration.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Cloud -->
        <svg width="44" height="44" viewBox="0 0 44 44"><ellipse cx="28" cy="28" rx="11" ry="7" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><ellipse cx="20" cy="32" rx="8" ry="5.5" fill="#36d399" stroke="#1877c2" stroke-width="2"/></svg>
      </span>
      <p class="w3-large">Cloud-Native SaaS</p>
      <p>Deploy instantly, scale up or down, no infrastructure to manage.</p>
    </div>
    <div class="w3-quarter">
      <span class="w3-icon">
        <!-- Plug -->
        <svg width="44" height="44" viewBox="0 0 44 44"><rect x="18" y="9" width="8" height="12" rx="4" fill="#b4e6fb" stroke="#1877c2" stroke-width="2"/><rect x="14" y="25" width="16" height="6" rx="3" fill="#36d399" stroke="#1877c2" stroke-width="2"/><rect x="19" y="32" width="6" height="6" rx="2" fill="#1877c2"/><line x1="21" y1="9" x2="21" y2="4" stroke="#1877c2" stroke-width="2"/><line x1="23" y1="9" x2="23" y2="4" stroke="#1877c2" stroke-width="2"/></svg>
      </span>
      <p class="w3-large">Integrations Ready</p>
      <p>Connect to SIEM, SOAR, GRC, or incident management tools—full integration suite coming.</p>
    </div>
  </div>
</div>

<!-- VALUE PROPOSITION -->
<div class="w3-container w3-light-grey" style="padding:96px 16px">
  <div class="w3-row-padding">
    <div class="w3-col m7">
      <h2>Unify. Automate. Never Miss a Risk.</h2>
      <ul class="w3-ul">
        <li><b>End blindspots:</b> Coverage for all your data stores in one view.</li>
        <li><b>Audit at scale:</b> Continuous, automated evidence for auditors and compliance teams.</li>
        <li><b>Prioritize real-world risk:</b> Remediation tailored to each regulation and business context.</li>
        <li><b>Evolve faster:</b> Stay in lockstep with new standards, regulations, and cloud transformations.</li>
      </ul>
    </div>
    <div class="w3-col m5 w3-center">
      <img class="w3-image w3-round-large" src="{{ asset('public/front/images/home/unified_compliance.svg') }}" alt="Unified Compliance" width="340">
    </div>
  </div>
</div>


<!-- AI Compliance Bot Lead Magnet -->
<div class="w3-container w3-center" id="ai-compliance-bot" style="padding:68px 0 32px 0; max-width:600px; margin:0 auto;">
    <div class="w3-card" style="border-radius:16px;box-shadow:0 5px 16px #36d39918;padding:32px 2vw 30px 2vw;">
        <h2 style="color:#1877c2;font-weight:800;">Free Basic Version of AI Compliance File Scan</h2>
        <p class="w3-large" style="margin:9px 0 22px 0;">
            Try our free compliance bot: Upload a file and get instant AI-powered risk and policy feedback for GDPR, CCPA, or Australian Privacy Act!</p>
        <form action="{{ route('ai.bot.scan') }}#ai-compliance-bot" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".txt,.csv,.doc,.docx,.pdf" required style="margin: 12px 0 20px 0;">
            <button class="btn btn-success" style="padding:12px 2.2em;font-size:1.15em;border-radius:8px;" type="submit">
                Scan My File
            </button>
        </form>
        @if(session('ai_scan_summary'))
            <div class="w3-panel w3-pale-green w3-leftbar w3-border-green" style="margin-top:20px;">
                <h4>AI Analysis Summary</h4>
                <div>{!! session('ai_scan_summary') !!}</div>
                <div style="margin-top:24px;">
                    <a class="btn btn-warning" href="{{ route('demo.request.form') }}">Request Full Compliance Report</a>
                </div>
            </div>
        @endif
        @if(session('ai_scan_error'))
            <div class="w3-panel w3-pale-red w3-leftbar w3-border-red" style="margin-top:20px;">
                {{ session('ai_scan_error') }}
            </div>
        @endif
    </div>
</div>

<!-- ROADMAP SECTION -->
<div class="w3-container" style="padding:96px 16px;">
  <h3 class="w3-center">Product Roadmap</h3>
  <div class="roadmap-cards">

    <div class="roadmap-card">
      <div class="milestone-icon">
        <!-- Rocket SVG -->
        <svg width="44" height="44" viewBox="0 0 48 48"><ellipse cx="24" cy="30" rx="11" ry="10" fill="#b4e6fb"/><polygon points="23,6 27,6 32,18 16,18" fill="#1877c2"/><rect x="20" y="24" width="8" height="8" rx="3" fill="#36d399"/></svg>
      </div>
      <span class="roadmap-date">Beta: May 2025</span>
      <h2>AI-Powered Discovery Live</h2>
      <div>
        - Sensitive data discovery & classification in Microsoft 365 (SharePoint, OneDrive, Teams), Google Drive, SMB and AWS S3<br>
        - Unified compliance dashboard MVP<br>
        - Risk-based labeling and alerting for 25+ regulations
      </div>
    </div>

    <div class="roadmap-card">
      <div class="milestone-icon">
        <!-- Line chart SVG -->
        <svg width="44" height="44" viewBox="0 0 48 48"><rect x="6" y="32" width="36" height="6" rx="2" fill="#b4e6fb"/><polyline points="14,32 22,18 30,28 36,12" fill="none" stroke="#36d399" stroke-width="3"/><circle cx="14" cy="32" r="2" fill="#1877c2"/><circle cx="22" cy="18" r="2" fill="#1877c2"/><circle cx="30" cy="28" r="2" fill="#1877c2"/><circle cx="36" cy="12" r="2" fill="#36d399"/></svg>
      </div>
      <span class="roadmap-date">Soft Launch: September 2025</span>
      <h2>Connector Expansion</h2>
      <div>
        - AI Agents for various Personas launched<br>
        - Deeper analytics and incident forensics<br>
        - Enhanced dashboard and search experience
      </div>
    </div>

    <div class="roadmap-card">
      <div class="milestone-icon">
        <!-- Globe/Audit SVG -->
        <svg width="44" height="44" viewBox="0 0 48 48"><ellipse cx="24" cy="24" rx="20" ry="20" fill="#36d399"/><ellipse cx="24" cy="24" rx="11" ry="11" fill="#b4e6fb"/><rect x="28" y="24" width="5" height="13" rx="2" fill="#1877c2" transform="rotate(45 28 24)"/></svg>
      </div>
      <span class="roadmap-date">Incremental Global Launch: From June 2026</span>
      <h2>Scalable Automation & Integration</h2>
      <div>
        - Native SIEM, SOAR, GRC integration<br>
        - Automated remediation and policy workflow<br>
        - Audit evidence export and global scale SaaS
      </div>
    </div>
  </div>
</div>

<!-- COMMITMENT SECTION -->
<div class="w3-container w3-light-grey w3-padding-64">
  <div class="w3-row-padding">
    <div class="w3-col m7">
      <h3>We Protect What Matters Most</h3>
      <ul class="w3-ul">
        <li>We never use your data for AI training</li>
        <li>All instructions/messages remain private</li>
        <li>Cloud-first, privacy-by-design and zero trust architecture</li>
        <li>Automated, verifiable, always-on compliance</li>
      </ul>
    </div>
    <div class="w3-col m5">
      <h3 class="w3-wide"><svg width="28" height="28" aria-hidden="true"><path d="M14 3c4 4 10 4 10 11 0 15-10 15-10 15S4 29 4 14c0-7 6-7 10-11z" fill="#b4e6fb" stroke="#1877c2" stroke-width="1.4"/><polyline points="9,18 13,22 20,14" fill="none" stroke="#36d399" stroke-width="2" stroke-linecap="round"/></svg> Data Security</h3>
      <h3 class="w3-wide"><svg width="28" height="28" aria-hidden="true"><circle cx="14" cy="14" r="10" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><path d="M14 5v-2M14 25v-2M5 14H2M25 14h-2M8.5 8.5l-2-2M19.5 19.5l-2-2M8.5 19.5l-2 2M19.5 8.5l-2 2" stroke="#1877c2" stroke-width="1.3" fill="none"/></svg> Compliance Automation</h3>
      <h3 class="w3-wide"><svg width="28" height="28" aria-hidden="true"><rect x="8" y="6" width="12" height="16" rx="4" fill="#b4e6fb" stroke="#1877c2" stroke-width="1.5"/><rect x="12" y="19" width="4" height="5" rx="1.2" fill="#36d399"/></svg> Single Platform Simplicity</h3>
    </div>
  </div>
</div>

<!-- PRICING SECTION -->
<div class="w3-container w3-center w3-dark-grey" style="padding:96px 16px" id="pricing">
  <h3>Pricing</h3>
  <div class="pricing-table">
    <div class="pricing-plan">
      <div class="pricing-header">Small Business (1-99 users) </div>
      <div class="pricing-price">USD 6.50<span style="font-size:0.9em;">/user/mo</span></div>
      <ul class="pricing-features">
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Unlimited M365 Sites/ S3 Buckets
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Upto Two Data Sources - M365, S3 , Fileshare, Google Drive
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Basic risk reporting
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Unlimited user accounts
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Free On-Boarding support
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        On-going Email support
    </li>
</ul>

     <a href="https://www.cybersecai.io/login" class="btn btn-block w3-button w3-black w3-padding-large w3-margin-top">
    	Get Started
	</a>
    </div>
    <div class="pricing-plan popular">
      <div class="pricing-header">Medium Business (100-999 users)</div>
      <div class="pricing-price">USD 5<span style="font-size:0.9em;">/user/mo</span></div>
      <ul class="pricing-features">
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Unlimited M365 Sites/ S3 Buckets
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Includes use of all available storage connectors
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        SIEM/GRC integration (when GA)
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Once off charge for On-Boarding
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Choice of deployment and branding (incl BYO domain)
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Priority support
    </li>
</ul>

      	<a href="{{ route('demo.request.form') }}" class="btn btn-block w3-button w3-black w3-padding-large w3-margin-top">
  			Contact Us
		</a>
    </div>
    <div class="pricing-plan">
      <div class="pricing-header">Enterprise</div>
      <div class="pricing-price">Custom</div>
      <ul class="pricing-features">
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Unlimited scale
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Custom connectors/workflows
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Once off charge for On-Boarding
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Dedicated success manager
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Choice of deployment and branding (incl BYO domain)
    </li>
    <li>
        <span class="shield-bullet">
            <svg viewBox="0 0 28 28"><path d="M14 3c4.6 3.2 11 3.2 11 10.4C25 27.4 14 29 14 29S3 27.4 3 13.4C3 6.2 9.4 6.2 14 3z" fill="#36d399" stroke="#1877c2" stroke-width="1.5"/><polyline points="9,16 13,20 20,12" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        </span>
        Enterprise SLA & compliance
    </li>
</ul>
  
      <a href="{{ route('demo.request.form') }}" class="btn btn-block w3-button w3-black w3-padding-large w3-margin-top">
  		Contact Us
	</a>
    </div>
  </div>
</div>

<div class="w3-container w3-center" style="padding:64px 16px">
  <h3>Request a Demo</h3>
  <p class="w3-large">See your risk. See your sensitive data. Discover easy compliance.</p>
  <a href="{{ route('demo.request.form') }}" class="w3-button w3-black w3-padding-large w3-margin-top">Request a Demo</a>
 
</div>

@stop

@push('scripts')
<script>
  
document.addEventListener("DOMContentLoaded", function(){
    // If there was a scan result in the last response, scroll to the bot
    @if(session('ai_scan_summary') || session('ai_scan_error'))
        var el = document.getElementById('ai-compliance-bot');
        if(el) el.scrollIntoView({behavior:'smooth',block:'center'});
    @endif
});
  
function onClick(element) {
  document.getElementById("img01").src = element.src;
  document.getElementById("modal01").style.display = "block";
  var captionText = document.getElementById("caption");
  captionText.innerHTML = element.alt;
}
var mySidebar = document.getElementById("mySidebar");
function w3_open() { if (mySidebar.style.display === 'block') {mySidebar.style.display = 'none';} else {mySidebar.style.display = 'block';}}
function w3_close() { mySidebar.style.display = "none"; }
  
  
// Typewriter effect: slower & restarts after finishing
 document.addEventListener("DOMContentLoaded", function(){
    const tw = document.getElementById('typewriter');
    if(!tw) return;
    const text = tw.textContent.trim();
    tw.textContent = ""; // clear it
    const typeDelay = 48; // base ms per char, adjust for slower/faster
    const randomDelay = 22; // some randomness
    
    let i = 0;
    function typeChar() {
        if (i < text.length) {
            tw.innerHTML += text.charAt(i) === "\n" ? "<br/>" : text.charAt(i);
            i++;
            setTimeout(typeChar, typeDelay + Math.floor(Math.random()*randomDelay));
        } // <--- No "else" (don't loop)
    }
    typeChar();
});

</script>
@endpush