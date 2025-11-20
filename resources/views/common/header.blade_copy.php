<!--================ Header Menu Area start =================-->
<?php
    $lang = Session::get('language');
?>

@push('css')
<style>
#mainMenuBar.menu-hidden {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s;
}
#mainMenuBar {
    transition: opacity 0.5s;
}
  
  /* Visually indent for "submenu" look */
.db-manager-group {
    padding-left: 1.6em;
    border-left: 2px solid #e5e9ee;
    margin-left: 8px;
    margin-bottom: 2px;
    background: #f8fbfd;
    border-radius: 0.3em;
}
.db-manager-toggle {
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.db-manager-arrow {
    margin-left: 6px;
    transition: transform 0.23s;
}
.db-manager-arrow.opened {
    transform: rotate(90deg);
}
  

/* Show submenu on hover (bootstrap-like) */
/*.dropdown-submenu:hover>.dropdown-menu {
    display: block;
}*/
.dropdown-submenu>.dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -6px;
    margin-left: .1rem;
    border-radius: 0.5rem;
    min-width: 240px;
    position: absolute;
    display: none;
    z-index: 1050;
}
.dropdown-submenu {
    position: relative;
}
  
</style>
@endpush

<input type="hidden" id="front_date_format_type" value="{{ Session::get('front_date_format_type')}}">
<header id="mainMenuBar" class="header_area  animated fadeIn">
    <div class="main_menu">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid container-fluid-90">

              
              <!-- Logo SVG -->
              <a class="navbar-brand logo_h" aria-label="logo" href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;">
    {{-- Inline SVG Logo (shield + AI/network nodes, modern blue/green) --}}
    <span style="display:inline-block;height:38px;width:38px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="38" height="38" aria-label="CyberSecAI.io logo">
        <defs>
          <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0%" stop-color="#1877c2"/>
            <stop offset="100%" stop-color="#36d399"/>
          </linearGradient>
        </defs>
        <!-- Shield outline -->
        <path d="M48 8C67 17 84 20 84 37c0 38-27.5 50-36 54C39.5 87 12 75 12 37c0-17 17-20 36-29z"
          fill="#fff" stroke="url(#g1)" stroke-width="5"/>
        <!-- Brain/network nodes and links -->
        <g stroke="#1877c2" stroke-width="1.7">
          <circle cx="48" cy="27" r="5" fill="#36d399"/>
          <circle cx="33" cy="40" r="3.5" fill="#95dbfa"/>
          <circle cx="63" cy="40" r="3.5" fill="#95dbfa"/>
          <circle cx="41" cy="56" r="3.5" fill="#5ad7ba"/>
          <circle cx="55" cy="56" r="3.5" fill="#5ad7ba"/>
          <line x1="48" y1="27" x2="33" y2="40"/>
          <line x1="48" y1="27" x2="63" y2="40"/>
          <line x1="33" y1="40" x2="41" y2="56"/>
          <line x1="63" y1="40" x2="55" y2="56"/>
          <line x1="41" y1="56" x2="55" y2="56"/>
        </g>
        <title>CyberSecAI.io Security + AI Logo</title>
      </svg>
    </span>
    {{-- Wordmark --}}
    <span style="font-size:2.2em;font-weight:700;color:#1877c2;letter-spacing:-0.04em;">
      cybersec<strong style="color:#36d399;">ai.io</strong>
    </span>
</a> 
              
              
              
				<!-- Trigger Button -->
				<a href="#" aria-label="navbar" class="navbar-toggler" data-bs-toggle="modal" data-bs-target="#left_modal">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
                </a>

                <div class="collapse navbar-collapse offset" id="navbarSupportedContent">
                    <div class="nav navbar-nav menu_nav justify-content-end">

                        @if(!Auth::check())

                            <div class="nav-item">
                                <a class="nav-link" href="{{ url('signup') }}" aria-label="signup">{{trans('messages.sign_up.sign_up')}}</a>
                            </div>
                            <div class="nav-item">
                                <a class="nav-link" href="{{ url('login') }}" aria-label="login">{{trans('messages.header.login')}}</a>
                            </div>
                        @else
                      
                      		<div class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" id="personaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        Data AI Agents
    </a>
    <ul class="dropdown-menu" aria-labelledby="personaDropdown" style="min-width:250px;">

      
      	<!-- ====== Fixed Agentic AI Compliance Crew Main Item ====== -->
<li>
    <div class="dropdown-item db-manager-toggle"
         onclick="event.stopPropagation();toggleDBManagerMenu('compliance');"
         aria-expanded="false"
         id="dbManagerDropdownCompliance">
        <span>
            <svg style="width:1.2em;height:1.2em;vertical-align:-0.18em;fill:#1877c2;margin-right:7px;" viewBox="0 0 16 16">
                <ellipse cx="8" cy="8" rx="7" ry="7" fill="#eaf9ff"/>
                <ellipse cx="8" cy="10" rx="4" ry="2" fill="#1877c2"/>
                <rect x="6" y="4" width="4" height="5" rx="1.7" fill="#36d399"/>
            </svg>
            Agentic AI Compliance Crew
        </span>
        <span>
            <i class="fas fa-caret-right db-manager-arrow" id="dbMenuArrowCompliance"></i>
        </span>
    </div>
    <div id="dbManagerSubmenuCompliance"
         class="db-manager-group"
         style="display:none;">
        <a class="dropdown-item" href="/agentic-ai/chatorchestrator">
            <i class="fas fa-balance-scale mr-1" style="color:#1877c2;"></i>
            Multi-Persona Agent
        </a>
        <a class="dropdown-item" href="{{ route('agentic_ai.agents') }}">
            <i class="fas fa-user-check mr-1" style="color:#36d399;"></i>
            My AI Agents Outputs
        </a>
        <a class="dropdown-item" href="{{ route('agentic_ai.auditor') }}">
            <i class="fas fa-clipboard-list mr-1" style="color:#e8ba3a;"></i>
            Board Report
        </a>
    </div>
</li>

<li><hr class="dropdown-divider"></li>

<!-- ====== Fixed Data Breach (DB) AI Agents Main Item ====== -->
<li>
    <div class="dropdown-item db-manager-toggle"
         onclick="event.stopPropagation();toggleDBManagerMenu('db');"
         aria-expanded="false"
         id="dbManagerDropdownDBAI">
        <span>
            <svg style="width:1.2em;height:1.2em;vertical-align:-0.18em;fill:#1877c2;margin-right:7px;" viewBox="0 0 16 16">
                <ellipse cx="8" cy="8" rx="7" ry="7" fill="#eaf9ff"/>
                <ellipse cx="8" cy="10" rx="4" ry="2" fill="#1877c2"/>
                <rect x="6" y="4" width="4" height="5" rx="1.7" fill="#36d399"/>
            </svg>
            Data Breach (DB) AI Agents
        </span>
        <span>
            <i class="fas fa-caret-right db-manager-arrow" id="dbMenuArrowDB"></i>
        </span>
    </div>
    <div id="dbManagerSubmenuDB"
         class="db-manager-group"
         style="display:none;">
        <a class="dropdown-item" href="{{ route('agenticai.docs_agent.index') }}">
            <i class="fas fa-balance-scale mr-1" style="color:#1877c2;"></i>
            DB Governance &amp; Documents Lawyer
        </a>
        <a class="dropdown-item" href="{{ route('agentic_ai.compliance') }}">
            <i class="fas fa-user-check mr-1" style="color:#36d399;"></i>
            DB Preliminary Assessor
        </a>
        <a class="dropdown-item" href="/databreach/events/create">
            <i class="fas fa-clipboard-list mr-1" style="color:#e8ba3a;"></i>
            DB Event &amp; Process Advisor
        </a>
    </div>
</li>
        <!-- === End Data Breach (DB) Manager Menu === -->

    </ul>
</div>
                      
                      
                   <div class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" id="manageFilesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
       Govern & Secure Files
    </a>
    <ul class="dropdown-menu" aria-labelledby="manageFilesDropdown">
      	  <li>
            <a class="dropdown-item" href="{{ route('wizard.visuals_dashboard') }}">
                <i class="fa fa-chart-bar mr-2"></i> Risk Insights
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('wizard.dashboard') }}">
                <i class="fa fa-database mr-2"></i> Secured File Sources
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('wizard.step1') }}">
                <i class="fa fa-plus-circle mr-2"></i> Add File Source
            </a>
        </li>
      
    </ul>
</div>
                      
                      
                      
                      <div class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" id="customAIBotDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        Custom AI Bot
    </a>
    <ul class="dropdown-menu" aria-labelledby="customAIBotDropdown">
        <li>
                               <a class="dropdown-item" href="{{ route('openai.create') }}">
    <span style="font-size:1.19em;vertical-align:-0.2em;">🤖</span> Create a Bot
</a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('user.assistants') }}">
    <span style="font-size:1.14em;vertical-align:-0.2em;">🧠</span> My Bots
</a>
        
        </li>
    </ul>
</div>
                      

                      
   
 

                            <div class="d-flex">
                                <div>
                                    <div class="nav-item mr-0">
                                    <img src="{{Auth::user()->profile_src}}" class="head_avatar" alt="{{Auth::user()->first_name}}">
                                </div>
                                </div>
                                <div>
                                <div class="nav-item ml-0 pl-0">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="nav-link dropdown-toggle text-15" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-label="user-profile" aria-haspopup="true" aria-expanded="false">
                                            {{Auth::user()->first_name}}
                                        </a>
                                        <div class="dropdown-menu drop-down-menu-left p-0 drop-width text-14" aria-labelledby="dropdownMenuButton">
     		
                                            <a class="font-weight-700 list-group-item vbg-default-hover border-0 " href="{{ url('users/profile') }}" aria-label="profile">{{trans('messages.utility.profile')}}</a>
                                            <a class="font-weight-700 list-group-item vbg-default-hover border-0 " href="{{ url('logout') }}" aria-label="logout">{{trans('messages.header.logout')}}</a>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </nav>
    </div>

</header>

<!-- Modal Window -->
<div class="modal left fade" id="left_modal" tabindex="-1" role="dialog" aria-labelledby="left_modal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 secondary-bg">
                @if(Auth::check())
                    <div class="row justify-content-center">
                        <div>
                            <img src="{{Auth::user()->profile_src}}" class="head_avatar" alt="{{Auth::user()->first_name}}">
                        </div>

                        <div>
                            <p  class="text-white mt-4"> {{Auth::user()->first_name}}</p>
                        </div>
                    </div>
                @endif

                <button type="button" class="close text-28" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
			</div>

            <div class="modal-body">
                <ul class="mobile-side">
                    @if(Auth::check())
                        <!--<li><a href="{{ url('exec_dashboard') }}"><i class="fa fa-bar-chart mr-3" style="font-size:18px"></i>Executive Insight</a></li>-->
                        <!--<li><a href="{{ url('dashboard') }}"><i class="fa fa-tachometer mr-3" style="font-size:18px"></i>{{trans('messages.header.dashboard')}}</a></li>-->
                  		<!-- Agentic AI Compliance Crew - mobile -->
<!-- Agentic AI Compliance Crew (Mobile) -->
<li class="mobile-persona-dropdown" style="background:#f4fafd;border-radius:7px;margin-bottom:8px; padding-left:4px;">
    <a href="#" onclick="event.preventDefault();toggleMobilePersonaMenu('compliance');" style="font-weight:700;font-size:1.08em;display:flex;align-items:center;">
        <i class="fas fa-user-shield mr-2" style="color:#36d399;"></i>
        Agentic AI Compliance Crew
        <span id="personaMobileMenuArrowCompliance" style="margin-left:auto;font-size:1.18em;transition:.2s;">&#9662;</span>
    </a>
    <ul id="personaMobileSubMenuCompliance" style="display:none;margin:0;padding:0 3px 5px 28px;">
        <li>
            <a href="/agentic-ai/chatorchestrator" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-balance-scale mr-1" style="color:#1877c2;"></i>
                Multi-Persona Agent
            </a>
        </li>
        <li>
            <a href="{{ route('agentic_ai.agents') }}" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-check-circle mr-1" style="color:#36d399;"></i>
                My AI Agents Outputs
            </a>
        </li>
        <li>
            <a href="{{ route('agentic_ai.auditor') }}" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-clipboard-list mr-1" style="color:#e8ba3a;"></i>
                Board Report
            </a>
        </li>
    </ul>
</li>
<!-- Data Breach (DB) AI Agents (Mobile) -->
<li class="mobile-persona-dropdown" style="background:#f4fafd;border-radius:7px;margin-bottom:8px; padding-left:4px;">
    <a href="#" onclick="event.preventDefault();toggleMobilePersonaMenu('db');" style="font-weight:700;font-size:1.08em;display:flex;align-items:center;">
        <i class="fas fa-database mr-2" style="color:#1877c2;"></i>
        Data Breach (DB) AI Agents
        <span id="personaMobileMenuArrowDB" style="margin-left:auto;font-size:1.18em;transition:.2s;">&#9662;</span>
    </a>
    <ul id="personaMobileSubMenuDB" style="display:none;margin:0;padding:0 3px 5px 28px;">
        <li>
            <a href="{{ route('agenticai.docs_agent.index') }}" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-balance-scale mr-1" style="color:#1877c2;"></i>
                DB Governance &amp; Documents Lawyer
            </a>
        </li>
        <li>
            <a href="{{ route('agentic_ai.compliance') }}" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-user-check mr-1" style="color:#36d399;"></i>
                DB Preliminary Assessor
            </a>
        </li>
        <li>
            <a href="/databreach/events/create" class="mobile-subitem" style="font-size:.98em;">
                <i class="fas fa-clipboard-list mr-1" style="color:#e8ba3a;"></i>
                DB Event &amp; Process Advisor
            </a>
        </li>
    </ul>
</li>

                  
                  
                  
                  
                  <li class="mobile-manage-dropdown" style="background:#f3faf9;border-radius:8px;margin-bottom:10px; padding-left:5px;">
    <a href="#" onclick="event.preventDefault();toggleManageMenu();" style="font-weight:700; font-size:1.1em;display:flex;align-items:center;">
        <i class="fa fa-database mr-2" style="color:#1877c2;"></i> Govern & Secure Files
        <span id="manageMenuArrow" style="margin-left:auto;font-size:1.22em;transition:.2s;" aria-label="expand">&#9662;</span>
    </a>
    <ul id="manageMobileSubMenu" style="display:none; margin:0; padding-left:19px;">
        
      <li>
            <a href="{{ route('wizard.visuals_dashboard') }}" class="mobile-subitem" style="font-size:.99em;">
                <i class="fa fa-chart-bar mr-1" style="color:#e8ba3a;"></i>
                Risk Insights
            </a>
        </li>
      <li>
            <a href="{{ route('wizard.dashboard') }}" class="mobile-subitem" style="font-size:.99em;">
                <i class="fa fa-database mr-1" style="color:#1877c2;"></i>
                Secured File Sources
            </a>
        </li>
        <li>
            <a href="{{ route('wizard.step1') }}" class="mobile-subitem" style="font-size:.99em;">
                <i class="fa fa-plus-square mr-1" style="color:#36d399;"></i>
                Add File Source
            </a>
        </li>
        
    </ul>
</li>
              
                  
                  
                  
                  
                  <li class="mobile-ai-dropdown" style="background:#f3faf9;border-radius:8px;margin-bottom:10px; padding-left:5px;">
    <a href="#" onclick="event.preventDefault();toggleAIBotMenu();" style="font-weight:700; font-size:1.1em;display:flex;align-items:center;">
        <i class="fa fa-robot mr-2" style="color:#36d399;"></i> Custom AI Bot
        <span id="aiBotMenuArrow" style="margin-left:auto;font-size:1.2em;transition:.2s;" aria-label="expand">&#9662;</span>
    </a>
    <ul id="aiBotMobileSubMenu" style="display:none; margin:0 0 0 18px;">
        <li>
            <a href="{{ route('openai.create') }}" class="mobile-subitem" style="font-size:.99em;">
                <i class="fa fa-plus-square mr-1" style="color:#1877c2;"></i>
                Create a Bot
            </a>
        </li>
        <li>
            <a href="{{ route('user.assistants') }}" class="mobile-subitem" style="font-size:.99em;">
                <i class="fa fa-brain mr-1" style="color:#e8ba3a;"></i>
                My Bots
            </a>
        </li>
    </ul>
</li>
                  
     
                        <li><a href="{{ url('users/profile') }}"><i class="far fa-user-circle mr-3"></i>{{trans('messages.utility.profile')}}</a></li>
                        <li><a href="{{ url('logout') }}"><i class="fas fa-sign-out-alt mr-3"></i>{{trans('messages.header.logout')}}</a></li>
                    @else
                        <li><a href="{{ url('signup') }}"><i class="fas fa-stream mr-3"></i>{{trans('messages.sign_up.sign_up')}}</a></li>
                        <li><a href="{{ url('login') }}"><i class="far fa-list-alt mr-3"></i>{{trans('messages.header.login')}}</a></li>

                    @endif

                  	@if(Auth::check())
                    	@if(Request::segment(1) != 'help')
                        	<a href="{{ route('wizard.step1') }}">
                            	<button class="btn vbtn-outline-success text-14 font-weight-700 pl-5 pr-5 pt-3 pb-3">
                                    Add New Data Storage
                            	</button>
                        	</a>
                    	@endif
                  	@endif
                </ul>
            </div>
        </div>
    </div>
</div>
<!--================Header Menu Area =================-->

@push('scripts')
<script>

  
  
  
document.addEventListener("DOMContentLoaded", function(){
    var header = document.getElementById('mainMenuBar');
    if (!header) return;
    function showMenu() { header.classList.remove('menu-hidden'); }
    function hideMenu() { header.classList.add('menu-hidden'); }

    // 1. Hide header after 2.5 seconds
    setTimeout(hideMenu, 2500);

    // 2. Show menu when mouse moves to top 64px of viewport
    document.body.addEventListener('mousemove', function(e){
        if (e.clientY < 64) showMenu();
        // (optional: hide again if they move away after some time)
    });

    // 3. Also show menu on header hover (for accessibility)
    header.addEventListener('mouseenter', showMenu);

    // 4. Hide again if mouse leaves header for 3 seconds
    header.addEventListener('mouseleave', function(){
        setTimeout(hideMenu, 2800);
    });
});
  
  
  function togglePersonaMenu() {
    var menu = document.getElementById('personaMobileSubMenu');
    var arrow = document.getElementById('personaMenuArrow');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
        if (arrow) arrow.style.transform = "rotate(180deg)";
    } else {
        menu.style.display = 'none';
        if (arrow) arrow.style.transform = "";
    }
}
  
  function toggleManageMenu() {
    var menu = document.getElementById('manageMobileSubMenu');
    var arrow = document.getElementById('manageMenuArrow');
    if (!menu) return;
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
        if (arrow) arrow.style.transform = "rotate(180deg)";
    } else {
        menu.style.display = 'none';
        if (arrow) arrow.style.transform = "";
    }
}
  
  function toggleAIBotMenu() {
    var menu = document.getElementById('aiBotMobileSubMenu');
    var arrow = document.getElementById('aiBotMenuArrow');
    if (!menu) return;
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
        if (arrow) arrow.style.transform = "rotate(180deg)";
    } else {
        menu.style.display = 'none';
        if (arrow) arrow.style.transform = "";
    }
}
function toggleDBManagerMenu(menu) {
    // menu = 'compliance' or 'db'
    var submenu, arrow;

    if(menu === 'compliance') {
        submenu = document.getElementById('dbManagerSubmenuCompliance');
        arrow = document.getElementById('dbMenuArrowCompliance');
    } else if(menu === 'db') {
        submenu = document.getElementById('dbManagerSubmenuDB');
        arrow = document.getElementById('dbMenuArrowDB');
    } else {
        return;
    }

    if (!submenu) return;
    // Only close the other submenu if it's open
    var otherSubmenu = (menu === 'compliance')
        ? document.getElementById('dbManagerSubmenuDB')
        : document.getElementById('dbManagerSubmenuCompliance');
    var otherArrow = (menu === 'compliance')
        ? document.getElementById('dbMenuArrowDB')
        : document.getElementById('dbMenuArrowCompliance');
    if (otherSubmenu && otherSubmenu !== submenu) {
        otherSubmenu.style.display = "none";
        if (otherArrow) otherArrow.classList.remove('opened');
    }

    if (submenu.style.display === "none" || submenu.style.display === "") {
        submenu.style.display = "block";
        if (arrow) arrow.classList.add('opened');
    } else {
        submenu.style.display = "none";
        if (arrow) arrow.classList.remove('opened');
    }
}
  
  function toggleMobilePersonaMenu(menu) {
    // Ensure values: menu = 'compliance' or 'db'
    var openId = (menu === 'compliance') ? 'Compliance' : 'DB';

    var submenu = document.getElementById('personaMobileSubMenu' + openId);
    var arrow = document.getElementById('personaMobileMenuArrow' + openId);

    // Other menu (so we can close it)
    var otherId = (menu === 'compliance') ? 'DB' : 'Compliance';
    var otherSubmenu = document.getElementById('personaMobileSubMenu' + otherId);
    var otherArrow = document.getElementById('personaMobileMenuArrow' + otherId);

    if (!submenu || !arrow) return;

    // Close other menu if open
    if (otherSubmenu && otherSubmenu.style.display === 'block') {
        otherSubmenu.style.display = 'none';
        if (otherArrow) otherArrow.style.transform = '';
    }

    // Toggle this menu
    if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        submenu.style.display = 'none';
        arrow.style.transform = '';
    }
}

// Optional: hide if clicking outside in mobile modal
document.addEventListener('click', function(event) {
    var complianceSub = document.getElementById('personaMobileSubMenuCompliance');
    var dbSub = document.getElementById('personaMobileSubMenuDB');
    var complianceArrow = document.getElementById('personaMobileMenuArrowCompliance');
    var dbArrow = document.getElementById('personaMobileMenuArrowDB');

    var isClickInCompliance = event.target.closest('.mobile-persona-dropdown:nth-of-type(1)');
    var isClickInDB = event.target.closest('.mobile-persona-dropdown:nth-of-type(2)');

    // Only close if a submenu is open and click was *outside* both dropdowns
    if (!isClickInCompliance && complianceSub && complianceSub.style.display === 'block') {
        complianceSub.style.display = 'none';
        if (complianceArrow) complianceArrow.style.transform = '';
    }
    if (!isClickInDB && dbSub && dbSub.style.display === 'block') {
        dbSub.style.display = 'none';
        if (dbArrow) dbArrow.style.transform = '';
    }
}, true); // useCapture so dropdown click doesn't interfere


// Optional: Hide submenus if user clicks elsewhere
document.addEventListener('click', function(event) {
    ['Compliance', 'DB'].forEach(function(type){
        var submenu = document.getElementById('dbManagerSubmenu'+type);
        var arrow = document.getElementById('dbMenuArrow'+type);
        var toggle = document.getElementById('dbManagerDropdown'+(type === 'Compliance' ? 'Compliance' : 'DBAI'));
        if (!submenu) return;
        if ((!toggle.contains(event.target)) && (!submenu.contains(event.target))) {
            submenu.style.display = "none";
            if (arrow) arrow.classList.remove('opened');
        }
    });
});

// Optional: Hide submenu if user clicks elsewhere or changes dropdown
document.addEventListener('click', function(event) {
    var submenu = document.getElementById('dbManagerSubmenu');
    var arrow = document.getElementById('dbMenuArrow');
    var toggle = document.getElementById('dbManagerDropdown');
    if (!submenu) return;
    if ((!toggle.contains(event.target)) && (!submenu.contains(event.target))) {
        submenu.style.display = "none";
        if (arrow) arrow.classList.remove('opened');
    }
});
  
</script>
@endpush
