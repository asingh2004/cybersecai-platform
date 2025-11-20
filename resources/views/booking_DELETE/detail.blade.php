@extends('template')
@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height">
	<div class="row mb-4">
		<div class="col-md-8 card p-5">
			<h2 class="font-weight-700">{{ trans('messages.booking_detail.request_booking') }}</h2>
			<div class="d-flex justify-content-between">
				<div>
					@if($result->status == 'Pending')
						<div class="pull-right mt-4">
							<span class="label label-info">
								<i class="far fa-clock"></i>
								{{ trans('messages.booking_detail.expire_in') }}
								<span class="countdown_timer hasCountdown"><span class="countdown_row countdown_amount" id="countdown_1"></span></span>
							</span>
						</div>
					@endif
				</div>

				<div>

				</div>
			</div>

			<div class="row flex-column-reverse flex-md-row mt-4 m-0">
				<div class="col-md-9 p-0">
					<p class="text-justify">{{ $result->users->first_name }} {{ $result->users->last_name }} has requested to book your Aged Care Home. Please accept or Decline this Residential Respite Care booking request.</p>
					@if($result->host_id == Auth::id())
				@if($result->status == 'Pending')
				<div>
					<p><i class="fas fa-exclamation-triangle  text-warning"></i> {{ trans('messages.booking_detail.expire_in_data') }}</p>
				</div>

				<div class="mt-5 text-center text-sm-left">
					<!-- Button trigger modal -->
					<button type="button" class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 pl-5 pr-5 mt-4" data-bs-toggle="modal" data-bs-target="#accept-modal" >
  						{{ trans('messages.booking_detail.accept') }}

					</button>


					<button type="button" class="btn btn-outline-danger text-16 font-weight-700  pl-5 pr-5 pt-3 pb-3 pl-5 pr-5 mt-4 ml-0 ml-sm-4" data-bs-toggle="modal" data-bs-target="#decline-modal">
					{{ trans('messages.booking_detail.decline') }}
					</button>
				</div>

				@else
					<h3 class="mt-4 font-weight-700">Booking status</h3>
					<p class="text-16 font-weight-700 secondary-text-color">{{ $result->status }}</p>
				@endif
			@endif
				</div>

				<div class="col-md-3">
					<div class="media-photo-badge text-center">
						<a href="{{ url('/') }}/users/show/{{ $result->users->id }}" ><img alt="{{ $result->users->first_name }}" class="" src="{{ $result->users->profile_src }}" title="{{ $result->users->first_name }}"></a>
					</div>
					<p class="font-weight-700 mb-0 text-center"><a href="{{ url('/') }}/users/show/{{ $result->users->id }}" class="verification_user_name">{{ $result->users->first_name }}</a></p>
					<p class="text-14 text-muted text-center">{{ trans('messages.booking_detail.member_since') }} {{ $result->users->account_since }}</p>
				</div>
			</div>
		</div>

        <div class="col-md-4">
            <div class="card mt-3 mb-5 p-3">
                <a href="{{ url('/') }}/properties/{{ $result->properties->slug}}">
                    <img class="card-img-top p-2 rounded" src="{{$result->properties->cover_photo}}" alt="{{$result->properties->name}}" height="180px">
                </a>

                <div class="card-body p-4">
                    <a href="{{ url('/') }}/properties/{{ $result->properties->slug}}">
                        <p class="text-16 font-weight-700 mb-0">{{ $result->properties->name }}</p>
                    </a>

                    <p class="text-14 mt-2 text-muted mb-0">
                        <i class="fas fa-map-marker-alt"></i>
                        {{$result->properties->property_address->address_line_1}}, {{ $result->properties->property_address->state }}, {{ $result->properties->property_address->country_name }}
                    </p>
                    <div class="border p-4 mt-3 text-center rounded-3">
                        <p class="text-16 mb-0">
                            <strong class="font-weight-700 secondary-text-color">{{ $result->properties->property_type_name }}</strong>
                            {{trans('messages.payment.for')}}
                            <strong class="font-weight-700 secondary-text-color">{{ $result->guest }} {{trans('messages.payment.guest')}}</strong>
                        </p>
                        <div class="text-16"><strong>{{ date('D, M d, Y', strtotime($result->startdate_dmy)) }}</strong> to <strong>{{ date('D, M d, Y', strtotime($result->enddate_dmy)) }}</strong></div>
                    </div>

                    <div class="border p-2 mt-3 rounded-3">
                        <div class="d-flex justify-content-between text-16">
                            <div>
                                <p class="pl-4">{{trans('messages.booking_detail.night')}}</p>
                            </div>

                            <div>
                                <p class="pr-4">{{ $result->total_night }}</p>
                            </div>
                        </div>

                  


                        @if($result->cleaning_charge != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.booking_detail.cleanning_fee')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->cleaning_fee_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif

                        @if($result->guest_charge != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.booking_detail.additional_guest_fee')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->additional_guest_fee_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif

                        @if($result->security_money != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.booking_detail.security_fee')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->security_fee_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif

                        @if($result->service_charge != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.property_single.service_fee')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->service_fee_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif


                        @if($result->iva_tax != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.property_single.iva_tax')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->iva_tax_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif

                        @if($result->accomodation_tax != 0)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.property_single.accommodatiton_tax')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->accomodation_tax_with_symbol !!}</p>
                                </div>
                            </div>
                        @endif



                        @if($result->host_fee)
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.booking_detail.host_fee')}}</p>
                                    <i class="icon icon-question icon-question-sign" data-behavior="tooltip" rel="tooltip" aria-label="myRespiteAccom charges a fee to cover the cost (banking fees) of processing payment from the traveler."></i>

                                </div>

                                <div>
                                    <p class="pr-4">{!! $result->currency->symbol !!}{{ $result->host_fee }}</p>
                                </div>
                            </div>
                        @endif

                        <hr>
                        <div class="d-flex justify-content-between text-16 font-weight-700"  id="total">
                            <div>
                                <p class="pl-4">{{trans('messages.booking_detail.total_payout')}}</p>
                            </div>

                            <div>
                            	@if($price_list)
                                	<p class="pr-4">{!! $price_list->total_with_symbol !!}</p>
                                @else
                                	<p class="pr-4">No Booking Fee!</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
	</div>
</div>

<div class="modal fade mt-5 modal-z-index" id="accept-modal" tabindex="-1" role="dialog" aria-labelledby="accept-modal" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<div class="w-100 pt-3">
						<h4 class="modal-title text-20 text-center font-weight-700">{{ trans('messages.booking_detail.accept_this_request') }}</h4>
					</div>

					<div>
						<button type="button" class="btn-close text-28 mr-2 filter-cancel font-weight-500" data-bs-dismiss="modal" aria-label="Close">
							
						</button>
					</div>
				</div>

				<div class="modal-body p-4">
					<form accept-charset="UTF-8" action="{{ url('booking/accept/'.$booking_id) }}" id="accept_reservation_form" method="post" name="accept_reservation_form">
						{{ csrf_field() }}
						<div class="row">
							<div class="col-md-12">
								<label for="cancel_message" class="row-space-top-2">
									{{ trans('messages.booking_detail.optional_message_request') }}
								</label>
								<textarea class="form-control" id="accept_message" name="message" rows="4"></textarea>
							</div>

							<div class="col-md-12">
								<div class="row mt-4">
									<div class="col-sm-1 p-0">
										<input id="tos_confirm" name="tos_confirm" type="checkbox" value="1">
									</div>

									<div class="col-sm-11 p-0 text-16 text-justify">
										<label class="label-inline" for="tos_confirm">{{ trans('messages.booking_detail.check_box_agree') }} <br><a href="https://myrespiteaccom.com.au/service-provider-terms-of-use" target="_blank" class="font-weight-700">{{ trans('messages.booking_detail.guarantee_term_condition') }}</a>, and understand my obligations as the Service Provider.</label>
									</div>
								</div>
							</div>

							<div class="col-md-12 text-right mt-4">
								<input type="hidden" name="decision" value="accept">

								<button type="button" class="btn btn-outline-danger text-16 font-weight-700 pl-5 pr-5 pt-2 pb-2 pl-5 pr-5 mt-4 ml-2" data-bs-dismiss="modal">{{trans('messages.booking_detail.close')}}</button>

								<button type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-2 pb-2 pl-5 pr-5 mt-4 mr-2" id="accept_submit" name="commit"> <i class="spinner fa fa-spinner fa-spin d-none" id="accept_spinner" ></i>
								<span id="accept_btn-text">{{trans('messages.booking_detail.accept')}}</span>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>
	</div>
</div>

<div class="modal fade mt-5 modal-z-index" id="decline-modal" tabindex="-1" role="dialog" aria-labelledby="decline-modal" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content ">
			<div class="modal-header">
				<div class="w-100 pt-3">
					<h4 class="modal-title text-20 text-center font-weight-700">{{ trans('messages.booking_detail.cancel_this_booking') }}</h4>
				</div>

				<div>
					<button type="button" class="btn-close text-28 mr-2 filter-cancel font-weight-500" data-bs-dismiss="modal" aria-label="Close">
						
					</button>
				</div>
			</div>

			<form accept-charset="UTF-8" action="{{ url('booking/decline/'.$booking_id) }}" id="decline_reservation_form" method="post" name="decline_reservation_form">
				{{ csrf_field() }}
				<div class="modal-body p-4">
					<div class="row">
						<div class="col-md-12">
							<div id="decline_reason_container">
								<p class="text-14">
									{{ trans('messages.booking_detail.improve_experience') }}{{ trans('messages.booking_detail.what_reason_cancelling') }}
								</p>
								<p>
								<strong>
									{{ trans('messages.booking_detail.response_not_shared') }}
								</strong>
								</p>
								<div class="select">
									<select class="form-control" id="decline_reason" name="decline_reason">
										<option value=" ">{{ trans('messages.booking_detail.reason_for_declining') }}</option>
										<option value="dates_not_available">{{ trans('messages.booking_detail.date_are_not_avialable') }}</option>
										<option value="not_a_good_fit">{{ trans('messages.booking_detail.listing_is_not_good') }}</option>
										<option value="spam">{{ trans('messages.booking_detail.spam_message') }}</option>
										<option value="other">{{ trans('messages.booking_detail.other') }}</option>
									</select>
									<span class="errorMessage text-danger"></span>
								</div>

								<div id="cancel_reason_other_div d-none" class="mt-4">
								<label for="cancel_reason_other" class="mb-3">
									{{ trans('messages.booking_detail.why_declining') }}?
								</label>
								<textarea class="form-control" id="decline_reason_other" name="decline_reason_other" rows="4" value=""></textarea>
								<span class="decline_reason_other text-danger"></span>
								</div>
							</div>
						</div>

						<div class="col-md-12 mt-4">
							<input type="checkbox" checked="checked" name="block_calendar" value="yes">
							{{ trans('messages.booking_detail.block_calender') }}  <b>{{ $result->startdate_md }}</b> {{ trans('messages.booking_detail.through') }} <b>{{ $result->enddate_md }}</b>
						</div>

						<div class="col-md-12">
							<label for="cancel_message" class="mt-3 mb-3">
								{{ trans('messages.booking_detail.optional_message_request') }}
							</label>
							<textarea class="form-control" id="decline_message" name="message" rows="4"></textarea>
						</div>

						<div class="col-md-12 mt-5 text-right">
							<input type="hidden" name="decision" value="decline">

							<button type="button" class="btn btn-outline-danger text-16 font-weight-700 pl-5 pr-5 pt-2 pb-2 pl-5 pr-5 ml-2" data-bs-dismiss="modal">{{ trans('messages.booking_detail.close') }}</button>

							<button type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-2 pb-2 pl-5 pr-5 mr-2" id="decline_submit" name="commit"> <i class="spinner fa fa-spinner fa-spin d-none" id="decline_spinner" ></i>
							<span id="decline_btn-text">{{trans('messages.booking_detail.decline')}}</span>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<input type="hidden" id="expired_at" value="{{ $result->expiration_time }}">
<input type="hidden" id="booking_id" value="{{ $booking_id }}">
@stop

@push('scripts')
<!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->

<script type="text/javascript">
$(document).ready(function() {
$(document).on('submit', 'form', function() {
	$('#accept_submit').attr('disabled', 'disabled');
});
});
</script>
<script type="text/javascript">
$('#accept-modal-trigger').on('click', function(){
	expirationTimeSet()
	$('#accept-modal').modal();
})
$('#decline-modal-trigger').on('click', function(){
	$('#decline-modal').modal();
})
$('#discuss-modal-trigger').on('click', function(){
	$('#discuss-modal').modal();
})

$('#decline_reason').on('change', function(){
	var res = $('#decline_reason').val();
	if(res == 'other') $('#cancel_reason_other_div').show();
});

var expiration_time  =  "{{ $result->expiration_time }}";
var _second = 1e3, _minute = 60 * _second, _hour = 60 * _minute, _day = 24 * _hour, timer;

function expirationTimeSet(){
	date_ele = new Date,

	present_time = new Date(date_ele.getUTCFullYear(), date_ele.getUTCMonth(), date_ele.getUTCDate(), date_ele.getUTCHours(), date_ele.getUTCMinutes(), date_ele.getUTCSeconds()).getTime(),
	expiration_time = new Date(this.expiration_time).getTime(),

	
	time_remaining = expiration_time - present_time;
	//console.log(expiration_time);
	//console.log(present_time);
	if (time_remaining < 0)
	//return '';
	//return clearInterval(interval), document.getElementById("countdown_1").innerHTML = "Expired!";
	return clearInterval(interval), document.getElementById("countdown_1").innerHTML = "";
	else{
	var d = Math.floor(time_remaining / (1000 * 60 * 60 * 24)),
	//var days = Math.floor(distance / (1000 * 60 * 60 * 24));
		h = (Math.floor(time_remaining / this._day), Math.floor(time_remaining % this._day / this._hour)),
		m = Math.floor(time_remaining % this._hour / this._minute),
		s = Math.floor(time_remaining % this._minute / this._second);

		// Ref: https://www.w3schools.com/howto/howto_js_countdown.asp
		// Output the result in an element with id="countdown_1"
  		document.getElementById("countdown_1").innerHTML = d + "d " + h + "h " + m + "m " + s + "s ";
		//document.getElementById("countdown_1").innerHTML = d + "day",document.getElementById("countdown_1").innerHTML = h + ":", document.getElementById("countdown_1").innerHTML += m //+ ":", document.getElementById("countdown_1").innerHTML += s + "";
	}
	//console.log(d+'day '+h+':'+m+':'+s);
}

var interval = setInterval(expirationTimeSet, 1e3)
$(document).on('click','#decline_submit',function(){
	var optVal    = $('#decline_reason').val();
	if(optVal==' '){
	$('.errorMessage').html("{{ __('messages.jquery_validation.required') }}");
	return false;
	}else if(optVal=='other'){
	var decline_reason = $('#decline_reason_other').val();
	if(decline_reason==''){
		$('.decline_reason_other').html("{{ __('messages.jquery_validation.required') }}");
		return false;
	}else{
		return true;
	}
	}
});
	$(document).on('click','#accept_submit',function(){
		if($("#tos_confirm").prop('checked') == true){
			return true;
		}else{
		alert("{{ __('messages.jquery_validation.accept_terms_conditions') }}");
		return false;
		}
});
</script>

<script type="text/javascript">
	$(document).ready(function () {
		$('#accept_reservation_form').validate({
			rules: {
				tos_confirm: {
					required: true
				}
			},
			submitHandler: function(form)
			{
				$("#accept_submit").on("click", function (e)
				{
					$("#accept_submit").attr("disabled", true);
					e.preventDefault();
				});

				$("#accept_spinner").removeClass('d-none');
				$("#accept_btn-text").text("{{trans('messages.booking_detail.accept')}} ..");
				return true;

			},
			messages: {
				tos_confirm: {
					required:  "{{ __('messages.jquery_validation.required') }}",
				}
			}
		});

		$('#decline_reservation_form').validate({
			rules: {
				decline_reason: {
					required: true
				}
			},
			submitHandler: function(form)
			{
				$("#decline_submit").on("click", function (e)
				{
					$("#decline_submit").attr("disabled", true);
					e.preventDefault();
				});

				$("#decline_spinner").removeClass('d-none');
				$("#decline_btn-text").text("{{trans('messages.booking_detail.decline')}} ..");
				return true;

			},
			messages: {
				decline_reason: {
					required:  "{{ __('messages.jquery_validation.required') }}",
				}
			}
		});
	});
</script>
@endpush

