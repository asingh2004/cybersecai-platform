@extends('template')

@push('css')
<style>
.about-card {
    max-width: 900px;
    margin: 64px auto 48px auto;
    background: #fff;
    border-radius:20px;
    box-shadow:0 10px 34px #13284a18;
    padding: 50px 40px 40px 40px;
}
@media (max-width:650px) {
    .about-card { padding: 8vw 4vw; }
}
.about-section-title {
    font-size:2.08em;
    font-weight:800;
    color:#1877c2;
    letter-spacing:-.03em;
    margin-bottom:.7em;
}
.about-highlight {
    font-size:1.15em;
    background: #f9fefd;
    padding: 20px;
    border-left:6px solid #36d399;
    margin:20px 0 32px 0;
    border-radius:11px;
}
.about-list li {margin-bottom:1.13em;}
.about-lead {
    font-size:1.18em;
    margin:28px 0 19px 0;
    font-weight:700;
    color:#1a8562;
}
.mission-quote {
    background:#eefcfa;
    border-left:5px solid #1877c2;
    font-style:italic;
    font-size:1.13em;
    margin:24px 0 38px 0;
    padding:14px 30px 16px 28px;
}
.team-section {
    margin-top:36px;
    padding-top:28px;
    border-top:1.1px solid #e0f0ec;
}
</style>
@endpush

@section('main')
<div class="about-card">
    <div class="about-section-title">About CyberSecAI</div>

    <div class="about-highlight">
        At <strong>CyberSecAI</strong>, our mission is simple: <br>
        <span style="color:#36d399;">Make data compliance, risk management, and sensitive information protection <u>truly automatic</u> for organizations of every size.</span>
    </div>

    <div class="about-lead">
        Security. Compliance. Peace of Mind. — Reimagined for the AI Era.
    </div>

    <p>
        CyberSecAI was founded by a team of compliance, cybersecurity, and AI experts who saw a new reality: the explosion of unstructured files across cloud and legacy systems—and the dramatic rise of global regulatory requirements—had left security and audit teams overwhelmed. Point solutions failed. Manual spreadsheets broke down. Security and compliance teams spent their days in “catch up” mode as data sprawled and auditors demanded real evidence.
    </p>

    <div class="mission-quote">
        “Every business today is a data business. You shouldn’t need an army of analysts or a fortune in legacy tools to protect your customers, pass audits, and control risk. Our platform puts <b>AI-powered, explainable, real-time compliance</b> into the hands of any company that cares about privacy and trust.”
    </div>

    <h3 style="margin-top:23px;">What Makes CyberSecAI Different?</h3>
    <ul class="about-list">
      <li><b>Unified sensitive data discovery</b>—across all major clouds, SaaS, and on-premises file stores (not just one vendor or format).</li>
      <li><b>AI-powered risk detection</b>—automatically classifies, risk-rates, and explains every finding (no black-boxes).</li>
      <li><b>Continuous, real-time monitoring</b>—not slow batch scans.</li>
      <li><b>Seamless SIEM/SOAR integration</b>—fits enterprise needs, but accessible and cost-effective for small and medium business.</li>
      <li><b>Actionable evidence, not just alerts</b>—detailed, audit-ready log trails, not just “anomalies”.</li>
      <li><b>Rapid onboarding</b>—from proof-of-concept to protection in days, not months.</li>
      <li><b>Global, evolving compliance</b>—built to keep up with every regulation, everywhere.</li>
    </ul>

    <p>
      Our platform is trusted from higher education to financial services to global consultancies, proven at full enterprise scale (200M+ files analyzed in a single project). 
    </p>

    <div class="team-section">
        <h3>Meet the Founders</h3>
        <p>
           Have led large-enterprise compliance automation; evangelist for AI/automation in enterprise security.
        </p>
        <p>
          The team includes top AI and security engineers, compliance analysts, and data scientists—each passionate about privacy, proactive security, and building the world’s leading compliance platform for the AI era.
        </p>
    </div>

    <div class="team-section">
        <h3>Our Values</h3>
        <ul class="about-list">
            <li><b>Transparency:</b> Compliance should be clear, explainable, and trustworthy.</li>
            <li><b>Agility:</b> Adapting quickly to regulations and client needs, everywhere data lives.</li>
            <li><b>Innovation:</b> Using real AI—not just "checkbox automation"—to solve real-world risk.</li>
            <li><b>Simplicity:</b> Solutions that work out-of-the-box—no months-long onboarding or cryptic config.</li>
        </ul>
    </div>

    <p class="mt-4" style="font-size:1.13em; color:#207364;">
      <b>Ready to see why CyberSecAI is trusted by security and compliance leaders across industries?</b> <br>
      <a href="https://cybersecai.io" class="read-link">Explore the platform or request a personalized demo now →</a>
    </p>
</div>
@endsection