@extends('template')
@section('main')
<div class="margin-top-85">
	<div class="row m-0">
		{{-- sidebar start--}}
		@include('users.sidebar')
		{{--sidebar end--}}
		<div class="col-lg-10 p-0">
			<div class="container-fluid min-height">
				<div class="row mt-4">
					<div class="col-md-4">
						<div class="card card-default p-3 mt-3">
							<div class="card-body">
								<p class="text-center font-weight-bold m-0"><i class="far fa-building mr-2 text-16 align-middle badge-dark rounded-circle p-3 vbadge-success"></i> <strong>Portfolio Summary</strong></p>
								<p class="text-center font-weight-bold m-0">
								
									{{ $domain_email }} portfolio has {{ $cnt_accounts }} user accounts</p></a>
							
								</p>
								<p class="text-center font-weight-bold m-0">Total Facilities: {{ $acct_facilities_cnt }}</p>
								<p class="text-center font-weight-bold m-0">Total Listings: {{ $acct_listings_cnt }}</p>
								<!--<a href="{{ url('facilities') }}"><p class="text-center font-weight-bold m-0">Associated Accounts:{{ $cnt_accounts }}</p></a> -->

								<!--<div id="myChart" style="width:100%;"></div>-->
							</div>
						</div>
					</div>


				
				<div class="row mb-5">
					<!-- Residential Respite Facts Column -->

					<div class="col-lg-7 mb-4 mt-5">
						<!-- Illustrations -->
						<div class="card card-default h-100">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fas fa-chart-line" style="font-size:48px;color:red;"></i>Residential Respite Fact Sheet - FY 2020/2021</h6>
							</div>

							<div class="card-body text-16 p-0">
								<div class="panel-footer">
									<div class="panel">
										<div class="panel-body" class="p-0">
											<div class="row">
												<div class="table-responsive">
													<table class="table table-striped table-hover table-header">

											
															<tbody id="transaction-table-body1">
										
																	<tr>
																		<td class="text-left">67,775 people received residential respite care, of whom 39,404 (approximately 58.1 per cent) were later admitted to permanent care.
																		</td>
																	</tr>

																	<tr>
																		<td class="text-left">The number of residential respite days used in 2020–21 was 2.4 million, an increase of 102,000 days from 2019–20. On average, each recipient received 1.2 episodes of residential respite care during 2020–21, and their average length of stay per episode was 28.6 days.
																		</td>
																	</tr>

																	<tr>
																		<td class="text-left">In 2020–21, the Australian Government provided subsidies and supplements totalling $458.0 million to service providers who delivered residential respite care. From October 2022, the new residential respite funding model will increase respite funding by $441.4 million (over 3 years) and align it to permanent residential care funding to ensure that both are  indexed/priced using the same model, ensuring ongoing equivalence. 
																		</td>
																	</tr>

																	<tr>
																		<td class="text-left">In 2020–21 there were 2,613 residential aged care homes which provided residential respite services.
																		</td>
																	</tr>
																
															</tbody>
													</table>
												
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-md-4">
						<div class="card card-default p-3 mt-3">
							<div class="card-body">
								<p class="text-center font-weight-bold m-0"><i class="fa fa-address-card mr-2 text-16 align-middle badge-dark rounded-circle p-3 vbadge-success"></i><strong>Number of Respite Seekers</strong></p>
									<div id="myRespiteDemand" style="width:100%; max-width:500px; height:300px;"></div>
							</div>
						</div>
				</div>



				<div class="col-md-4">
						<div class="card card-default p-3 mt-3">
							<div class="card-body">
								<p class="text-center font-weight-bold m-0"><i class="fa fa-hospital-o mr-2 text-16 align-middle badge-dark rounded-circle p-3 vbadge-success"></i><strong>High Care Respite Days Booked</strong></p>
									<div id="myRespiteHighCare" style="width:100%; max-width:500px; height:300px;"></div>
							</div>
						</div>
				</div>

				<div class="col-md-4">
						<div class="card card-default p-3 mt-3">
							<div class="card-body">
								<p class="text-center font-weight-bold m-0"><i class="fa fa fa-usd mr-2 text-16 align-middle badge-dark rounded-circle p-3 vbadge-success"></i><strong>Low Care Respite Days Booked</strong></p>
									<div id="myRespiteLowCare" style="width:100%; max-width:500px; height:300px;"></div>
							</div>
						</div>
				</div>

				<div class="col-md-4">
						<div class="card card-default p-3 mt-3">
							<div class="card-body">
								<p class="text-center font-weight-bold m-0"><i class="fa fa-usd mr-2 text-16 align-middle badge-dark rounded-circle p-3 vbadge-success"></i><strong>Residential Respite Service Providers</strong></p>
									<div id="myRespiteSP" style="width:100%; max-width:500px; height:300px;"></div>
							</div>
						</div>
				</div>


				<div class="row mb-5">
					<!-- Portfolio Column -->

					<div class="col-lg-7 mb-4 mt-5">
						<!-- Illustrations -->
						<div class="card card-default h-100">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fas fa-exchange-alt mr-2"></i>Portfolio View</h6>
							</div>

							<div class="card-body text-16 p-0">
								<div class="panel-footer">
									<div class="panel">
										<div class="panel-body" class="p-0">
											<div class="row">
												<div class="table-responsive">
													<table class="table table-striped table-hover table-header">

														@if($acct_facilities_cnt>0)
															<thead>
																<tr class="bg-secondary text-white">
																	<th class="text-center">Account/Email</th>
																	<th class="text-center">Name</th>
																	<th class="text-center">MAC Facilities</th>
																	<th class="text-center">Facilities Subscribed</th>
																</tr>
															</thead>
														@endif
															<tbody id="transaction-table-body1">
										
																@forelse($accounts_facilities_arr as $afa)
																	<tr>
																		<td class="text-left">{{ $afa->email }}</td>
																		<td class="text-left">{{ $afa->first_name }}</td>
																		<td class="text-center">{{ $afa->MAC_facilities }}</td>
																		

																		<td class="text-center">{{ $afa->facilities_subscribed }}</td>
																	</tr>
																@empty

																<div class="row jutify-content-center w-100 p-4 mt-4">
																	<div class="text-center w-100">
																	<p class="text-center">{{trans('messages.listing_description.no')}} {{trans('messages.account_sidenav.transaction_history')}}.</p>
																	</div>
																</div>
																@endforelse
															</tbody>
													</table>
												
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>






				<div class="row mb-5">
					<!-- Facility & Listings Column -->

					<div class="col-lg-7 mb-4 mt-5">
						<!-- Illustrations -->
						<div class="card card-default h-100">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fas fa-exchange-alt mr-2"></i>Facilities & Associated Listings</h6>
							</div>

							<div class="card-body text-16 p-0">
								<div class="panel-footer">
									<div class="panel">
										<div class="panel-body" class="p-0">
											<div class="row">
												<div class="table-responsive">
													<table class="table table-striped table-hover table-header">

														@if($acct_facilities_cnt>0)
															<thead>
																<tr class="bg-secondary text-white">
																	<th class="text-center">Facility Address</th>
																	<th class="text-center">Listings</th>
									
																</tr>
															</thead>
														@endif
															<tbody id="transaction-table-body1">
										
																@forelse($acct_facilities_listings_all as $afla)
																	<tr>
																		<td class="text-left">{{ $afla->address_line_1 }}</td>
																		<td class="text-left">{{ $afla->name }}</td>
																		

																	</tr>
																@empty

																<div class="row jutify-content-center w-100 p-4 mt-4">
																	<div class="text-center w-100">
																	<p class="text-center">{{trans('messages.listing_description.no')}} {{trans('messages.account_sidenav.transaction_history')}}.</p>
																	</div>
																</div>
																@endforelse
															</tbody>
													</table>
												
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>




					<div class="col-lg-5 mb-4 mt-5">
						<!-- Project Card Example -->
						<div class="card card-default">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fa fa-bookmark  mr-1" aria-hidden="true"></i> Accepted Bookings</h6>
							</div>
							<div class="card-body">
								<div class="widget">
									<ul>
										@forelse($bookings as $booking)
										@if($loop->index < 12)
										<li>
											<div class="sidebar-thumb">
												<a href="{{ url('/') }}/properties/{{ $booking->properties->slug}}"><img class="animated rollIn" src="{{ $booking->properties->cover_photo}} " alt="coverphoto" /></a>

											</div>

											<div>
												<h4 class="animated bounceInRight text-16 font-weight-700">
													<a href="{{ url('/') }}/properties/{{ $booking->properties->slug}}">{{ $booking->properties->name}}
													</a><br/>

												</h4>
											</div>

											<div class="d-flex justify-content-between">
												<div>
													<div>
														<span class="text-14 font-weight-400">
															<i class="fa fa-calendar" aria-hidden="true"></i> {{ $booking->date_range}}</span>
														<div class="sidebar-meta">
															<a href="{{ url('/') }}/users/show/{{ $booking->user_id}}" class="text-14 font-weight-400">{{ $booking->users->full_name}}</a>
														</div>
													</div>

												</div>

												<div class="align-self-center pr-4">
													<span class="badge vbadge-success text-14 mt-3 p-2 {{ $booking->status}}">{{ $booking->status}}</span>
												</div>
											</div>
										</li>
										@endif
										@empty
										<div class="row jutify-content-center w-100 p-4 mt-4">
											<div class="text-center w-100">
											<p class="text-center">{{trans('messages.booking_my.no_booking')}}</p>
											</div>
										</div>
										@endforelse
									</ul>
								</div>

								@if($bookings->count()>12)
									<div class="more-btn text-right">
										<a class="btn vbtn-outline-success text-14 font-weight-700 p-0 mt-2 pl-3 pr-3" href="{{ url('/') }}/my-bookings">
											<p class="p-2 mb-0">{{trans('messages.property_single.more')}}</p>
										</a>
									</div>
								@endif
							</div>


						</div>
					</div>

					<!-- Bookings Declined Column -->
					<div class="col-lg-5 mb-4 mt-5">
						<!-- Project Card Example -->
						<div class="card card-default">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fa fa-bookmark  mr-1" aria-hidden="true"></i> Booking Request Declined</h6>
							</div>
							<div class="card-body">
								<div class="widget">
									<ul>
										@forelse($bookings_declined as $booking_dec)
										@if($loop->index < 12)
										<li>
											<div class="sidebar-thumb">
												<a href="{{ url('/') }}/properties/{{ $booking_dec->properties->slug}}"><img class="animated rollIn" src="{{ $booking_dec->properties->cover_photo}} " alt="coverphoto" /></a>

											</div>

											<div>
												<h4 class="animated bounceInRight text-16 font-weight-700">
													<a href="{{ url('/') }}/properties/{{ $booking_dec->properties->slug}}">{{ $booking_dec->properties->name}}
													</a><br/>

												</h4>
											</div>

											<div class="d-flex justify-content-between">
												<div>
													<div>
														<span class="text-14 font-weight-400">
															<i class="fa fa-calendar" aria-hidden="true"></i> {{ $booking_dec->date_range}}</span>
														<div class="sidebar-meta">
															<a href="{{ url('/') }}/users/show/{{ $booking_dec->user_id}}" class="text-14 font-weight-400">{{ $booking_dec->users->full_name}}</a>
														</div>
													</div>

												</div>

												<div class="align-self-center pr-4">
													<span class="badge vbadge-success text-14 mt-3 p-2 {{ $booking_dec->status}}">{{ $booking_dec->status}}</span>
												</div>
											</div>
										</li>
										@endif
										@empty
										<div class="row jutify-content-center w-100 p-4 mt-4">
											<div class="text-center w-100">
											<p class="text-center">{{trans('messages.booking_my.no_booking')}}</p>
											</div>
										</div>
										@endforelse
									</ul>
								</div>

								@if($bookings_declined->count()>12)
									<div class="more-btn text-right">
										<a class="btn vbtn-outline-success text-14 font-weight-700 p-0 mt-2 pl-3 pr-3" href="{{ url('/') }}/my-bookings">
											<p class="p-2 mb-0">{{trans('messages.property_single.more')}}</p>
										</a>
									</div>
								@endif
							</div>


						</div>
					</div>




					<!-- Bookings Pending Column -->
					<div class="col-lg-5 mb-4 mt-5">
						<!-- Project Card Example -->
						<div class="card card-default">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fa fa-bookmark  mr-1" aria-hidden="true"></i> Bookings Ignored by Provider</h6>
							</div>
							<div class="card-body">
								<div class="widget">
									<ul>
										@forelse($bookings_pending as $booking_pen)
										@if($loop->index < 12)
										<li>
											<div class="sidebar-thumb">
												<a href="{{ url('/') }}/properties/{{ $booking_pen->properties->slug}}"><img class="animated rollIn" src="{{ $booking_pen->properties->cover_photo}} " alt="coverphoto" /></a>

											</div>

											<div>
												<h4 class="animated bounceInRight text-16 font-weight-700">
													<a href="{{ url('/') }}/properties/{{ $booking_pen->properties->slug}}">{{ $booking_pen->properties->name}}
													</a><br/>

												</h4>
											</div>

											<div class="d-flex justify-content-between">
												<div>
													<div>
														<span class="text-14 font-weight-400">
															<i class="fa fa-calendar" aria-hidden="true"></i> {{ $booking_pen->date_range}}</span>
														<div class="sidebar-meta">
															<a href="{{ url('/') }}/users/show/{{ $booking_pen->user_id}}" class="text-14 font-weight-400">{{ $booking_pen->users->full_name}}</a>
														</div>
													</div>

												</div>

												<div class="align-self-center pr-4">
													<span class="badge vbadge-success text-14 mt-3 p-2 {{ $booking_pen->status}}">{{ $booking_pen->status}}</span>
												</div>
											</div>
										</li>
										@endif
										@empty
										<div class="row jutify-content-center w-100 p-4 mt-4">
											<div class="text-center w-100">
											<p class="text-center">{{trans('messages.booking_my.no_booking')}}</p>
											</div>
										</div>
										@endforelse
									</ul>
								</div>

								@if($bookings_pending->count()>12)
									<div class="more-btn text-right">
										<a class="btn vbtn-outline-success text-14 font-weight-700 p-0 mt-2 pl-3 pr-3" href="{{ url('/') }}/my-bookings">
											<p class="p-2 mb-0">{{trans('messages.property_single.more')}}</p>
										</a>
									</div>
								@endif
							</div>


						</div>
					</div>


					<!-- Bookings Processing Column -->
					<div class="col-lg-5 mb-4 mt-5">
						<!-- Project Card Example -->
						<div class="card card-default">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fa fa-bookmark  mr-1" aria-hidden="true"></i> Bookings accepted by Provider but not committed by Consumers!</h6>
							</div>
							<div class="card-body">
								<div class="widget">
									<ul>
										@forelse($bookings_processing as $booking_proc)
										@if($loop->index < 12)
										<li>
											<div class="sidebar-thumb">
												<a href="{{ url('/') }}/properties/{{ $booking_proc->properties->slug}}"><img class="animated rollIn" src="{{ $booking_proc->properties->cover_photo}} " alt="coverphoto" /></a>

											</div>

											<div>
												<h4 class="animated bounceInRight text-16 font-weight-700">
													<a href="{{ url('/') }}/properties/{{ $booking_proc->properties->slug}}">{{ $booking_proc->properties->name}}
													</a><br/>

												</h4>
											</div>

											<div class="d-flex justify-content-between">
												<div>
													<div>
														<span class="text-14 font-weight-400">
															<i class="fa fa-calendar" aria-hidden="true"></i> {{ $booking_proc->date_range}}</span>
														<div class="sidebar-meta">
															<a href="{{ url('/') }}/users/show/{{ $booking_proc->user_id}}" class="text-14 font-weight-400">{{ $booking_proc->users->full_name}}</a>
														</div>
													</div>

												</div>

												<div class="align-self-center pr-4">
													<span class="badge vbadge-success text-14 mt-3 p-2 {{ $booking_proc->status}}">{{ $booking_proc->status}}</span>
												</div>
											</div>
										</li>
										@endif
										@empty
										<div class="row jutify-content-center w-100 p-4 mt-4">
											<div class="text-center w-100">
											<p class="text-center">{{trans('messages.booking_my.no_booking')}}</p>
											</div>
										</div>
										@endforelse
									</ul>
								</div>

								@if($bookings_pending->count()>12)
									<div class="more-btn text-right">
										<a class="btn vbtn-outline-success text-14 font-weight-700 p-0 mt-2 pl-3 pr-3" href="{{ url('/') }}/my-bookings">
											<p class="p-2 mb-0">{{trans('messages.property_single.more')}}</p>
										</a>
									</div>
								@endif
							</div>


						</div>
					</div>

					<!-- Bookings Expired Column -->
					<div class="col-lg-5 mb-4 mt-5">
						<!-- Project Card Example -->
						<div class="card card-default">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-700 text-18"><i class="fa fa-bookmark  mr-1" aria-hidden="true"></i> Bookings not Actioned on Time by Provider</h6>
							</div>
							<div class="card-body">
								<div class="widget">
									<ul>
										@forelse($bookings_expired as $booking_exp)
										@if($loop->index < 12)
										<li>
											<div class="sidebar-thumb">
												<a href="{{ url('/') }}/properties/{{ $booking_exp->properties->slug}}"><img class="animated rollIn" src="{{ $booking_exp->properties->cover_photo}} " alt="coverphoto" /></a>

											</div>

											<div>
												<h4 class="animated bounceInRight text-16 font-weight-700">
													<a href="{{ url('/') }}/properties/{{ $booking_exp->properties->slug}}">{{ $booking_exp->properties->name}}
													</a><br/>

												</h4>
											</div>

											<div class="d-flex justify-content-between">
												<div>
													<div>
														<span class="text-14 font-weight-400">
															<i class="fa fa-calendar" aria-hidden="true"></i> {{ $booking_exp->date_range}}</span>
														<div class="sidebar-meta">
															<a href="{{ url('/') }}/users/show/{{ $booking_exp->user_id}}" class="text-14 font-weight-400">{{ $booking_exp->users->full_name}}</a>
														</div>
													</div>

												</div>

												<div class="align-self-center pr-4">
													<span class="badge vbadge-success text-14 mt-3 p-2 {{ $booking_exp->status}}">{{ $booking_exp->status}}</span>
												</div>
											</div>
										</li>
										@endif
										@empty
										<div class="row jutify-content-center w-100 p-4 mt-4">
											<div class="text-center w-100">
											<p class="text-center">{{trans('messages.booking_my.no_booking')}}</p>
											</div>
										</div>
										@endforelse
									</ul>
								</div>

								@if($bookings_expired->count()>12)
									<div class="more-btn text-right">
										<a class="btn vbtn-outline-success text-14 font-weight-700 p-0 mt-2 pl-3 pr-3" href="{{ url('/') }}/my-bookings">
											<p class="p-2 mb-0">{{trans('messages.property_single.more')}}</p>
										</a>
									</div>
								@endif
							</div>


						</div>
					</div>




				</div>
			</div>
		</div>
	</div>
</div>

<!-- Chart Plotting-->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {

let myArray = {!! $accounts_facilities_arr !!};


var newArr = [['Account', 'Facilities']];

for(let key in myArray) {
	vemail = myArray[key].email;
	vmac = parseInt(myArray[key].MAC_facilities, 10);
	newArr.push([vemail,vmac]);

}
//console.log(newArr);

var data = google.visualization.arrayToDataTable([
  newArr
]);



var options = {
        hAxis: {
            title: "Facilities",
            id: "Facilities",
            label: "Facilities",
            type: "number"
        },
        vAxis: {
            title: "Account",
            id: "Account",
            label: "Account",
            type: "string"
        },
        colors: ["#a52714", "#097138"]
    };

//var chart = new google.visualization.BarChart(document.getElementById('myChart'));
 // chart.draw(data, options);
}


google.charts.setOnLoadCallback(drawChart2);

function drawChart2() {
var data = google.visualization.arrayToDataTable([
  ['Facility', 'Listings'],
  ['Pennant Hills',11.76],
  ['Heritage Illawong',11.76],
  ['Chomley House',11.76],
  ['Heritage Queanbeyan',11.76],
  ['Water Gardens',5.8],
  ['Heritage Gardens',11.76],
  ['Epping Gardens',11.76],
  ['Heritage Botany',11.76],
  ['The Manor Fairfield East',11.76]
]);

//console.log(data);

var options = {
  title:'Listings by Facilities',
  is3D:true
};

//var chart = new google.visualization.PieChart(document.getElementById('myChart2'));
//  chart.draw(data, options);
}
</script>

<script>

	let myArray = {!! $accounts_facilities_arr !!};

	
	//for(let key in myArray) {
	//	console.log(myArray[key].email);
	//	console.log(myArray[key].MAC_facilities);
	//	console.log(myArray[key].email,',',parseInt(myArray[key].MAC_facilities, 10));
	//}
</script>


<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
var data = google.visualization.arrayToDataTable([
  ['State', 'RespiteSeekers'],
  ['NSW',25452],
  ['VIC',15566],
  ['QLD',12898],
  ['WA',3649],
  ['SA',8053],
  ['TAS',1535],
  ['ACT',602],
  ['NT',207]
]);

var options = {
  title:'Residential Respite Seekers by State - 2020/2021',
  is3D:true
};

var chart = new google.visualization.PieChart(document.getElementById('myRespiteDemand'));
  chart.draw(data, options);
}
</script>


<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
var data = google.visualization.arrayToDataTable([
  ['State', 'Service Providers'],
  ['NSW',870],
  ['VIC',726],
  ['QLD',462],
  ['WA',210],
  ['SA',241],
  ['TAS',68],
  ['ACT',24],
  ['NT',12]
]);

var options = {
  title:'Residential Respite Service Providers Days - 2020/2021',
  is3D:true
};

var chart = new google.visualization.PieChart(document.getElementById('myRespiteSP'));
  chart.draw(data, options);
}
</script>

<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
var data = google.visualization.arrayToDataTable([
  ['State', 'High Care Respite Seekers'],
  ['NSW',791923],
  ['VIC',299362],
  ['QLD',399678],
  ['WA',100387],
  ['SA',279634],
  ['TAS',32817],
  ['ACT',13552],
  ['NT',12140]
]);

var options = {
  title:'High Care Residential Respite Days - 2020/2021',
  is3D:true
};

var chart = new google.visualization.PieChart(document.getElementById('myRespiteHighCare'));
  chart.draw(data, options);
}
</script>


<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
var data = google.visualization.arrayToDataTable([
  ['State', 'Low Care Respite Seekers'],
  ['NSW',127632],
  ['VIC',198590],
  ['QLD',65996],
  ['WA',17912],
  ['SA',14910],
  ['TAS',10591],
  ['ACT',3849],
  ['NT',2159]
]);

var options = {
  title:'Low Care Residential Respite by State - 2020/2021',
  is3D:true
};

var chart = new google.visualization.PieChart(document.getElementById('myRespiteLowCare'));
  chart.draw(data, options);
}
</script>
@stop
