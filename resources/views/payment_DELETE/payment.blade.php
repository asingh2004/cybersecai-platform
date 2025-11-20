@extends('template')

@section('main')
<div class="container-fluid container-fluid-90 margin-top-85 min-height">
	@if(Session::has('message'))
		<div class="row mt-5">
			<div class="col-md-12 text-13 alert mb-0 {{ Session::get('alert-class') }} alert-dismissable fade in  text-center opacity-1">
				<a href="#"  class="close " data-dismiss="alert" aria-label="close">&times;</a>
				{{ Session::get('message') }}
			</div>
		</div>
	@endif

	<div class="row justify-content-center">
		<div class="col-md-8 mb-5 mt-3 main-panel p-5 border rounded">
			<form action="{{ url('payments/create_booking') }}" method="post" id="checkout-form">
				{{ csrf_field() }}
				<div class="row justify-content-center">
				<input name="property_id" type="hidden" value="{{ $property_id }}">
				<input name="checkin" type="hidden" value="{{ $checkin }}">
				<input name="checkout" type="hidden" value="{{ $checkout }}">
				<input name="number_of_guests" type="hidden" value="{{ $number_of_guests }}">
				<input name="nights" type="hidden" value="{{ $nights }}">
				<input name="currency" type="hidden" value="{{ $result->property_price->code }}">
				<input name="booking_id" type="hidden" value="{{ $booking_id }}">
				<input name="booking_type" type="hidden" value="{{ $booking_type }}">
				<input name="respite_type" type="hidden" value="{{ $result->respite_service_type }}">
				<input name="referralcode" type="hidden" value="{{ $result->referralcode }}">

            				

		
				@if($booking_type == "instant"|| $status == "Processing" )

				@if(!$is_discounted)
					<div class="col-md-12 p-0">
						<label for="exampleInputEmail1">{{ trans('messages.payment.country') }}</label>
					</div>

					<div class="col-sm-12 p-0 pb-3">
					
						<select name="payment_country" id="country-select" data-saving="basics1" class="form-control mb20">
							<option selected>Australia</option>
							@foreach($country as $key => $value)
							<!-- <option value="{{ $key }}" {{ ($key == $default_country) ? 'selected' : '' }}>{{ $value }}</option> -->

							<option value="{{ $key }}">{{ $value }}</option>
							@endforeach
							
						</select>
					</div>

					<div class="col-sm-12 p-0">
						<label for="exampleInputEmail1">{{ trans('messages.payment.payment_type') }}</label>
					</div>

					<div class="col-sm-12 p-0 pb-3">
						<select name="payment_method" class="form-control mb20" id="payment-method-select">
							@if($paypal_status->value == 1)
								<option value="paypal" data-payment-type="payment-method" data-cc-type="visa" data-cc-name="" data-cc-expire="">
								{{ trans('messages.payment.paypal') }}
								</option>
							@endif

							@if($stripe_status->value == 1)
								<option value="stripe" data-payment-type="payment-method" data-cc-type="visa" data-cc-name="" data-cc-expire="">
								{{ trans('messages.payment.stripe') }}
								</option>
              @endif

              @if($banks >= 1)
                <option value="bank" data-payment-type="payment-method" data-cc-type="bank" data-cc-name="" data-cc-expire="">
                  {{ trans('messages.payment.bank') }}
              	</option>
              @endif

							@if(!$paypal_status->value == 1 && !$stripe_status->value == 1 && !$banks >= 1)
								<option value="">
								{{ trans('messages.payment.disable') }}
								</option>
							@endif
						</select>
						<div class="paypal-div {{$paypal_status->value != 1 ? 'display-off' : ''}}">
							<span id='paypal-text'>{{ trans('messages.payment.redirect_to_paypal') }}</span>
						</div>

					</div>
					@endif

				@else
					<div class="text-16 mb-0">
						<strong class="font-weight-700 secondary-text-color">Please review and complete the following:</strong>
					</div>


					<form class="row g-3 needs-validation" novalidate>
  					<div class="col-sm-12">
    					<label for="referralcode" class="form-label">Referral Code</label>
    					<input type="text" class="form-control" name="referralcode" id="referralcode" value="" placeholder="12 digit code (formatted like 1-12345678012). Pls enter if you have it." required>
    					<div class="valid-feedback">Looks good!</div>
  					</div>


						<div>
  						<p>
    							<button class="btn vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
    							<strong> What is Referral Code? </strong>
  							</button>
							</p>

							<div class="collapse" id="collapseExample">
  							<div class="card card-body">

  								<figure>
  								<figcaption class="blockquote-footer">Referral Code is a 12 digit code (formatted like 1-12345678012) that is provided with an Aged Care Assessment. A potential aged care resident will likely receive a referral code for respite, permanent aged care, home care packages and other home care services. This code will allow aged care providers to view the aged care assessment online, and understand a potential aged care resident’s care needs. </figcaption>
									</figure>
  							</div>
							</div>
						</div>


						<div class="col-sm-12">
    					<label for="validationCustom01" class="form-label">FAQ on Residential Respite Care</label>
    					<div>
  							<p>
  
  							<button class="btn vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExample1" aria-expanded="false" aria-controls="collapseExample1"><strong> Click on me to view FAQs </strong>     </button>
							</p>

							<div class="collapse" id="collapseExample1">
  							<div class="card card-body">

									<dl class="row">
  									<dt class="col-sm-3">1. Aged Care Assessment (ACAT/ASAS)</dt>
  									<dd class="col-sm-9">
  									<figure>
    									<figcaption class="blockquote-footer">A potential aged care resident will need to get an aged care assessment prior to entering a nursing home. This is a medical assessment done by a clinician from an Aged Care Assessment Service (VIC/WA), or Aged Care Assessment Team (all other States).</figcaption>
										</figure>
										</dd>

  									<dt class="col-sm-3">2. Respite </dt>
  									<dd class="col-sm-9">
  									<figure>
    									<figcaption class="blockquote-footer">This is a short term stay in an aged care facility. The Australian Government subsidises up to 63 days of respite per financial year for an elderly person who has an aged care assessment. A respite stay is also a good way for a potential resident to get to know a facility.</figcaption>
										</figure>
										</dd>

  									<dt class="col-sm-3">3. What is residential respite accommodation?</dt>
  									<dd class="col-sm-9">
  									<figure>
    									<figcaption class="blockquote-footer">It is an Aged Care service provider facility (ie aged care home) that provides residential respite care.</figcaption>
										</figure>
										</dd>

  									<dt class="col-sm-3">4. What is residential respite care?</dt>
  									<dd class="col-sm-9"> 
  									<figure>
    									<figcaption class="blockquote-footer">Residential respite care is short-term care delivered within an aged care home on either a planned or emergency basis.</figcaption>
										</figure>
										</dd>

  
  									<dt class="col-sm-3">5. Who can access residential respite care?</dt>
  									<dd class="col-sm-9">
  									<figure>
    									<figcaption class="blockquote-footer">An assessment by an Aged Care Assessment Team (ACAT)/ ASAS is required to access residential respite care. Eligible care recipients are approved for either high or low level residential respite care.</figcaption>
										</figure>
										</dd>

  									<dt class="col-sm-3">6. Why do people use residential respite care?</dt>
  									<dd class="col-sm-9">
  									<figure>
    									<figcaption class="blockquote-footer">The primary purpose of residential respite care is to give a carer or care recipient a break from their usual care arrangements. Residential care providers may receive respite subsidies and supplements for eligible residential respite care recipients. Residential care providers do not have a separate allocation of residential respite places. Rather a portion of each permanent allocation of residential care places is used for the provision of respite care, known as respite days. </figcaption>
										</figure>
										</dd>

  									<dt class="col-sm-3">7. How Long Can I stay in residential respite accommodation? </dt>
  									<dd class="col-sm-9">

  									<figure>
    									<figcaption class="blockquote-footer">It depends on individual circumstances. Residential respite care is most commonly accessed in weekly units. A fortnight is by far the most common residential respite care length of stay. One, three and four weeks are the next most common lengths of stay. A person who is approved for respite care can have up to 63 days of subsidised respite care in a financial year. This can be extended by up to 21 days at a time if approved by an ACAT. Where a care recipient accesses residential respite care, providers are responsible for checking the care recipient’s remaining respite care allowance. Respite care subsidies and supplements are not paid if the care recipient has used up their annual allowance of respite care days. </figcaption>
										</figure>
										</dd>
									</dl>

    						</div>
							</div>
							</div>
   					</div>
					</form>
				@endif


				<div class="col-sm-12 p-0">
					<label for="message"></label>
				</div>

				
				<div class="col-sm-12 p-0 pb-3">
					<textarea name="message_to_host" placeholder="{{ trans('messages.trips_active.type_message') }}" class="form-control mb20" rows="7" required style="height: 300px;"></textarea>
				</div>

					@if($status == "" && $booking_type == "request")
						@if(session()->get('payment_discount_code') || $is_discounted)
							<div class="text-16 mb-0">
								<class="font-weight-700 secondary-text-color">{{ trans('messages.listing_book.discounted_req_message') }}
							</div>
						@else
							<div class="text-16 mb-0">
								<class="font-weight-700 secondary-text-color">{{ trans('messages.listing_book.request_message') }}
							</div>
						@endif
					@endif

					<div class="col-sm-12 p-0 text-right mt-4">
						<button id="payment-form-submit" type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3">
							<i class="spinner fa fa-spinner fa-spin d-none"></i>
							{{ ($booking_type == 'instant') ? trans('messages.listing_book.book_now') : trans('messages.property.continue') }}
						</button>
					</div>
			</form>
		</div>
	</div>



		<div class="col-md-4  mt-3 mb-5">
				<div class="card p-3">
					<a href="{{ url('/') }}/properties/{{ $result->slug}}">
						<img class="card-img-top p-2 rounded" src="{{ $result->cover_photo }}" alt="{{ $result->name }}" height="180px">
					</a>

					<div class="card-body p-2">
						<a href="{{ url('/') }}/properties/{{ $result->slug}}">
							<p class="text-16 font-weight-700 mb-0">{{ $result->name }}</p>
						</a>

						<p class="text-14 mt-2 text-muted mb-0">
							<i class="fas fa-map-marker-alt"></i>
							{{$result->property_address->address_line_1}}, {{ $result->property_address->state }}, {{ $result->property_address->country_name }}
						</p>
						<div class="border p-4 mt-4 text-center rounded-3">
							<p class="text-16 mb-0">
								<strong class="font-weight-700 secondary-text-color">{{ $result->property_type_name }}</strong>
								{{trans('messages.payment.for')}}
								<strong class="font-weight-700 secondary-text-color">{{ $number_of_guests }} {{trans('messages.payment.guest')}}</strong>
							</p>
							<div class="text-16"><strong>{{ date('D, M d, Y', strtotime($checkin)) }}</strong> to <strong>{{ date('D, M d, Y', strtotime($checkout)) }}</strong></div>
						</div>

						<div class="border p-4 rounded-3 mt-4">

						
							

							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="text-16 mb-0">
										<strong class="font-weight-700 secondary-text-color">{{trans('messages.property_single.care_type1')}}: </strong>
									</p>	
                                  </div>

                                  <div>
									<p class="text-16 mb-0">
										<strong class="font-weight-700 secondary-text-color">{{ $result->respite_service_type }} </strong>
									</p>
									<input type="hidden" id="respite_service_type" name ="respite_service_type" value="{{ $result->respite_service_type }}">
								</div>
							</div>


							<hr>

							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">{{trans('messages.payment.night')}}</p>
								</div>
								<div>
									<p class="pr-4">{{ $nights }}</p>
								</div>
							</div>

							<div class="d-flex justify-content-between text-16">
								@if(session()->get('payment_discount_code') || $is_discounted)
								<div>
									<p class="pl-4">$0 x {{ $nights }} {{trans('messages.payment.nights')}}</p>
								</div>
								<div>
									<p class="pr-4">$0</p>
								</div>
							</div>
							@else

							<div>
									<p class="pl-4">{!! $price_list->per_night_price_with_symbol !!} x {{ $nights }} {{trans('messages.payment.nights')}}</p>
								</div>
								<div>
									<p class="pr-4">{!! $price_list->total_night_price_with_symbol !!}</p>
								</div>
							</div>
							@endif

				

							@if($price_list->additional_guest)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.payment.additional_guest_fee')}}</p>
									</div>

									<div>
										<p class="pr-4">{!! $price_list->additional_guest_fee_with_symbol !!}</p>
									</div>
								</div>
							@endif

							@if($price_list->security_fee)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.payment.security_deposit')}}</p>
									</div>

									<div>
										<p class="pr-4">{!! $price_list->security_fee_with_symbol !!}</p>
									</div>
								</div>
							@endif

							@if($price_list->cleaning_fee)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.payment.cleaning_fee')}}</p>
									</div>

									<div>
										<p class="pr-4">{!! $price_list->cleaning_fee_with_symbol !!}</p>
									</div>
								</div>
							@endif

							@if(session()->get('payment_discount_code')  || $is_discounted)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.property_single.iva_tax')}}</p>
									</div>

									<div>
										<p class="pr-4">$0</p>
									</div>
								</div>
							@else
							@if($price_list->iva_tax)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.property_single.iva_tax')}}</p>
									</div>

									<div>
										<p class="pr-4">{!! $price_list->iva_tax_with_symbol !!}</p>
									</div>
								</div>
							@endif
							@endif

							@if($price_list->accomodation_tax)
								<div class="d-flex justify-content-between text-16">
									<div>
										<p class="pl-4">{{trans('messages.property_single.accommodatiton_tax')}}</p>
									</div>

									<div>
										<p class="pr-4">{!! $price_list->accomodation_tax_with_symbol !!}</p>
									</div>
								</div>
							@endif
							<hr>

							<div class="d-flex justify-content-between font-weight-700">
								<div>
									<p class="pl-4">{{trans('messages.payment.total')}}</p>
								</div>

								<div>
									@if(session()->get('payment_discount_code') || $is_discounted)
										<p class="pr-4">No charge, thank you!</p>
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
<!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->
<script type="text/javascript">
$('#payment-method-select').on('change', function(){
  var payment = $(this).val();
  if(payment !== 'paypal'){
      $('.paypal-div').addClass('display-off')
  }
  else {
      $('.paypal-div').removeClass('display-off')
  }
});

$(document).ready(function() {
    $('#checkout-form').validate({
        submitHandler: function(form)
        {
 			$("#payment-form-submit").on("click", function (e)
            {
            	$("#payment-form-submit").attr("disabled", true);
                e.preventDefault();
            });


            $(".spinner").removeClass('d-none');
            $("#save_btn-text").text("{{trans('messages.users_profile.save')}} ..");
            return true;
        }
    });
});


$('#country-select').on('change', function() {
  var country = $(this).find('option:selected').text();
  $('#country-name-set').html(country);
})
</script>
@endpush
@stop
