
<!--================ Header Menu Area start =================-->
<?php
    $lang = Session::get('language');
?>

@push('css')
<style> @media (min-width: 992px){ #csai-toggle { display: none !important; } } </style>
<style>
  #mainMenuBar.menu-hidden {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s;
  }
  #mainMenuBar {
    transition: opacity 0.5s;
  }

  /* Nested dropdown (desktop) */
  .dropdown-submenu {
    position: relative;
  }
  .dropdown-submenu > .dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -6px;
    margin-left: .2rem;
    border-radius: 0.5rem;
    min-width: 240px;
    display: none;
  }
  .dropdown-submenu .dropdown-menu.show {
    display: block;
  }
</style>
@endpush

<input type="hidden" id="front_date_format_type" value="{{ Session::get('front_date_format_type')}}">
<header id="mainMenuBar" class="header_area animated fadeIn">
  <div class="main_menu">
    <nav class="navbar navbar-expand-lg navbar-light">
      <div class="container-fluid container-fluid-90">

        <!-- Logo -->
        <a class="navbar-brand logo_h" aria-label="logo" href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;">
          <span style="display:inline-block;height:38px;width:38px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" width="38" height="38" aria-label="mochanai.com logo">
              <defs>
                <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
                  <stop offset="0%" stop-color="#1877c2"/>
                  <stop offset="100%" stop-color="#36d399"/>
                </linearGradient>
              </defs>
              <path d="M48 8C67 17 84 20 84 37c0 38-27.5 50-36 54C39.5 87 12 75 12 37c0-17 17-20 36-29z"
                fill="#fff" stroke="url(#g1)" stroke-width="5"/>
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
              <title>mochanai.com Security + AI Logo</title>
            </svg>
          </span>
          <span style="font-size:2.2em;font-weight:700;color:#1877c2;letter-spacing:-0.04em;">
            
            mochan<strong style="color:#36d399;">ai.com</strong>
          </span>
        </a>
        
        @auth
        <div class="d-none d-lg-inline-flex align-items-center ms-2"> 
          <span class="text-muted px-2">|</span> 
          <a href="#" class="text-decoration-none fw-semibold" data-bs-toggle="offcanvas" data-bs-target="#csai-offcanvas" aria-controls="csai-offcanvas" title="Chat with mochanai">
            <i class="fas fa-comments me-1"></i> 
            Chat </a> 
        </div> 
        @endauth

        <!-- Mobile Trigger (Left modal) -->
        <a href="#" aria-label="navbar" class="navbar-toggler" data-bs-toggle="modal" data-bs-target="#left_modal">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </a>

        <div class="collapse navbar-collapse offset" id="navbarSupportedContent">
          <div class="nav navbar-nav menu_nav justify-content-end">

            @if(!Auth::check())
              <div class="nav-item">
                <a class="nav-link" href="{{ url('signup') }}" aria-label="signup">{{ trans('messages.sign_up.sign_up') }}</a>
              </div>
              <div class="nav-item">
                <a class="nav-link" href="{{ url('login') }}" aria-label="login">{{ trans('messages.header.login') }}</a>
              </div>
            @else
              <!-- Overview -->
              <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="overviewDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Overview
                </a>
                <ul class="dropdown-menu" aria-labelledby="overviewDropdown" style="min-width:250px;">
                  <li>
                    <a class="dropdown-item" href="{{ route('wizard.index') }}">
                      <i class="fas fa-tachometer-alt mr-2"></i> Risk overview
                    </a>
                  </li>
                </ul>
              </div>

              <!-- AI agents -->
              <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="agentsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  AI agents
                </a>
                <ul class="dropdown-menu" aria-labelledby="agentsDropdown" style="min-width:260px;">
                  <li>
                    <a class="dropdown-item" href="/agentic-ai/chatorchestrator">
                      <i class="fas fa-robot mr-2"></i> Orchestrator
                    </a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#" aria-expanded="false" aria-haspopup="true">
                      <i class="fas fa-shield-alt mr-2"></i> Breach response
                    </a>
                    <ul class="dropdown-menu" aria-label="Breach response sub-menu">
                      <li>
                        <a class="dropdown-item" href="/databreach/events/create">
                          <i class="fas fa-bolt mr-2" style="color:#e8ba3a;"></i> New incident
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('agentic_ai.compliance') }}">
                          <i class="fas fa-user-check mr-2" style="color:#36d399;"></i> Triage &amp; assessment
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('agenticai.docs_agent.index') }}">
                          <i class="fas fa-balance-scale mr-2" style="color:#1877c2;"></i> Governance &amp; docs
                        </a>
                      </li>
                    </ul>
                  </li>
                </ul>
              </div>

              <!-- Data & files -->
              <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="dataFilesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Data &amp; files
                </a>
                <ul class="dropdown-menu" aria-labelledby="dataFilesDropdown" style="min-width:270px;">
                  <!-- Visualizations first (to mirror sidenav order) -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#" aria-expanded="false" aria-haspopup="true">
                      <i class="fa fa-chart-pie mr-2"></i> Visualizations
                    </a>
                    <ul class="dropdown-menu" aria-label="Visualizations sub-menu">
                      <li><a class="dropdown-item" href="{{ route('wizard.file_graph_table') }}"><i class="fa fa-table mr-2"></i> Table explorer</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_pyramid') }}"><i class="fa fa-layer-group mr-2"></i> Risk pyramid</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_treemap') }}"><i class="fa fa-th-large mr-2"></i> Treemap</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_sunburst') }}"><i class="fa fa-circle-notch mr-2"></i> Sunburst</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_stacked_bar') }}"><i class="fa fa-chart-bar mr-2"></i> Stacked bar</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_heatmap') }}"><i class="fa fa-border-all mr-2"></i> Heatmap</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_bubble') }}"><i class="fa fa-circle mr-2"></i> Bubble</a></li>
                      <li><a class="dropdown-item" href="{{ route('wizard.filesummary_sankey') }}"><i class="fa fa-project-diagram mr-2"></i> Sankey</a></li>
                    </ul>
                  </li>
                  <li>
                    <a class="dropdown-item" href="{{ route('wizard.files.list') }}">
                      <i class="fa fa-list mr-2"></i> Explore files
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="{{ route('filesummary.duplicates') }}">
                      <i class="fa fa-clone mr-2"></i> Duplicates
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Setup -->
              <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="setupDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Setup
                </a>
                <ul class="dropdown-menu" aria-labelledby="setupDropdown" style="min-width:240px;">
                  <li>
                    <a class="dropdown-item" href="{{ route('wizard.dashboard') }}">
                      <i class="fa fa-database mr-2"></i> Data sources
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="{{ route('wizard.step1') }}">
                      <i class="fa fa-plus-square mr-2"></i> Add source
                    </a>
                  </li>
                </ul>
              </div>

              <!-- User -->
              <div class="d-flex">
                <div class="nav-item mr-0">
                  <img src="{{ Auth::user()->profile_src }}" class="head_avatar" alt="{{ Auth::user()->first_name }}">
                </div>
                <div class="nav-item ml-0 pl-0">
                  <div class="dropdown">
                    <a href="javascript:void(0)" class="nav-link dropdown-toggle text-15" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-label="user-profile" aria-haspopup="true" aria-expanded="false">
                      {{ Auth::user()->first_name }}
                    </a>
                    <div class="dropdown-menu drop-down-menu-left p-0 drop-width text-14" aria-labelledby="dropdownMenuButton">
                      <a class="font-weight-700 list-group-item vbg-default-hover border-0" href="{{ url('users/profile') }}" aria-label="profile">{{ trans('messages.utility.profile') }}</a>
                      <a class="font-weight-700 list-group-item vbg-default-hover border-0" href="{{ url('logout') }}" aria-label="logout">{{ trans('messages.header.logout') }}</a>
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

<!-- Mobile Side Modal -->
<div class="modal left fade" id="left_modal" tabindex="-1" role="dialog" aria-labelledby="left_modal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header border-0 secondary-bg">
        @if(Auth::check())
          <div class="row justify-content-center">
            <div><img src="{{ Auth::user()->profile_src }}" class="head_avatar" alt="{{ Auth::user()->first_name }}"></div>
            <div><p class="text-white mt-4">{{ Auth::user()->first_name }}</p></div>
          </div>
        @endif

        <button type="button" class="btn-close text-reset" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <ul class="mobile-side">
          @if(Auth::check())
            <!-- Overview (Mobile) -->
            <li style="background:#f6fbff;border-radius:7px;margin-bottom:8px; padding-left:4px;">
              <a href="#" onclick="event.preventDefault();toggleMobileSection('Overview');" style="font-weight:700;font-size:1.08em;display:flex;align-items:center;">
                <i class="fas fa-tachometer-alt mr-2" style="color:#1877c2;"></i>
                Overview
                <span id="mobileMenuArrowOverview" style="margin-left:auto;font-size:1.18em;transition:.2s;">&#9662;</span>
              </a>
              <ul id="mobileSubMenuOverview" style="display:none;margin:0;padding:0 3px 8px 28px;">
                <li><a href="{{ route('wizard.index') }}" class="mobile-subitem" style="font-size:.98em;"><i class="fas fa-chart-line mr-1" style="color:#1877c2;"></i> Risk overview</a></li>
              </ul>
            </li>

            <!-- AI agents (Mobile) -->
            <li style="background:#f4fafd;border-radius:7px;margin-bottom:8px; padding-left:4px;">
              <a href="#" onclick="event.preventDefault();toggleMobileSection('Agents');" style="font-weight:700;font-size:1.08em;display:flex;align-items:center;">
                <i class="fas fa-robot mr-2" style="color:#1877c2;"></i>
                AI agents
                <span id="mobileMenuArrowAgents" style="margin-left:auto;font-size:1.18em;transition:.2s;">&#9662;</span>
              </a>
              <ul id="mobileSubMenuAgents" style="display:none;margin:0;padding:0 3px 8px 28px;">
                <li><a href="/agentic-ai/chatorchestrator" class="mobile-subitem" style="font-size:.98em;"><i class="fas fa-robot mr-1" style="color:#1877c2;"></i> Orchestrator</a></li>
                <li style="margin-top:4px;">
                  <a href="#" onclick="event.preventDefault();toggleMobileSection('Breach');" style="font-weight:700;font-size:1.0em;display:flex;align-items:center;">
                    <i class="fas fa-shield-alt mr-2" style="color:#36d399;"></i> Breach response
                    <span id="mobileMenuArrowBreach" style="margin-left:auto;font-size:1.08em;transition:.2s;">&#9662;</span>
                  </a>
                  <ul id="mobileSubMenuBreach" style="display:none;margin:0;padding:2px 3px 5px 26px;">
                    <li><a href="/databreach/events/create" class="mobile-subitem" style="font-size:.96em;"><i class="fas fa-bolt mr-1" style="color:#e8ba3a;"></i> New incident</a></li>
                    <li><a href="{{ route('agentic_ai.compliance') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fas fa-user-check mr-1" style="color:#36d399;"></i> Triage &amp; assessment</a></li>
                    <li><a href="{{ route('agenticai.docs_agent.index') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fas fa-balance-scale mr-1" style="color:#1877c2;"></i> Governance &amp; docs</a></li>
                  </ul>
                </li>
              </ul>
            </li>

            <!-- Data & files (Mobile) -->
            <li style="background:#f3faf9;border-radius:8px;margin-bottom:10px; padding-left:5px;">
              <a href="#" onclick="event.preventDefault();toggleMobileSection('DataFiles');" style="font-weight:700; font-size:1.1em;display:flex;align-items:center;">
                <i class="fa fa-database mr-2" style="color:#1877c2;"></i> Data &amp; files
                <span id="mobileMenuArrowDataFiles" style="margin-left:auto;font-size:1.22em;transition:.2s;" aria-label="expand">&#9662;</span>
              </a>
              <ul id="mobileSubMenuDataFiles" style="display:none; margin:0; padding-left:19px;">
                <li style="margin-top:4px;">
                  <a href="#" onclick="event.preventDefault();toggleMobileSection('Visualizations');" style="font-weight:700;font-size:1.0em;display:flex;align-items:center;">
                    <i class="fa fa-chart-pie mr-2" style="color:#e8ba3a;"></i> Visualizations
                    <span id="mobileMenuArrowVisualizations" style="margin-left:auto;font-size:1.08em;transition:.2s;">&#9662;</span>
                  </a>
                  <ul id="mobileSubMenuVisualizations" style="display:none;margin:0;padding:2px 3px 5px 26px;">
                    <li><a href="{{ route('wizard.file_graph_table') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-table mr-1"></i> Table explorer</a></li>
                    <li><a href="{{ route('wizard.filesummary_pyramid') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-layer-group mr-1"></i> Risk pyramid</a></li>
                    <li><a href="{{ route('wizard.filesummary_treemap') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-th-large mr-1"></i> Treemap</a></li>
                    <li><a href="{{ route('wizard.filesummary_sunburst') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-circle-notch mr-1"></i> Sunburst</a></li>
                    <li><a href="{{ route('wizard.filesummary_stacked_bar') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-chart-bar mr-1"></i> Stacked bar</a></li>
                    <li><a href="{{ route('wizard.filesummary_heatmap') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-border-all mr-1"></i> Heatmap</a></li>
                    <li><a href="{{ route('wizard.filesummary_bubble') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-circle mr-1"></i> Bubble</a></li>
                    <li><a href="{{ route('wizard.filesummary_sankey') }}" class="mobile-subitem" style="font-size:.96em;"><i class="fa fa-project-diagram mr-1"></i> Sankey</a></li>
                  </ul>
                </li>
                <li><a href="{{ route('wizard.files.list') }}" class="mobile-subitem" style="font-size:.99em;"><i class="fa fa-list mr-1" style="color:#1877c2;"></i> Explore files</a></li>
                <li><a href="{{ route('filesummary.duplicates') }}" class="mobile-subitem" style="font-size:.99em;"><i class="fa fa-clone mr-1" style="color:#36d399;"></i> Duplicates</a></li>
              </ul>
            </li>

            <!-- Setup (Mobile) -->
            <li style="background:#f4fbf7;border-radius:8px;margin-bottom:10px; padding-left:5px;">
              <a href="#" onclick="event.preventDefault();toggleMobileSection('Setup');" style="font-weight:700; font-size:1.1em;display:flex;align-items:center;">
                <i class="fa fa-cog mr-2" style="color:#36d399;"></i> Setup
                <span id="mobileMenuArrowSetup" style="margin-left:auto;font-size:1.22em;transition:.2s;" aria-label="expand">&#9662;</span>
              </a>
              <ul id="mobileSubMenuSetup" style="display:none; margin:0; padding-left:19px;">
                <li><a href="{{ route('wizard.dashboard') }}" class="mobile-subitem" style="font-size:.99em;"><i class="fa fa-database mr-1" style="color:#1877c2;"></i> Data sources</a></li>
                <li><a href="{{ route('wizard.step1') }}" class="mobile-subitem" style="font-size:.99em;"><i class="fa fa-plus-square mr-1" style="color:#36d399;"></i> Add source</a></li>
              </ul>
            </li>

            <li><a href="{{ url('users/profile') }}"><i class="far fa-user-circle mr-3"></i>{{ trans('messages.utility.profile') }}</a></li>
            <li><a href="{{ url('logout') }}"><i class="fas fa-sign-out-alt mr-3"></i>{{ trans('messages.header.logout') }}</a></li>

            @else
              <li><a href="{{ url('signup') }}"><i class="fas fa-stream mr-3"></i>{{ trans('messages.sign_up.sign_up') }}</a></li>
              <li><a href="{{ url('login') }}"><i class="far fa-list-alt mr-3"></i>{{ trans('messages.header.login') }}</a></li>
            @endif

            @if(Auth::check())
              @if(Request::segment(1) != 'help')
                <a href="{{ route('wizard.step1') }}">
                  <button class="btn vbtn-outline-success text-14 font-weight-700 pl-5 pr-5 pt-3 pb-3">
                    Add data source
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
document.addEventListener("DOMContentLoaded", function() {
  var header = document.getElementById('mainMenuBar');
  if (header) {
    var hideTimeout;
    function showMenu() { header.classList.remove('menu-hidden'); }
    function hideMenu() { header.classList.add('menu-hidden'); }

    setTimeout(hideMenu, 2500);
    document.body.addEventListener('mousemove', function(e) {
      if (e.clientY < 64) showMenu();
    });
    header.addEventListener('mouseenter', showMenu);
    header.addEventListener('mouseleave', function() {
      clearTimeout(hideTimeout);
      hideTimeout = setTimeout(hideMenu, 2800);
    });
  }

  // Desktop: toggle nested dropdown submenus (Breach response, Visualizations)
  (function initNestedDropdowns() {
    var submenuToggles = document.querySelectorAll('.dropdown-submenu > .submenu-toggle');
    submenuToggles.forEach(function(toggle) {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var submenu = this.nextElementSibling;
        if (!submenu) return;

        // Close other open submenus under the same parent menu
        var parentMenu = this.closest('.dropdown-menu');
        if (parentMenu) {
          parentMenu.querySelectorAll('.dropdown-submenu .dropdown-menu.show').forEach(function(opened) {
            if (opened !== submenu) opened.classList.remove('show');
          });
          parentMenu.querySelectorAll('.dropdown-submenu > .submenu-toggle[aria-expanded="true"]').forEach(function(openedToggle) {
            if (openedToggle !== toggle) openedToggle.setAttribute('aria-expanded', 'false');
          });
        }

        // Toggle current submenu
        var willOpen = !submenu.classList.contains('show');
        submenu.classList.toggle('show', willOpen);
        this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });

    // When any top-level dropdown hides, close nested submenus inside it
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(topToggle) {
      topToggle.addEventListener('hide.bs.dropdown', function() {
        var container = this.closest('.dropdown');
        if (!container) return;
        container.querySelectorAll('.dropdown-submenu .dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
        container.querySelectorAll('.dropdown-submenu > .submenu-toggle[aria-expanded="true"]').forEach(function(t) { t.setAttribute('aria-expanded', 'false'); });
      });
    });
  })();

  // Mobile: generic toggle for collapsible sections
  window.toggleMobileSection = function(id) {
    var menu = document.getElementById('mobileSubMenu' + id);
    var arrow = document.getElementById('mobileMenuArrow' + id);
    if (!menu || !arrow) return;
    var open = (menu.style.display === 'block');
    menu.style.display = open ? 'none' : 'block';
    arrow.style.transform = open ? '' : 'rotate(180deg)';
  };
});
</script>
@endpush