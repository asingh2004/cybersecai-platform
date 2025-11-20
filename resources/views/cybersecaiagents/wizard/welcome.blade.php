@extends('template')
@section('main')
<style>
.wproc-map { display: flex; justify-content:center; gap:20px; flex-wrap:wrap; margin:23px 0 29px 0;}
.proc-step { 
    background:#fff; color:#1b2538; font-weight:700; border-radius:18px;
    border:3px solid #0e9279; box-shadow:0 2px 11px #1204040e; 
    padding:18px 28px; font-size:1.22rem; cursor:pointer;
    display:inline-flex;align-items:center; gap:9px;
    transition:.16s, border 0.18s;
}
.proc-step svg {width:32px;height:32px;}
.proc-step.active, .proc-step:hover {border-color:#1b2538;background:#e9fffa; color:#0e9279; }
.get-started-btn {
    margin-top:30px;
    background: #fff; color: #0e9279; border: 3px solid #0e9279;
    font-family:inherit; font-weight: 800; letter-spacing:.04em;
    border-radius:35px; padding:17px 43px; font-size:22px;
    box-shadow: 0 2px 10px #0e927919;
    transition:background .17s, color .14s, border .13s;
}
.get-started-btn:hover { background: #0e9279 !important; color: #fff !important; border-color:#0e9279;}
.agent-hero-wrap {text-align:center; margin-bottom:16px;}
.agent-roboticarm {
    width:102px;height:102px;display:inline-block;
    border: 4px solid #0e9279; border-radius:60px; background:#f8fcfc; margin-bottom:11px;
    box-shadow:0 2px 13px #0e927911;
}
.agent-roboticarm .arm {transition: transform 0.3s cubic-bezier(.79,.02,.12,.96);}
.agent-roboticarm:hover .arm {transform: rotate(-6deg) scale(1.1);}
</style>


<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                      
<div class="col-md-10">
  <div style="max-width:700px; background:#fff; border-radius:36px; margin:54px auto 0; padding:54px 30px 33px; text-align:center; box-shadow:0 4px 31px #18d58d10; border:4px solid #0e9279;">
    <div class="agent-hero-wrap">
      <!-- Robotic Arm SVG  -->
      <svg class="agent-roboticarm" viewBox="0 0 110 110">
        <!-- Background circle -->
        <circle cx="55" cy="55" r="47" fill="#fff" stroke="#0e9279" stroke-width="4"/>
        <!-- Arm base -->
        <rect x="46" y="72" width="18" height="19" rx="7" fill="#fff" stroke="#0e9279" stroke-width="3"/>
        <!-- Arm forearm (animated piece) -->
        <g class="arm">
            <rect x="51" y="55" width="8" height="28" rx="4" fill="#6beed7" stroke="#0e9279" stroke-width="3"/>
            <rect x="48.2" y="40" width="13.7" height="20" rx="6.5" fill="#e8fffa" stroke="#0e9279" stroke-width="3"/>
            <!-- Gripper -->
            <rect x="54.9" y="32" width="3.2" height="10" rx="1.5" fill="#0e9279"/>
            <rect x="50.1" y="32" width="3.2" height="10" rx="1.5" fill="#0e9279"/>
            <ellipse cx="56.8" cy="32.5" rx="2.9" ry="2.1" fill="#0e9279"/>
        </g>
        <!-- Camera "eye" -->
        <ellipse cx="55" cy="35" rx="4" ry="4.2" fill="#fff" stroke="#0e9279" stroke-width="2"/>
        <ellipse cx="55" cy="36" rx="1.4" ry="2" fill="#0e9279"/>
        <!-- Joint -->
        <ellipse cx="55" cy="60" rx="4.1" ry="4.1" fill="#fff" stroke="#0e9279" stroke-width="2"/>
        <!-- Base shadow -->
        <ellipse cx="55" cy="93" rx="16" ry="8" fill="#0e927910"/>
      </svg>
    </div>
    <h1 style="color:#0e9279;font-size:2.19rem; font-family:Inter,sans-serif; font-weight:900;line-height:1.097; letter-spacing:-.5px;">
      Sensitive Data Compliance Agent
    </h1>
    <div style="font-size:1.15rem; color:#2c2c2f; line-height:1.45; margin:19px auto 7px; max-width:420px;">
      <strong>My mission:</strong> Replace the old auditor/data specialist workflow.<br>
      <span style="color:#359a8a;font-size:.99rem;">Classify files, assign risk, and document your controls effortlessly — all via AI-driven agents. Click any step below to continue!</span>
    </div>
    <!-- Process map -->
    <div class="wproc-map" style="margin-top:27px;">
      <div class="proc-step" onclick="location.href='{{route('cybersecaiagents.step1')}}'">
        <svg fill="#e1fffa" stroke="#0e9279" stroke-width="2"><rect x="5" y="7" rx="5" width="23" height="18"/><path d="M9 12h15M9 16h15" /></svg>
        Data Source
      </div>
      <div class="proc-step" onclick="location.href='{{route('cybersecaiagents.policyForm')}}'">
        <svg fill="#e1fffa" stroke="#0e9279" stroke-width="2"><rect x="6" y="8" rx="3" width="21" height="16"/><rect x="11" y="13" width="11" height="6" rx="2" fill="#0e9279"/></svg>
        Policy/Controls
      </div>
      <div class="proc-step" onclick="location.href='{{route('cybersecaiagents.agentStep')}}'">
        <svg fill="#0e9279" stroke="#0e9279" stroke-width="2"><rect x="8" y="8" width="18" height="18" rx="5"/><path d="M13 16l4 4 4-4" stroke="#fff"/></svg>
        Discover
      </div>
      <div class="proc-step" onclick="location.href='{{route('cybersecaiagents.visualsDashboard')}}'">
        <svg fill="#0e9279" stroke="#0e9279" stroke-width="2"><circle cx="15" cy="14" r="8"/><rect x="10" y="24" width="10" height="3" rx="1.2" fill="#82f4db"/></svg>
        Classify & Visualize
      </div>
    </div>
    <a href="{{ route('cybersecaiagents.step1') }}" class="get-started-btn">Get started →</a>
  </div>
</div></div></div></div></div></div></div>
@endsection