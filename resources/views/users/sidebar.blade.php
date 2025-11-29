<div class="col-lg-2 p-0 border-right d-none d-lg-block overflow-hidden mt-m-30">
    <div class="main-panel mt-5 h-100">
        <div class="mt-2">
            @php
                $isOverviewMenuActive =
                    request()->routeIs('wizard.visuals_dashboard') ||
                    request()->routeIs('agentic_ai.auditor');

                  
          		$isAiAgentsMenuActive =
                    request()->is('agentic-ai/chatorchestrator') ||
                    request()->routeIs('agenticai.docs_agent.index') ||
                    request()->routeIs('agentic_ai.compliance.breach.index');

                $isBreachSubMenuActive =
                    request()->routeIs('agenticai.docs_agent.index') ||
                    request()->routeIs('agentic_ai.compliance.breach.index');

                $isDataFilesMenuActive =
                    request()->routeIs('wizard.files.*') ||
                    request()->routeIs('filesummary.duplicates') ||
                    request()->routeIs('filesummary.duplicates.group') ||
                    request()->routeIs('wizard.file_graph_table') ||
                    request()->routeIs('wizard.filesummary_pyramid') ||
                    request()->routeIs('wizard.filesummary_treemap') ||
                    request()->routeIs('wizard.filesummary_sunburst') ||
                    request()->routeIs('wizard.filesummary_stacked_bar') ||
                    request()->routeIs('wizard.filesummary_heatmap') ||
                    request()->routeIs('wizard.filesummary_bubble') ||
                    request()->routeIs('wizard.filesummary_sankey');

                $isVisualizationsSubMenuActive =
                    request()->routeIs('wizard.file_graph_table') ||
                    request()->routeIs('wizard.filesummary_pyramid') ||
                    request()->routeIs('wizard.filesummary_treemap') ||
                    request()->routeIs('wizard.filesummary_sunburst') ||
                    request()->routeIs('wizard.filesummary_stacked_bar') ||
                    request()->routeIs('wizard.filesummary_heatmap') ||
                    request()->routeIs('wizard.filesummary_bubble') ||
                    request()->routeIs('wizard.filesummary_sankey');

                $isSetupMenuActive =
                    request()->routeIs('wizard.dashboard') ||
                    request()->routeIs('wizard.step1');
            @endphp

            <ul class="list-group list-group-flush pl-3">

                <!-- Overview -->
                <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
                    style="font-size:0.99em;border-left:4px solid #36d399; cursor:pointer;"
                    data-bs-toggle="collapse" data-bs-target="#overviewMenu" aria-controls="overviewMenu" aria-expanded="{{ $isOverviewMenuActive ? 'true' : 'false' }}">
                    <span>Overview</span>
                    <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
                </li>
                <li class="list-group-item p-0 border-0 collapse {{ $isOverviewMenuActive ? 'show' : '' }}" id="overviewMenu" style="background:none;">
                    <ul class="list-group">
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.index') ? 'active-sidebar' : '' }}">
                            <a class="text-color font-weight-500" href="{{ route('wizard.index') }}">
                                <i class="fas fa-tachometer-alt mr-2 text-18"></i> Risk overview
                            </a>
                        </li>
                    </ul>
                </li>

     
                <!-- AI agents -->
                <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
                    style="margin-top:12px;font-size:0.99em;border-left:4px solid #1877c2; cursor:pointer;"
                    data-bs-toggle="collapse" data-bs-target="#aiAgentsMenu" aria-controls="aiAgentsMenu" aria-expanded="{{ $isAiAgentsMenuActive ? 'true' : 'false' }}">
                    <span>AI agents</span>
                    <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
                </li>
                <li class="list-group-item p-0 border-0 collapse {{ $isAiAgentsMenuActive ? 'show' : '' }}" id="aiAgentsMenu" style="background:none;">
                    <ul class="list-group">
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->is('agentic-ai/chatorchestrator') ? 'active-sidebar' : '' }}">
                            <a href="/agentic-ai/chatorchestrator" class="text-color font-weight-500">
                                <i class="fas fa-robot mr-2 text-18"></i> Orchestrator
                            </a>
                        </li>
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('agentic_ai.compliance.breach.index') ? 'active-sidebar' : '' }}">
                            <a href="{{ route('agentic_ai.compliance.breach.index') }}" class="text-color font-weight-500">
                                <i class="fas fa-shield-alt mr-2 text-18"></i> Breach response
                            </a>
                        </li>
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('agenticai.docs_agent.index') ? 'active-sidebar' : '' }}">
                            <a href="{{ route('agenticai.docs_agent.index') }}" class="text-color font-weight-500">
                                <i class="fas fa-balance-scale mr-2 text-18"></i> Governance &amp; docs
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Data & files -->
                <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
                    style="margin-top:12px;font-size:0.99em;border-left:4px solid #1877c2; cursor:pointer;"
                    data-bs-toggle="collapse" data-bs-target="#dataFilesMenu" aria-controls="dataFilesMenu" aria-expanded="{{ $isDataFilesMenuActive ? 'true' : 'false' }}">
                    <span>Data &amp; files</span>
                    <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
                </li>
                <li class="list-group-item p-0 border-0 collapse {{ $isDataFilesMenuActive ? 'show' : '' }}" id="dataFilesMenu" style="background:none;">
                    <ul class="list-group">
                        <!-- Visualizations (now the first sub-menu) -->
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 d-flex justify-content-between align-items-center"
                            style="cursor:pointer;"
                            data-bs-toggle="collapse" data-bs-target="#visualizationsMenu" aria-controls="visualizationsMenu" aria-expanded="{{ $isVisualizationsSubMenuActive ? 'true' : 'false' }}">
                            <span class="text-color font-weight-500">
                                <i class="fa fa-chart-pie mr-2 text-18"></i> Visualizations
                            </span>
                            <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
                        </li>
                        <li class="list-group-item p-0 border-0 collapse {{ $isVisualizationsSubMenuActive ? 'show' : '' }}" id="visualizationsMenu" style="background:none;">
                            <ul class="list-group">
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.file_graph_table') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.file_graph_table') }}">
                                        <i class="fa fa-table mr-2 text-18"></i> Table explorer
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_pyramid') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_pyramid') }}">
                                        <i class="fa fa-layer-group mr-2 text-18"></i> Risk pyramid
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_treemap') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_treemap') }}">
                                        <i class="fa fa-th-large mr-2 text-18"></i> Treemap
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_sunburst') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_sunburst') }}">
                                        <i class="fa fa-circle-notch mr-2 text-18"></i> Sunburst
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_stacked_bar') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_stacked_bar') }}">
                                        <i class="fa fa-chart-bar mr-2 text-18"></i> Stacked bar
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_heatmap') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_heatmap') }}">
                                        <i class="fa fa-border-all mr-2 text-18"></i> Heatmap
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_bubble') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_bubble') }}">
                                        <i class="fa fa-circle mr-2 text-18"></i> Bubble
                                    </a>
                                </li>
                                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.filesummary_sankey') ? 'active-sidebar' : '' }}">
                                    <a class="text-color font-weight-500" href="{{ route('wizard.filesummary_sankey') }}">
                                        <i class="fa fa-project-diagram mr-2 text-18"></i> Sankey
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!-- Then the Explore Files and Duplicates menu items -->
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.files.*') ? 'active-sidebar' : '' }}">
                            <a class="text-color font-weight-500" href="{{ route('wizard.files.list') }}">
                                <i class="fa fa-list mr-2 text-18"></i> Explore files
                            </a>
                        </li>
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->routeIs('filesummary.duplicates') || request()->routeIs('filesummary.duplicates.group')) ? 'active-sidebar' : '' }}">
                            <a class="text-color font-weight-500" href="{{ route('filesummary.duplicates') }}">
                                <i class="fa fa-clone mr-2 text-18"></i> Duplicates
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Setup -->
                <li class="list-group-item font-weight-bold text-uppercase text-muted bg-light d-flex justify-content-between align-items-center"
                    style="margin-top:12px;font-size:0.99em;border-left:4px solid #1877c2; cursor:pointer;"
                    data-bs-toggle="collapse" data-bs-target="#setupMenu" aria-controls="setupMenu" aria-expanded="{{ $isSetupMenuActive ? 'true' : 'false' }}">
                    <span>Setup</span>
                    <span class="collapse-arrow"><i class="fa fa-angle-down"></i></span>
                </li>
                <li class="list-group-item p-0 border-0 collapse {{ $isSetupMenuActive ? 'show' : '' }}" id="setupMenu" style="background:none;">
                    <ul class="list-group">
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.essentialSetup') ? 'active-sidebar' : '' }}">
                            <a class="text-color font-weight-500" href="{{ route('wizard.essentialSetup') }}">
                                <i class="fa fa-cogs mr-2 text-18"></i> Essential Setup
                            </a>
                        </li>
                        <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ request()->routeIs('wizard.dashboard') ? 'active-sidebar' : '' }}">
                            <a class="text-color font-weight-500" href="{{ route('wizard.dashboard') }}">
                                <i class="fa fa-database mr-2 text-18"></i> Data Source Setup
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Account (always visible) -->
                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4 {{ (request()->is('users/profile') || request()->is('users/profile/media') || request()->is('users/edit-verification') || request()->is('users/security')) ? 'active-sidebar' : '' }}">
                    <a href="{{ url('users/profile') }}" class="text-color font-weight-500">
                        <i class="far fa-user-circle mr-2 text-18"></i>{{ trans('messages.utility.profile') }}
                    </a>
                </li>
                <li class="list-group-item vbg-default-hover pl-25 border-0 text-15 p-4">
                    <a href="{{ url('logout') }}" class="text-color font-weight-500">
                        <i class="fas fa-sign-out-alt mr-2 text-18"></i>{{ trans('messages.header.logout') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>