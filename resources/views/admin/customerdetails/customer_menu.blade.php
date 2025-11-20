<div class="box">
	<div class="panel-body">
		<div class="nav-tabs-custom">
			<ul class="cus nav" role="tablist">
				<li  class="{{ isset($customer_edit_tab) ? $customer_edit_tab : '' }} nav-item">
					<a class="nav-link" class="nav-link active" href='{{url("admin/edit-customer")}}/{{@$user->id}}' >Edit Customer</a>
				</li>
				<li  class="{{ isset($properties_tab) ? $properties_tab : '' }} nav-item">
					<a class="nav-link" href='{{url("admin/customer/properties")}}/{{@$user->id}}' >Listings</a>
				</li>
				<li class="{{ isset($bookings_tab) ? $bookings_tab : '' }} nav-item">
					<a class="nav-link" href='{{url("admin/customer/bookings")}}/{{@$user->id}}'>Bookings</a>
				</li>
				<li class="{{ isset($payouts_tab) ? $payouts_tab : '' }} nav-item">
					<a class="nav-link" href='{{url("admin/customer/payouts")}}/{{@$user->id}}'>Payouts</a>
				</li>
				<li class="{{ isset($payment_methods_tab) ? $payment_methods_tab : '' }} nav-item">
					<a class="nav-link" href='{{url("admin/customer/payment-methods")}}/{{@$user->id}}' >Payment Methods</a>
				</li>
				<li class="{{ isset($wallet) ? $wallet : '' }} nav-item">
					<a class="nav-link" href='{{ url("admin/customer/wallet") }}/{{@$user->id}}' >Wallet</a>
				</li>
			</ul>
			<div class="clearfix"></div>
		</div>
	</div>
</div> 
<h3>{{ @$user->first_name." ".@$user->last_name }}</h3>