<aside class="main-sidebar">
    <section class="sidebar">
	<ul class="sidebar-menu">
        <li class="{{ (Route::current()->uri() == 'admin/dashboard') ? 'active' : ''  }}"><a href="{{ url('admin/dashboard') }}"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
		@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'customers'))
			<li class="{{ (Route::current()->uri() == 'admin/customers') || (Route::current()->uri() == 'admin/add-customer') || (Route::current()->uri() == 'admin/edit-customer/{id}') || (Route::current()->uri() == 'admin/customer/properties/{id}')  || (Route::current()->uri() == 'admin/customer/bookings/{id}') || (Route::current()->uri() == 'admin/customer/payouts/{id}')  || (Route::current()->uri() == 'admin/customer/payment-methods/{id}') || (Route::current()->uri() == 'admin/customer/wallet/{id}')  ? 'active' : '' }} }}"><a href="{{ url('admin/customers') }}"><i class="fa fa-users"></i><span>Users</span></a></li>
		@endif



		@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'manage_pages'))
			<li class="{{ (Route::current()->uri() == 'admin/pages') || (Route::current()->uri() == 'admin/add-page') || (Route::current()->uri() == 'admin/edit-page/{id}') ? 'active' : ''  }}"><a href="{{ url('admin/pages') }}"><i class="fa fa-newspaper-o"></i><span>Static Pages</span></a></li>
		@endif




		@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'manage_admin'))
			<li class="{{ (Route::current()->uri() == 'admin/admin-users') || (Route::current()->uri() == 'admin/add-admin') || (Route::current()->uri() == 'admin/edit-admin/{id}') ? 'active' : ''  }}">
				<a href="{{ url('admin/admin-users') }}">
				<i class="fa fa-user-plus"></i> <span>Users</span>
				</a>
			</li>
		@endif




		@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'manage_email_template'))
			<li class="{{ (Route::current()->uri() == 'admin/email-template/{id}') ? 'active' : ''  }}"><a href="{{ url('admin/email-template/1') }}"><i class="fa fa-envelope"></i><span>Email Templates</span></a></li>
		@endif

		<!-- Email Template Ends -->  
		@if(Helpers::has_permission(Auth::guard('admin')->user()->id, 'general_setting'))
			<li class="{{ (Request::segment(2) == 'settings') ? 'active' : ''  }}"><a href="{{ url('admin/settings') }}"><i class="fa fa-gears"></i><span>Settings</span></a></li>
		@endif
    </ul>
    </section>
</aside>