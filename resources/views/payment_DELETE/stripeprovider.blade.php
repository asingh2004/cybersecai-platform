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

	<div class="row">
		<div class="col-md-8 col-sm-8 col-xs-12 mb-5 main-panel p-5 border rounded">
			<div class="pb-3 m-0 text-24 font-weight-700">{{trans('messages.payment.stripe')}} {{trans('messages.payment.payment')}} </div>


			<div class="container">
  				<div class="row row-cols-1">
            		<div>
            		<a class="navbar-brand logo_h" aria-label="paymentstripe" href="https://stripe.com/au" target="_blank"><img src="/public/front/images/payment/StripePaymentMethods.png" alt="stripepaymentlogo" class="img-fluid"></a>
         			</div>
    
  				</div>
			</div>

			<form action="{{URL::to('facilities/stripe-request')}}" method="post" id="payment-form">
				{{ csrf_field() }}

				<div class="container">
  					<div class="row row-cols-1">
			
					<label for="card-element">
					{{trans('messages.payment_stripe.credit_debit_card')}}
					</label>
					</div>
				</div>

				<div class="container">
  					<div class="row row-cols-1">
						<div id="card-element">
						<!-- a Stripe Element will be inserted here. -->
						</div>

						<!-- Used to display form errors -->
					<div id="card-errors" role="alert"></div>
					</div>
				</div>
		







			<div class="form-group mt-5">
				<div class="col-sm-8 p-0">
					<button class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3" id="stripe_btn"><i class="spinner fa fa-spinner fa-spin d-none"></i> {{trans('messages.payment_stripe.submit_payment')}}</button>
				</div>
			</div>
		</form>
		</div>



		<div class="col-md-4 mb-5">
			<div class="card p-3">
				

					<div class="border p-4 mt-3">
					
							<div>
								<p class="pl-4 text-16 font-weight-700">Subscription Summary</p>
							</div>
						

							
							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">Subscription Start Date:</p>
								</div>

								<div>
									<p class="pr-4"> {{ Session::get('subs_start_date') }}</p>
								</div>
							</div>
						

							<div class="d-flex justify-content-between text-16">
								<div>
									<p class="pl-4">Next Renewal Date: </p>
								</div>
								
								<div>
									<p class="pr-4"> {{ Session::get('sub_renewal_date') }} </p>
								</div>
							</div>


						<hr>
						<div class="d-flex justify-content-between text-16">
							<div>
								<p class="pl-4">Account ID: </p>
							</div>
							<div>

							@foreach(session('businessname') as $email)
								<p class="pr-4">{{ $email->email }} </p>
							@endforeach
							</div>
						</div>

						<div class="d-flex justify-content-between text-16">
							<div>
								<p class="pl-4">Number of Properties Subscribed:</p>
							</div>
							<div>
								<p class="pr-4"> {{ Session::get('properties_count') }} </p>
							</div>
						</div>
				

						
						<hr>

						<div class="d-flex justify-content-between font-weight-700 text-16">
							<div>
								<p class="pl-4">Total Subscription Fees (incl GST): </p>
							</div>

							<div>
							@foreach(session('amount') as $total)
								<p class="pr-4">${{ $total->value * Session::get('properties_count') * 7 * 52 * 1.1}}</p>

							@endforeach

							</div>
						</div>
					</div>
				</div>
			
			</div>



	</div>
</div>
@push('scripts')
@if (Request::path() == 'facilities/stripe')
	<script src="https://js.stripe.com/v3/"></script>
@endif
<!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->

<script type="text/javascript">
	// Create a Stripe client
	var stripe = Stripe('{{$publishable}}');

	// Create an instance of Elements
	var elements = stripe.elements();

	// Custom styling can be passed to options when creating an Element.
	// (Note that this demo uses a wider set of styles than the guide below.)
	var style = {
		base: {
		color: '#32325d',
		lineHeight: '24px',
		fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
		fontSmoothing: 'antialiased',
		fontSize: '16px',
		'::placeholder': {
			color: '#aab7c4'
		}
		},
		invalid: {
		color: '#fa755a',
		iconColor: '#fa755a'
		}
	};

	// Create an instance of the card Element
	var card = elements.create('card', {style: style});

	// Add an instance of the card Element into the `card-element` <div>
	card.mount('#card-element');

	// Handle real-time validation errors from the card Element.
	card.addEventListener('change', function(event) {
		var displayError = document.getElementById('card-errors');
		if (event.error) {
		displayError.textContent = event.error.message;
		} else {
		displayError.textContent = '';
		}
	});

	// Handle form submission
	var form = document.getElementById('payment-form');
	form.addEventListener('submit', function(event) {
		event.preventDefault();

		stripe.createToken(card).then(function(result) {
		if (result.error) {
			// Inform the user if there was an error
			var errorElement = document.getElementById('card-errors');
			errorElement.textContent = result.error.message;
		} else {
			// Send the token to your server
			stripeTokenHandler(result.token);
		}
		});
	});

	function stripeTokenHandler(token) {
		// Insert the token ID into the form so it gets submitted to the server
		var form = document.getElementById('payment-form');
		var hiddenInput = document.createElement('input');
		hiddenInput.setAttribute('type', 'hidden');
		hiddenInput.setAttribute('name', 'stripeToken');
		hiddenInput.setAttribute('value', token.id);
		form.appendChild(hiddenInput);

		$("#stripe_btn").on("click", function (e)
        {
        	$("#stripe_btn").attr("disabled", true);
            e.preventDefault();
        });

        $(".spinner").removeClass('d-none');
        $("#save_btn-text").text("{{trans('messages.users_profile.save')}} ..");

		$("#payment-form").trigger("submit");

	}
	</script>
@endpush
@stop
