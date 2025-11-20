@extends('template')
@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 mb-5 p-0">
	<div class="row m-0">
		<div class="col-md-3 mt-4">
			<div class="row border rounded p-3">
				<div class="col-md-12 text-center">
					<img src="{{ $result->profile_src }}" title="{{ $result->first_name }}" class="img-fluid mt-2 p-5" alt="{{ $result->first_name }}" >
				</div>

				<div class="col-md-12 text-center p-0">
					@if($result->id == Auth::user()->id )
						@if((Auth::user()->users_verification->email == 'no') || (Auth::user()->users_verification->facebook == 'no') || (Auth::user()->users_verification->google == 'no'))
							<a href="{{ url('users/edit-verification') }}">
								<button  class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 mb-4">{{trans('messages.users_dashboard.complete_profile')}}</button>
							</a>
						@else
							<i class="fa fa-check-circle fa-3x text-success" aria-hidden="true"></i>
						@endif
					@endif

					<h2 class="text-center">{{trans('messages.users_dashboard.identity_verified')}} ({{ $reviews_count }})</h2>  
					@if(($result->users_verification->email == 'yes') || ($result->users_verification->facebook == 'yes') || ($result->users_verification->google == 'yes'))
						<h3 class="text-center"> <i class="fas fa-check-double"></i> Identity Verified</h3>
					@else
						<h2 class="text-center"> <i class="fa fa-times"></i> {{trans('messages.users_dashboard.identity_unverified')}}</h2>
					@endif
					<hr>
				</div>

				<div class="col-md-12 mt-4 p-0">
					<h2 class="font-weight-700">{{ ucfirst($result->first_name) }} {{ trans('messages.users_dashboard.confirmed') }}</h2>
					<ul>
						<li class="p-2" ><i class="{{ ($result->users_verification->email == 'yes') ? "fa fa-check" : "fa fa-times" }} "></i> {{ trans('messages.login.email') }}</li>
						<li class="p-2"><i class="{{ ($result->users_verification->facebook == 'yes') ? "fa fa-check" : "fa fa-times" }} "></i></i> {{ trans('messages.sign_up.facebook') }} </li>
						<li class="p-2"><i class="{{ ($result->users_verification->google == 'yes') ? "fa fa-check" : "fa fa-times" }} "></i></i> {{ trans('messages.sign_up.google') }}</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="col-md-9 p-0 mt-4">
			<div class="row">
				<div class="col-md-12 p-4">
					<h1 class="font-weight-700 text-30">{{trans('messages.users_show.hey')}} {{ucfirst($result->first_name)}}!</h1>
					<h5 class="gray-text mt-3"><strong>{{trans('messages.users_show.member_since')}} {{ $result->account_since }}</strong></h5>
					<hr/>
					@if(isset($details['live']))
						<p class="text-lg-left text-16 mt-3">  <i class="fas fa-home mr-2 text-20"></i>Lives in {{ $details['live'] }}</p>
					@endif
					
					@if(isset($details['about']))
						<p class="font-weight-700 mt-2 text-18">{{trans('messages.users_dashboard.about')}}</p>
						<p class="text-16 m-0">{{$details['about']}}</p>
						<br>
					@endif
				</div>
			</div>

			<div class="row">
				<div class="col-md-12">
						<div class="row  ">
							<div class="col-md-12 mt-2 p-0">
								<div class="row justify-content-center">                      
									<div class="col-md-12 list-bacground mt-0 rounded-3 pl-3 pt-3 pb-3 pr-3 border">
										<h2></i>  <span><strong>{{trans('messages.sidenav.reviews')}} ({{ $reviews_count }})</strong></span></h2>   
									</div>
									
									@if($reviews_from_guests->count() > 0 && $reviews_from_hosts->count() > 0 )
										<div class="col-md-12 p-0 mt-4">
											<ul class="nav nav-tabs" role="tablist">
												<li class="nav-item">
													<a class="nav-link active secondary-text-color text-color-hover" data-bs-toggle="tab" href="#tabs-1" role="tab">{{trans('messages.users_show.review_guest')}}</a>
												</li>
												<li class="nav-item">
													<a class="nav-link secondary-text-color text-color-hover" data-bs-toggle="tab" href="#tabs-2" role="tab">{{trans('messages.users_show.review_host')}}</a>
												</li>
											</ul><!-- Tab panes -->

											<div class="tab-content">
												<div class="tab-pane active" id="tabs-1" role="tabpanel">
													@foreach($reviews_from_guests as $row_host) 
														@include('users.review_list')
													@endforeach
												</div>

												<div class="tab-pane" id="tabs-2" role="tabpanel">
													@foreach($reviews_from_hosts as $row_host) 
														@include('users.review_list')
													@endforeach
												</div>
											</div>  
										</div>

									@elseif($reviews_from_guests->count() > 0)
										@foreach($reviews_from_guests as $row_host) 
											@include('users.review_list')
										@endforeach
									@elseif($reviews_from_hosts->count() > 0)
										@foreach($reviews_from_hosts as $row_host) 
											@include('users.review_list')
										@endforeach
									@endif
								</div>
							</div>
						</div>
				</div>
			</div>
		</div> 
	</div>
</div>
@stop

@push('scripts')
<script type="text/javascript">
	$("#profile-review-count").on('click', function(e){
		e.preventDefault()
		$('html,body').animate({
			scrollTop: $("#profile-review-title").offset().top},
			'slow');
	});
</script>
@endpush