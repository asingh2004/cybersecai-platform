@extends('template')

@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height">
	<div class="row">
		<div class="col-md-8 mb-5 mt-3 main-panel p-5 border rounded">
			<div class="col-lg-12 p-0">
				@if($booking_details->status == 'Pending' || $booking_details->status == 'Processing')

                    @if($booking_details->payment_method_id == 4)
                        <h2>{{trans('messages.booking_request.get_ready')}} {{ $booking_details->properties->property_address->city }}!</h2>
                        <p>{{trans('messages.booking_request.bank_paid')}}</p>
                    @else

                        <h3 class="font-weight-700"><i class="fa fa-info-circle" aria-hidden="true"></i> {{ trans('messages.booking_request.request_has_sent') }}</h3>
                        <p>{{ trans('messages.booking_request.not_a_confirmed_booking') }} {{ trans('messages.booking_request.hear_back_within_24') }} {{ trans('messages.booking_request.not_be_charged') }} {{$booking_details->properties->users->first_name}} {{ trans('messages.booking_request.accommodate_stay') }}.</p>
                    @endif
                @endif

				@if($booking_details->status == 'Accepted')
					<h2>{{trans('messages.booking_request.get_ready')}} {{ $booking_details->properties->property_address->city }}!</h2>
					<p>{{trans('messages.booking_request.confirmed_booking')}} {{$booking_details->properties->users->first_name}}. {{trans('messages.booking_request.emailed_itinerary')}} {{$booking_details->properties->users->email}}.</p>
				@endif


			</div>


		</div>

		<div class="col-md-4">
			<div class="card mt-3 mb-5 p-3">
				<a href="{{ url('/') }}/properties/{{ $booking_details->properties->slug}}">
					<img class="card-img-top p-2 rounded" src="{{$booking_details->properties->cover_photo}}" alt="{{$booking_details->properties->name}}" height="180px">
				</a>

				<div class="card-body p-4">
					<a href="{{ url('/') }}/properties/{{ $booking_details->properties->slug}}">
						<p class="text-16 font-weight-700 mb-0">{{ $booking_details->properties->name }}</p>
					</a>

					<p class="text-14 mt-2 text-muted mb-0">
						<i class="fas fa-map-marker-alt"></i>
						{{$booking_details->properties->property_address->address_line_1}}, {{ $booking_details->properties->property_address->state }}, {{ $booking_details->properties->property_address->country_name }}
					</p>
					<div class="border p-4 mt-3 text-center rounded-3">
						<p class="text-16 mb-0">
							<strong class="font-weight-700 secondary-text-color">{{ $booking_details->properties->property_type_name }}</strong>
							{{trans('messages.payment.for')}}
							<strong class="font-weight-700 secondary-text-color">{{ $booking_details->guest }} {{trans('messages.payment.guest')}}</strong>
						</p>
						<div class="text-16"><strong>{{ date('D, M d, Y', strtotime($booking_details->startdate_dmy)) }}</strong> to <strong>{{ date('D, M d, Y', strtotime($booking_details->enddate_dmy)) }}</strong></div>
					</div>

					<div class="border p-2 mt-3 rounded-3">			
							

							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="text-16 mb-0">
										<strong class="font-weight-700 secondary-text-color">{{trans('messages.property_single.care_type1')}}: </strong>
									</p>	
                                  </div>

                                  <div>
									<p class="text-16 mb-0">
										<strong class="font-weight-700 secondary-text-color">{{ $booking_details->properties->respite_service_type }} </strong>
									

									</p>
								</div>
							</div>


							<hr>


						<div class="d-flex justify-content-between text-16">
							<div>
								<p class="pl-4">{{trans('messages.booking_detail.night')}}</p>
							</div>

							<div>
								<p class="pr-4">{{ $booking_details->total_night }}</p>
							</div>
						</div>

						

				

						@if($booking_details->cleaning_charge != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.booking_detail.cleanning_fee')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->cleaning_fee_with_symbol !!}</p>
								</div>
							</div>
						@endif

						@if($booking_details->guest_charge != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.booking_detail.additional_guest_fee')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->additional_guest_fee_with_symbol !!}</p>
								</div>
							</div>
						@endif

						@if($booking_details->security_money != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.booking_detail.security_fee')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->security_fee_with_symbol !!}</p>
								</div>
							</div>
						@endif

						@if($booking_details->service_charge != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.property_single.service_fee')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->service_fee_with_symbol !!}</p>
								</div>
							</div>
						@endif


						@if($booking_details->iva_tax != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.property_single.iva_tax')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->iva_tax_with_symbol !!}</p>
								</div>
							</div>
						@endif

						@if($booking_details->accomodation_tax != 0)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.property_single.accommodatiton_tax')}}</p>
								</div>

								<div>
									<p class="pr-4">{!! $price_list->accomodation_tax_with_symbol !!}</p>
								</div>
							</div>
						@endif


			

						@if($booking_details->host_fee)
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.booking_detail.host_fee')}}</p>
									<i class="icon icon-question icon-question-sign" data-behavior="tooltip" rel="tooltip" aria-label="OzzieAccom charges a fee to cover the cost (banking fees) of processing payment from the traveler."></i>

								</div>

								<div>
									<p class="pr-4">{!! $booking_details->currency->symbol !!}{{ $booking_details->host_fee }}</p>
								</div>
							</div>
						@endif

						<hr>
						<div class="d-flex justify-content-between text-16 font-weight-700"  id="total">
							<div>
								<p class="pl-4">{{trans('messages.booking_detail.total_payout')}}</p>
							</div>

							<div>
								@if(session()->get('payment_discount_code'))
									<p class="pr-4">No Fee</p>
								@else
									<p class="pr-4">{!! $price_list->total_with_symbol !!}</p>
								@endif
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@push('scripts')
<script type="text/javascript">
	$('#request-add-email').on('click', function(){
		var content = '<div class="form-group">'
			+'<input type="email" name="friend[]" class="form-control" id="exampleInputPassword1" placeholder="Email">'
			+'</div>';
		$(content).insertBefore('#add-email-field');
	});
</script>
@endpush
@stop
