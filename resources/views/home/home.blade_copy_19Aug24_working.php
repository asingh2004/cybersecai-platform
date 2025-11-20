@extends('template')
@push('css')
	<link rel="stylesheet" type="text/css" href="{{ url('public/css/daterangepicker.min.css')}}" />
    <style>
        .vbtn-outline-success:hover {
            background: #1dbf73 !important;
        }

        .btn-outline-danger:hover {
            background: #dc3545 !important;
        }
    </style>
@endpush

@section('main')
	<input type="hidden" id="front_date_format_type" value="{{ Session::get('front_date_format_type')}}">


<section class="hero-banner magic-ball">
		<div class="main-banner"  style="background-image: url('{{ defined("BANNER_URL") ? BANNER_URL : '' }}');">

			<div class="container">

				<div class="row align-items-center text-center text-md-left">
					<div class="col-md-6 col-lg-5 mb-5 mb-md-0">
						<div class="main_formbg item animated zoomIn mt-80">
							<h1 class="pt-4 ">{{trans('messages.home.make_your_reservation')}}</h1>
							<form id="front-search-form" method="post" action="{{url('search')}}">
								{{ csrf_field() }}
								<div class="row">
									<div class="col-md-12">
										<div class="input-group pt-4">
											<input class="form-control p-3 text-14" id="front-search-field" placeholder="{{trans('messages.home.where_want_to_go')}}" autocomplete="off" name="location" type="text" required>
										</div>
									</div>

									<div class="col-md-12">
										<div class="d-flex" id="daterange-btn">
											<div class="input-group mr-2 pt-4" >
												<input class="form-control p-3 border-right-0 border text-14 checkinout" name="checkin" id="startDate" type="text" placeholder="{{trans('messages.search.check_in')}}" autocomplete="off" required>
											</div>

											<div class="input-group ml-2 pt-4">
												<input class="form-control p-3 border-right-0 border text-14 checkinout" name="checkout" id="endDate" placeholder="{{trans('messages.search.check_out')}}" type="text"  required>
											</div>
										</div>
									</div>



									@if(!$respite_type->isEmpty())
    									<div class="col-md-12">
        									<div class="input-group pt-4 text-14">
            									
            									<select class="form-select form-control" name="respite_type" id="respite_type" required>
                								<option value=""> Choose an AI Feature </option>
                									@foreach($respite_type as $respite)
                    									<option value="{{$respite->name}}">{{$respite->name}}</option>
                									@endforeach
            									</select>
        									</div>
    									</div>
									@endif




									</div>

									<div class="col-md-12 front-search mt-5 pb-3 pt-4">
										<button type="submit" class="btn vbtn-default btn-block p-3 text-16">{{trans('messages.home.search')}}</button>
                                      
                                       <p>Click the link below to call the Coding Companion AI:</p>
    									<a href="{{ url('/math-tutor') }}">Your Coding Companion for Python , Php and More!</a>
                                      
                                      <p>Click the link below to summarise your pdf:</p>
    									<a href="{{ route('pdf.index') }}">Go to PDF Summariser AI</a>
                                      
                                      <p>Click the link below to summarise multipel web blogs:</p>
    									<a href="{{ url('/blog-summarizer') }}">Go to Web Blogs Summariser AI</a>
                                   
									</div>
								</form>
							</div>
							
						</div>
					</div>
					</div>
			</div>
			
	</section>



	@if(!$properties->isEmpty())
		<section class="recommandedbg bg-gray mt-4 magic-ball magic-ball-about pb-5">
			<div class="container-fluid container-fluid-90">
				<div class="row">
					<div class="recommandedhead section-intro text-center mt-70">
						<p class="item animated fadeIn text-24 font-weight-700 m-0">{{trans('messages.home.recommended_home')}}</p>
						<p class="mt-2">{{trans('messages.home.recommended_slogan')}}</p>
						<!--<p class="mt-2"> Welcome to MyRespiteAccom, your trusted source for booking online Respite Care stay in Australia. MyRespiteAccom is a new way of booking system that provides a break.</p>

						<p class="mt-2">Respite care is a critical service that offers Peace of Mind to Carers and the Person receiving care. It allows carers to take much-needed breaks, relieving the physical and emotional demands of caregiving. This support is available through various Types of Respite care, including NDIS respite and Residential respite care, catering to different needs and preferences.</p>


						<p class="mt-2">To Find Respite Care, you can start by exploring our online platform where you can find the information and access for booking online Respite Care in popular Australian cities including Sydney, Adelaide, Brisbane, Canberra, Perth, Melbourne and Darwin. Whether you need a short break to recharge or extended Residential Respite Care for up to 63 Days, with our platform you can connect you with Local Area Respite Care and Aged Care Homes providers in Australia. </p>
						-->

					</div>
				</div> 

				<div class="row mt-5">
					@foreach($properties as $property)
					<div class="col-md-6 col-lg-4 col-xl-3 pl-3 pr-3 pb-3 mt-4">
						<div class="card h-100 card-shadow card-1">
							<div class="grid">
								<a href="properties/{{ $property->slug }}" aria-label="{{ $property->name}}">
									<figure class="effect-milo">
										<img src="{{ $property->cover_photo }}" class="room-image-container200" alt="{{ $property->name}}"/>
										<figcaption>
										</figcaption>
									</figure>
								</a>
							</div>

							<div class="card-body p-0 pl-1 pr-1">
								<div class="d-flex">

									<div class="p-2 text">
										<a class="text-color text-color-hover" href="properties/{{ $property->slug }}">
											<p class="text-16 font-weight-700 text"> {{ $property->name}}</p>
										</a>
										<p class="text-13 mt-2 mb-0 text"><i class="fas fa-map-marker-alt"></i> {{$property->property_address->city}}, {{$property->property_address->state}}</p>
									</div>
								</div>

								<div class="review-0 p-3">
									<div class="d-flex justify-content-between">

										<div class="d-flex">
                                            <div class="d-flex align-items-center">Review: 
											<span><i class="fa fa-star text-14 secondary-text-color"></i>
												@if( $property->guest_review)
                                                    {{ $property->avg_rating }}
                                                @else
                                                    0
                                                @endif
                                                ({{ $property->guest_review }})</span>
                                            </div>

                                            <div class="">
                                                @auth
                                                    <a class="btn btn-sm book_mark_change"
                                                       data-status="{{$property->book_mark}}" data-id="{{$property->id}}"
                                                       style="color:{{($property->book_mark == true) ? '#1dbf73':''}}; ">
                                                    <span style="font-size: 22px;">
                                                        <i class="fas fa-heart pl-2"></i>
                                                    </span>
                                                    </a>
                                                @endauth
                                            </div>
                                        </div>


										<div>
											<span class="font-weight-700">{{ $property->respite_service_type }}</span>
										</div>
									</div>
								</div>

								<div class="card-footer text-muted p-0 border-0">
									<div class="d-flex bg-white justify-content-between pl-2 pr-2 pt-2 mb-3">
										<div>
											<ul class="list-inline">
												<li class="list-inline-item  pl-4 pr-4 border rounded-3 mt-2 bg-light text-dark">
														<div class="vtooltip"> <i class="fas fa-user-friends"></i> {{ $property->accommodates }}
														<span class="vtooltiptext text-14">{{ $property->accommodates }} {{trans('messages.property_single.guest')}}</span>
													</div>
												</li>

												<li class="list-inline-item pl-4 pr-4 border rounded-3 mt-2 bg-light">
													<div class="vtooltip"> <i class="fas fa-bed"></i> {{ $property->bedrooms }}
														<span class="vtooltiptext  text-14">{{ $property->bedrooms }} {{trans('messages.property_single.bedroom')}}</span>
													</div>
												</li>

												<li class="list-inline-item pl-4 pr-4 border rounded-3 mt-2 bg-light">
													<div class="vtooltip"> <i class="fas fa-bath"></i> {{ $property->bathrooms }}
														<span class="vtooltiptext  text-14 p-2">{{ $property->bathrooms }} {{trans('messages.property_single.bathroom')}}</span>
													</div>
												</li>
											</ul>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</section>
	@endif


@if(!$testimonials->isEmpty())
	<section class="testimonialbg pb-70">
		<div class="testimonials">
			<div class="container">
				<div class="row">
					<div class="recommandedhead section-intro text-center mt-70">
						<p class="animated fadeIn text-24 text-color font-weight-700 m-0">{{ trans('messages.home.say_about_us') }}</p>
						<p class="mt-2">{{trans('messages.home.people_say')}}</p>
					</div>
				</div>

				<div class="row mt-5">
					@foreach($testimonials as $testimonial)
					<?php $i = 0; ?>
						<div class="col-md-4 mt-4">
							<div class="item h-100 card-1">
								<img src="{{$testimonial->image_url}}" alt="{{$testimonial->name}}">
								<div class="name">{{$testimonial->name}}</div>
									<small class="desig">{{$testimonial->designation}}</small>
									<p class="details">{{ substr($testimonial->description, 0, 200) }} </p>
									<ul>
										@for ($i = 0; $i < 5; $i++)
											@if($testimonial->review >$i)
												<li><i class="fa fa-star secondary-text-color" aria-hidden="true"></i></li>
											@else
												<li><i class="fa fa-star rating" aria-hidden="true"></i></li>
											@endif
										@endfor
									</ul>
							</div>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</section>
	@endif

				<!--<div class="row">
					<div class="recommandedhead section-intro text-center mt-70">
						<p class="animated fadeIn text-24 text-color font-weight-700 m-0">{{ trans('messages.home.partners') }}</p>
					</div>
				</div> -->


   <!-- <div class="container">
  		<div class="row row-cols-3">
            <div>
            	<a class="navbar-brand logo_h" aria-label="logo" href="https://nationalseniors.com.au/advocacy/our-campaigns" target="_blank"><img src="public/front/images/banners/NSA_Logo_Colour.png" alt="nsa logo" class="img-fluid"></a>
         		</div>

            <div>
            
             
             <a class="navbar-brand logo_h" aria-label="logo" href="https://lasa.asn.au/" target="_blank"><img src="public/front/images/banners/ACCPA Industry-Partner-Logo.jpg" alt="lasa logo" class="img-fluid" align="center"></a>
          </div>

        	
           <div>
            <a class="navbar-brand logo_h" aria-label="logo" href="https://apod.com.au/" target="_blank"><img src="public/front/images/banners/APOD Partner Logo_WhiteBG_Transparent_Large.png" alt="apod logo" class="img-fluid"></a>
          </div>
        <div>
           
         </div>
        <div>
           <a class="navbar-brand logo_h" aria-label="logo" href="https://secure.communities.qld.gov.au/chiip/businessSearch/SearchDetails.aspx?OutletID=37333" target="_blank"><img src="public/front/images/banners/Seniors-SeniorsBusiness-Carer_web.jpg" alt="seniors card" class="img-fluid"></a>
         </div>
     
    
  		</div>
	</div>-->




	<!--<section class="recommandedbg bg-gray mt-4 magic-ball magic-ball-about pb-5">
			<div class="container-fluid container-fluid-90">
				<div class="row">
					<div class="recommandedhead section-intro text-center mt-70">
						<p class="item animated fadeIn text-24 font-weight-700 m-0">Do you have any question?</p>
						<p class="mt-2">Simply type your question and it will be answered by OpenAi. This is a Beta trial & hence the result may not be accurate.</p>
					

					<div id="chatbot-container"></div>
						<form onsubmit="submitForm(event)">
    						<input type="text" id="chatbot-input" placeholder="Ask me something...ie contact details Manor Court Werribee">
    						<input type="submit" value="Send to OpenAI">
						</form>
					<p id="response"></p>
					</div>
				</div>
			</div>
	</section>-->
		
			<script>

			// Function to handle form submission
			function submitForm(event) {
    			event.preventDefault(); // prevent page refresh
        		//const prompt = "What is the capital of France?";
        		// Get the input value from the user
    			const input = document.getElementById("chatbot-input").value;
        		const temperature = 0.9;
        		const apiKey = "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";
        
        		fetch("https://api.openai.com/v1/engines/text-davinci-003/completions", {
            		method: "POST",
            		headers: {
                		"Content-Type": "application/json",
                		"Authorization": `Bearer ${apiKey}`
            		},
            	body: JSON.stringify({
                	prompt: input + " Australia",
                	temperature: temperature,
                	max_tokens:150,
                	stop: input
            	})
        	})
        	.then(response => response.json())
        	.then(data => {
            	const responseText = data.choices[0].text;
            	document.getElementById("response").innerHTML = responseText;
        	})
        	.catch(error => console.log(error));
    		}
			</script>



	@if(!$starting_cities->isEmpty())
	<section class="bg-gray mt-70 pb-2">
		<div class="container-fluid container-fluid-90">
			<div class="row">
				<div class="section-intro text-center">
					<p class="item animated fadeIn text-24 font-weight-700 m-0 text-capitalize">{{trans('messages.home.top_destination')}}</p>
					<p class="mt-3">{{trans('messages.home.destination_slogan')}} </p>
				</div>
			</div>

			<div class="row mt-2">
				@foreach($starting_cities as $city)
				<div class="col-md-4 mt-5">
				<a href="{{URL::to('/')}}/search?location={{ $city->name }}">
						<div class="grid item animated zoomIn">
							<figure class="effect-ming">
								<img src="{{ $city->image_url }}" alt="city"/>
									<figcaption>
										<p class="text-18 font-weight-700 position-center">{{$city->name}}</p>
									</figcaption>
							</figure>
						</div>
					</a>
				</div>
				@endforeach
			</div>
		</div>
	</section>
	@endif



	
@stop

@push('scripts')


	

	<!--<script type="text/javascript" src='https://maps.google.com/maps/api/js?key={{ @$map_key }}&libraries=places'></script>-->

	<script type="text/javascript" src='https://maps.googleapis.com/maps/api/js?key={{ @$map_key }}&callback=Function.prototype&libraries=places'></script>
	

	

	<script type="text/javascript" src="{{ url('public/js/moment.min.js') }}"></script>
    @auth
        <script src="{{ url('public/js/sweetalert.min.js') }}"></script>
    @endauth

	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js" integrity="sha512-mh+AjlD3nxImTUGisMpHXW03gE6F4WdQyvuFRkjecwuWLwD2yCijw4tKA3NsEFpA1C3neiKhGXPSIGSfCYPMlQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<script type="text/javascript" src="{{ url('public/js/front.js') }}"></script>
	<script type="text/javascript" src="{{ url('public/js/daterangecustom.js')}}"></script>
	<script type="text/javascript">
		$(function() {
			dateRangeBtn(moment(),moment(), null, '{{$date_format}}');
		});

        @auth
        $(document).on('click', '.book_mark_change', function(event){
            event.preventDefault();
            var property_id = $(this).data("id");
            var property_status = $(this).data("status");
            var user_id = "{{Auth::id()}}";
            var dataURL = APP_URL+'/add-edit-book-mark';
            var that = this;
            if (property_status == "1")
            {
                var title = "{{trans('messages.favourite.remove')}}";

            } else {

                var title = "{{trans('messages.favourite.add')}}";
            }

            swal({
                title: title,
                icon: "warning",
                buttons: {
                    cancel: {
                        text: "{{trans('messages.general.no')}}",
                        value: null,
                        visible: true,
                        className: "btn btn-outline-danger text-16 font-weight-700  pt-3 pb-3 pl-5 pr-5",
                        closeModal: true,
                    },
                    confirm: {
                        text: "{{trans('messages.general.yes')}}",
                        value: true,
                        visible: true,
                        className: "btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3 pl-5 pr-5",
                        closeModal: true
                    }
                },
                dangerMode: true,
            })
                .then((willDelete) => {
                    if (willDelete) {

                        $.ajax({
                            url: dataURL,
                            data:{
                                "_token": "{{ csrf_token() }}",
                                'id':property_id,
                                'user_id':user_id,
                            },
                            type: 'post',
                            dataType: 'json',
                            success: function(data) {

                                $(that).removeData('status')
                                if(data.favourite.status == 'Active') {
                                    $(that).css('color', '#1dbf73');
                                    $(that).attr("data-status", 1);
                                    swal('success', '{{trans('messages.success.favourite_add_success')}}');

                                } else {
                                    $(that).css('color', 'black');
                                    $(that).attr("data-status", 0);
                                    swal('success', '{{trans('messages.success.favourite_remove_success')}}');


                                }
                            }
                        });

                    }
                });
        });
        @endauth
	</script>




@endpush

