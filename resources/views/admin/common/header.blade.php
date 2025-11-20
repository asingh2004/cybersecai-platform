<header class="main-header">
    <a href="{{ url('admin/dashboard') }}" class="logo text-decoration-none">
        @if (!empty($site_name))
            <span class="logo-mini"><b>{{ $site_name }}</b></span>
            <span class="logo-lg"><b>{{ $site_name }}</b></span>
        @endif
    </a>

    <nav class="navbar navbar-expand navbar-light bg-light header_controls">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-outline-secondary me-2" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>

            <ul class="navbar-nav ms-auto align-items-center">
                @if(Auth::guard('admin')->check())
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ Auth::guard('admin')->user()->profile_src }}" class="rounded-circle" alt="User Image" width="30" height="30">
                        <span class="ms-2">{{ ucfirst(Auth::guard('admin')->user()->username) }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown" style="min-width: 200px;">
                        <li class="px-3 py-2 text-center">
                            <img src="{{ Auth::guard('admin')->user()->profile_src }}" class="rounded-circle mb-2" alt="User Image" width="65" height="65">
                            <div class="fw-bold">
                                {{ ucfirst(Auth::guard('admin')->user()->username) }}
                            </div>
                            <div class="small text-muted">Member since {{ date('M, Y', strtotime(Auth::guard('admin')->user()->created_at)) }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="{{ url('admin/profile') }}" class="dropdown-item">
                                <i class="fa fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('admin/logout') }}" class="dropdown-item">
                                <i class="fa fa-sign-out me-2"></i>Sign out
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
            </ul>
        </div>
    </nav>
</header>

<!-- Flash container remains as before, include your alert code below: -->
<div class="flash-container">
    @if(Session::has('message'))
        <div class="alert {{ Session::get('alert-class') }} text-center mb-0" role="alert">
            {{ Session::get('message') }}
            <a href="#" class="pull-right alert-close" data-bs-dismiss="alert">&times;</a>
        </div>
    @endif

    <div class="alert alert-success text-center mb-0 d-none" id="success_message_div" role="alert">
        <a href="#" class="pull-right alert-close" data-bs-dismiss="alert">&times;</a>
        <p id="success_message"></p>
    </div>

    <div class="alert alert-danger text-center mb-0 d-none" id="error_message_div" role="alert">
        <p><a href="#" class="pull-right alert-close" data-bs-dismiss="alert">&times;</a></p>
        <p id="error_message"></p>
    </div>
</div>