<div class="box box-info box_info">
	<div class="panel-body">
		<h4 class="all_settings">Manage Settings</h4>
		<ul class="nav flex-column" role="tablist">
			@if(Permission::has_permission(Auth::guard('admin')->user()->id, 'general_setting'))
				<li class="{{ (Route::current()->uri() == 'admin/settings') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings') }}" data-group="profile">General</a>
				</li>
			@endif

			@if(Permission::has_permission(Auth::guard('admin')->user()->id, 'preference'))
				<li class="{{ (Route::current()->uri() == 'admin/settings/preferences') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings/preferences') }}" data-group="profile">Preferences</a>
				</li>
			@endif



			@if(Permission::has_permission(Auth::guard('admin')->user()->id, 'manage_banners'))
				<li class="{{ (Route::current()->uri() == 'admin/settings/banners') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings/banners') }}" data-group="profile">Banners</a>
				</li>
			@endif




	

			@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'email_settings'))
				<li class="{{ (Route::current()->uri() == 'admin/settings/email') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings/email') }}">Email Settings</a>
				</li>
			@endif

	


			@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'api_informations'))
				<li class="{{ (Route::current()->uri() == 'admin/settings/api-informations') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings/api-informations') }}" data-group="api_informations">Api Credentials</a>
				</li>
			@endif

		

			@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'manage_roles'))
				<li class="{{ (Route::current()->uri() == 'admin/settings/roles' || Route::current()->uri() == 'admin/permissions' || Route::current()->uri() == 'admin/settings/add-role' || Route::current()->uri() == 'admin/settings/edit-role/{id}') ? 'active' : ''  }} nav-item">
					<a class="nav-link" href="{{ url('admin/settings/roles') }}"><span>Roles & Permissions</span></a>
				</li>
			@endif

			@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'database_backup'))
			<li class="{{ (Route::current()->uri() == 'admin/backup') ? 'active' : ''  }} nav-item">
				<a class="nav-link" href="{{ url('admin/settings/backup') }}"><span>Database Backups</span></a>
			</li>
			@endif
		</ul>
	</div>
</div>
