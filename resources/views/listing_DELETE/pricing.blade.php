	@extends('template')

	@section('main')
	<div class="margin-top-85">
		<div class="row m-0">
			<!-- sidebar start-->
			@include('users.sidebar')
			<!--sidebar end-->
			<div class="col-md-10">
				<div class="main-panel min-height mt-4">
					<div class="row justify-content-center">
						<div class="col-md-3 pl-4 pr-4">
							@include('listing.sidebar')
						</div>

						



						<div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
							<form id="lis_pricing" enctype="multipart/form-data" method="post" action="{{url('listing/'.$result->id.'/'.$step)}}" accept-charset='UTF-8'>

								{{ csrf_field() }}
								<div class="form-row mt-4 border rounded pb-4 m-0">
									<div class="form-group col-md-12 main-panelbg pb-3 pt-3 pl-4">
											<h4 class="text-16 font-weight-700">{{trans('messages.listing_price.base_price')}} <span id="listing_name" class="fa fa-info-circle secondary-text-color"
                                                    data-html="true" data-bs-toggle="tooltip" data-placement="top"
                                                    title="Only one file is allowed. Note that this will be viewed by seekers prior to requesting the booking."></span></h4>
									</div>

									<div class="form-group col-lg-6 pl-5 pr-5">
										<label for="listing_price_native">
											{{trans('messages.listing_price.night_price')}} 
											<span class="text-danger">*</span>
										</label>


										<div class="row">
											<div class="input-group text-16">
  												<input type="file" class="form-control text-16" id="pdfFile" name="file" aria-describedby="inputGroupFileAddon04" aria-label="Upload">
          										<input type="hidden" id="photo" type="text" name="photos">

  												<button class="btn btn-large btn-photo text-16" type="submit" id="pdfFile">
  												<i class="spinner fa fa-spinner fa-spin d-none"
                                                                           id="up_spin"></i>
                                                                       <span
                                                                            id="up_button_txt">{{ trans('messages.listing_description.upload') }}</span>
                                                </button>

                                               
											</div>
											  <blockquote class="blockquote text-13">
  													<p>Upload the Resident Agreement draft here in PDF format, ensure your fees, charges and services are clearly outlined. Uploaded file can be accessed by clicking on the link below.</p>
												</blockquote>
                                                             
                                        </div>


<div class="accordion accordion-flush" id="accordionFlushExample">
<div id="flush-collapseTwo" class="accordion-collapse collapse show" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
        <figure>
  
  <figcaption class="blockquote-footer">

    @if($result->agreement)
        <a href="{{ url($result->agreement) }}" class="link-primary" target="_blank">Uploaded Draft Resident Agreement</a>

    @else
        No file is attached to this listing yet. Note that by uploading a new file, previoud file will be replaced by it.
    @endif
    
  </figcaption>
</figure>
      </div>
    </div>
</div>





									</div>

									

									<!-- <div class="form-group col-md-12">
										@if($result->property_price->weekly_discount == 0 && $result->property_price->monthly_discount == 0)
							
												<figcaption class="blockquote-footer text-14">
  													<p>{{trans('messages.listing_price.access_offer')}}  </p>
												</figcaption>


										@endif
									</div> -->
								</div>

								


								<div class="mt-4 border rounded pb-4 m-0">
									<div class="form-group col-md-12 main-panelbg pb-3 pt-3 pl-4">
										<h4 class="text-16 font-weight-700">{{trans('messages.listing_price.additional_price')}}</h4>
									</div>
								

									

									<div class="col-md-12 pl-3 pr-3 pl-sm-5 pr-sm-5 mt-4">
										<label for="listing_cleaning_fee_native_checkbox" class="label-large label-inline">
											<input type="checkbox" class="pricing_checkbox" data-rel="security" {{(@$result->property_price->original_security_fee == 0)?'':'checked="checked"'}}>
											{{trans('messages.listing_price.security_deposit')}} 
										</label>
									</div>

									<div id="security" class="{{($result->property_price->original_security_fee == 0)?'display-off':''}}">
										<div class="col-md-12 pl-3 pr-3 pl-sm-5 pr-sm-5 mt-4">
											<div class="input-group">
												<div class="input-group mb-3">
													<div class="input-group-prepend">
														<span class="input-group-text text-16">$</span>
													</div>
													<input type="text" class="money-input text-16" data-extras="true" value="{{ $result->property_price->original_security_fee }}" id="price-security" name="security_fee" data-saving="additional-saving">
												</div>
											</div>
										</div>
									</div>

								

									<div id="weekend" class="{{($result->property_price->original_weekend_price == 0)?'display-off':''}}">
										<div class="col-md-12 pl-3 pr-3 pl-sm-5 pr-sm-5 mt-3">
											<div class="input-group">
												<div class="input-group mb-3">
													<div class="input-group-prepend">
														<span class="input-group-text text-16">$</span>
													</div>
													<input type="text" data-extras="true" value="{{ $result->property_price->original_weekend_price }}" id="price-weekend" name="weekend_price" class="text-16" data-saving="additional-saving">
												</div>
											</div>
										</div>
									</div>
								</div>



						
							<div class="row justify-content-between mt-4 mb-5">
								<div class="mt-4">
									<a  data-prevent-default="" href="{{ url('listing/'.$result->id.'/photos') }}" class="btn btn-outline-danger secondary-text-color-hover text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3">
									{{trans('messages.listing_description.back')}}
									</a>
								
									<a  data-prevent-default="" href="{{url('listing/'.$result->id.'/booking')}}" class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3" id="btn_next">
									<i class="spinner fa fa-spinner fa-spin d-none" id="btn_next_spinner"></i>
									<span id="btn_next-text">{{trans('messages.listing_basic.next')}}</span> 
									</a>
								</div>
							</div>






									</div>
								</div>
							</form>
						</div>
					</div>




				</div>
			</div>
		</div>
	</div>
	@stop



	@push('scripts')
	<!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->
	<script type="text/javascript">
		$(document).ready(function () {
                $('#lis_pricing').validate({
                    rules: {
                        'photos[]': {
                            required:true,
                            accept: "application/pdf"
                        }
                    },
                    submitHandler: function(form)
                    {
                        $("#pdfFile").on("click", function (e)
                        {
                            $("#pdfFile").attr("disabled", true);
                            e.preventDefault();
                        });

                        $("#up_spin").removeClass('d-none');
                        $("#up_button_txt").text("{{trans('messages.listing_description.upload')}}..");
                        return true;
                    },
                    messages: {
                        'photos[]': {
                            accept: "{{ __('messages.jquery_validation.image_accept') }}",
                        }
                    }
                });
            });


		
		$(document).on('change', '.pricing_checkbox', function(){
			if(this.checked){
			var name = $(this).attr('data-rel');
			$('#'+name).show();
			}else{
			var name = $(this).attr('data-rel');
			$('#'+name).hide();
			$('#price-'+name).val(0);
			}
		});

		$(document).on('click', '#show_long_term', function(){
			$('#js-set-long-term-prices').hide();
			$('#long-term-div').show();
		});

		$(document).on('change', '#price-select-currency_code', function(){
			//var currency = $(this).val();
			var dataURL = '{{url("currency-symbol")}}';

			var currency = 'AUD';
			var price = 99;

			console.log(currency);
			$.ajax({
			url: dataURL,
			data: {
					"_token": "{{ csrf_token() }}",
					'currency': currency
				},
			type: 'post',
			dataType: 'json',
			success: function (result) {
				if(result.success == 1)
				$('.pay-currency').html(result.symbol);
			},
			error: function (request, error) {
				// This callback function will trigger on unsuccessful action
				console.log(error);
			}
			});
		});
	</script>

	<script type="text/javascript">
		$(document).ready(function () {
			$('#lis_pricing').validate({
				rules: {
					price: {
						//required: true,
						number: true,
						min: 0,
						defaults: 99
					},
					weekly_discount: {
						number: true,
						max: 99,
						min: 0
					},
					monthly_discount: {
						number: true,
						max: 99,
						min: 0
					}
				},
				errorPlacement: function (error, element) {
					console.log('dd', element.attr("name"))
					if (element.attr("name") == "price") {
						error.appendTo("#price-error");
					} else {
						error.insertAfter(element)
					}
				},

				submitHandler: function(form)
	            {	           
	                $("#btn_next").on("click", function (e)
	                {	
	                	$("#btn_next").attr("disabled", true);
	                    e.preventDefault();
	                });
	                $(".spinner").removeClass('d-none');
	                $("#btn_next-text").text("{{trans('messages.listing_basic.next')}}..");
	                return true;
	            },
				messages: {
					price: {
						required:  "{{ __('messages.jquery_validation.required') }}",
						number: "{{ __('messages.jquery_validation.number') }}",
						min: "{{ __('messages.jquery_validation.min5') }}",
					},
					weekly_discount: {
						number: "{{ __('messages.jquery_validation.number') }}",
						max: "{{ __('messages.jquery_validation.max99') }}",
						min: "{{ __('messages.jquery_validation.min0') }}",
					},
					monthly_discount: {
						number: "{{ __('messages.jquery_validation.number') }}",
						max: "{{ __('messages.jquery_validation.max99') }}",
						min: "{{ __('messages.jquery_validation.min0') }}",
					}
				}
			});

		});
	</script>
	@endpush