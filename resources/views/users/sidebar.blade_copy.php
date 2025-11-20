<div class="col-lg-2 p-0 border-right d-none d-lg-block overflow-hidden mt-m-30">
    <div class="main-panel mt-5 h-100">
        <div class="mt-2">
<ul class="list-group list-group-flush pl-3">
    <!-- AI Compliance Crew Section -->
    <!-- AI Compliance Crew Section -->
<li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
    style="font-size:0.99em;border-left:4px solid #36d399; cursor:pointer;"
    data-bs-toggle="collapse" data-bs-target="#personaMenu" aria-controls="personaMenu" aria-expanded="false">
    <span>Agentic AI Compliance Crew</span>
    <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
</li>
<li class="list-group-item p-0 border-0 collapse" id="personaMenu" style="background:none;">
    <ul class="list-group">
		<li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4">
            <a href="/agentic-ai/chatorchestrator" class="text-color font-weight-500">
                <span style="font-size:1.15em;vertical-align:-0.14em;">🖥️</span> Multi-Persona Agent
            </a>
        </li>
      
              

        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4">
            <a href="{{ route('agentic_ai.auditor') }}" class="text-color font-weight-500">
                <span style="font-size:1.15em;vertical-align:-0.13em;">📊</span> Board Report
            </a>
        </li>
        
    </ul>
</li>
  
  
   <!-- Data Breach AI Agent Section -->
    <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
        style="margin-top:12px;font-size:0.99em;border-left:4px solid #1877c2; cursor:pointer;"
        data-bs-toggle="collapse" data-bs-target="#dbManagerMenu" aria-controls="fileMenu" aria-expanded="false">
        <span>Data Breach (DB) AI Agents</span>
        <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
    </li>
    <li class="list-group-item p-0 border-0 collapse" id="dbManagerMenu" style="background:none;">
        <ul class="list-group">
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard.visuals_dashboard')) ? 'active-sidebar' : '' }}">

              <a href="{{ route('agenticai.docs_agent.index') }}" class="text-color font-weight-500">
                        <i class="fas fa-balance-scale mr-2"></i> DB Governance &amp; Documents Lawyer
                    </a>
            </li>
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard.dashboard')) ? 'active-sidebar' : '' }}">
                <a href="{{ route('agentic_ai.compliance') }}" class="text-color font-weight-500">
                        <i class="fas fa-user-check mr-2"></i> DB Preliminary Assessor
                    </a>
            </li>
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard/step1')) ? 'active-sidebar' : '' }}">
                <a href="/databreach/events/create" class="text-color font-weight-500">
                        <i class="fas fa-clipboard-list mr-2"></i> DB Event &amp; Process Advisor
                    </a>
            </li>
        </ul>
    </li>
  

    <!-- Govern & Secure Files Section -->
    <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
        style="margin-top:12px;font-size:0.99em;border-left:4px solid #1877c2; cursor:pointer;"
        data-bs-toggle="collapse" data-bs-target="#fileMenu" aria-controls="fileMenu" aria-expanded="false">
        <span>Govern & Secure Files</span>
        <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
    </li>
    <li class="list-group-item p-0 border-0 collapse" id="fileMenu" style="background:none;">
        <ul class="list-group">
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard.visuals_dashboard')) ? 'active-sidebar' : '' }}">
                <a class="text-color font-weight-500" href="{{ route('wizard.visuals_dashboard') }}">
                    <i class="fa fa-chart-bar mr-2 text-18"></i> Risk Insights
                </a>
            </li>
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard.dashboard')) ? 'active-sidebar' : '' }}">
                <a class="text-color font-weight-500" href="{{ route('wizard.dashboard') }}">
                    <i class="fa fa-database mr-2 text-18"></i> Secured File Sources
                </a>
            </li>
            <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('wizard/step1')) ? 'active-sidebar' : '' }}">
                <a class="text-color font-weight-500" href="{{ route('wizard.step1') }}">
                    <i class="fa fa-plus-square mr-2 text-18"></i> Add File Source
                </a>
            </li>
        </ul>
    </li>


    <!-- Profile and Logout (always visible) -->
    <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('users/profile') || request()->is('users/profile/media') || request()->is('users/edit-verification') || request()->is('users/security')) ? 'active-sidebar' : '' }}">
        <a href="{{ url('users/profile') }}" class="text-color font-weight-500">
            <i class="far fa-user-circle mr-2 text-18"></i>{{trans('messages.utility.profile')}}
        </a>
    </li>
    <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4">
        <a href="{{ url('logout') }}" class="text-color font-weight-500">
            <i class="fas fa-sign-out-alt mr-2 text-18"></i>{{trans('messages.header.logout')}}
        </a>
    </li>
</ul>
      </div>
    </div>
</div>


