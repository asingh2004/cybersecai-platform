@extends('template')

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        @include('users.sidebar')

        <div class="col-lg-10">
            <div class="main-panel">
                

                <div class="container-fluid min-height"> 
                    <div class="row">




                        <div class="col-md-12 p-0 mb-3">
                            <div class="list-bacground mt-4 rounded-3 p-4 border">



<span class="text-18 pt-4 pb-4 font-weight-700">{{trans('messages.facility.account_summary')}}</span>

<form action="{{URL::to('facilities/create_booking')}}" method="post" id="payment-form">
{{ csrf_field() }}



<div class="accordion accordion-flush" id="accordionFlushExample">
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingOne">
      <button class="accordion-button btn-lg vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="true" aria-controls="flush-collapseOne">
        <strong> {{trans('messages.facility.account_summary')}} </strong>
      </button>
    </h2>
    <div id="flush-collapseOne" class="accordion-collapse collapse show" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
           <span class="text-18 pt-4 pb-4 font-weight-700">{{trans('messages.facility.account_summary')}}</span>
                                    @foreach($business_name as $bname)
                                        <div class="d-flex gap-3 mt-4"> 
                                            <div class="pr-4">
                                                <i class="fas fa-id-badge">
                                                <span class="text-14 pt-2 pb-2 font-weight-700">{{trans('messages.facility.account_id')}}: </span>  </i>  
                                                    {{$bname->email}}
                                                        
                                            </div>
                                        </div>

                                        <div class="d-flex gap-3 mt-4"> 
                                            <div class="pr-4">
                                                <i class="fas fa-user">
                                                <span class="text-14 pt-2 pb-2 font-weight-700">{{trans('messages.facility.business_name')}}: </span> </i> 
                                                        
                                                        {{$bname->first_name}} {{$bname->last_name}}
                                                         
                                            </div>
                                        </div>

                                        <div class="d-flex gap-3 mt-4"> 
                                            <div class="pr-4">
                                                <i class="fas fa-phone"> 
                                                <span class="text-14 pt-2 pb-2 font-weight-700">{{trans('messages.facility.phone_number')}}: </span> </i> 
                                                        
                                                {{$bname->formatted_phone}}
                                                         
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    <div class="d-flex gap-3 mt-4 p-4">
                                    <i class="fa fa-pencil" aria-hidden="true"></i>
                                        <a class="text-color font-weight-500 mt-1" href="{{ url('users/profile') }}">
                   
                                            {{trans('messages.utility.edit_profile')}}
                                        </a>
                        
                                    </div>

      </div>
    </div>
  </div>



@if($facilities_cnt >=1)                                           
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingTwo">
      <button class="accordion-button btn-lg vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="true" aria-controls="flush-collapseTwo">
        <strong> Facilities/ Properties attached to this User Account</strong>
      </button>
    </h2>
    <div id="flush-collapseTwo" class="accordion-collapse collapse show" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
        <span class="text-18 pt-4 pb-4 font-weight-700">Facilities/ Properties attached to this User Account</span>
          @forelse($facilities as $facility)
                                        <div class="d-flex gap-3 mt-4"> 
                                            <div class="pr-4">
                                                <i class="fas fa-building">
                                                <span class="text-14 pt-2 pb-2 font-weight-700">{{trans('messages.facility.address')}} </span>  </i>  
                                                    {{$facility->address_line_1}}
                                                        
                                            </div>
                                        </div>

                                    @empty
                                        <div class="row justify-content-center position-center w-100 p-4 mt-4">
                                        <div class="text-center w-100">
                                                <img src="{{ url('public/img/unnamed.png')}}" class="img-fluid"   alt="Not Found">
                                            <p class="text-center">{{trans('messages.message.no_property')}}</p>
                                        </div>
                                        </div>
                                    @endforelse
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingThree">
      <button class="accordion-button btn-lg vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="true" aria-controls="flush-collapseThree">
        <strong> {{trans('messages.facility.subscription_status')}}</strong>
      </button>
    </h2>
    <div id="flush-collapseThree" class="accordion-collapse collapse show" aria-labelledby="flush-headingThree" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
          <span class="text-18 pt-4 pb-4 font-weight-700">{{trans('messages.facility.subscription_status')}}</span>
          @forelse($business_name as $bname)
                                        <div class="d-flex gap-3 mt-4"> 
                                            <div class="pr-4">

                                            @if($bname->subscription_status =='inactive') 


                                                <button type="button" class="btn btn-lg text-white btn-danger vbtn-outline-success" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Your subscription is not active, please pay subscription fees to enjoy unlimited listings at the properties listed here."> Subscription is Inactive!
                                            </button> 


                                            @if($supplier_type =='RACF')

                                            @foreach($daily_subscription_fee as $subfee)
                                            <div class="d-flex gap-3 mt-4"> 
                                            
                                               <span class="text-16 pt-4 pb-1 font-weight-600">Subscription Fee Breakdown:</span> 
                                           </div>
                                           <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                Subscription Fees - daily fee per property: ${{$subfee->value}}
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                Number of Properties (as shown above): {{ $facilities_cnt }} properties
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                GST (10%): ${{ 0.1 * $subfee->value * 7 * 52 * $facilities_cnt}}

                                                @php $subscription_total = 0.1 * $subfee->value * 7 * 52 * $facilities_cnt; @endphp
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <span class="text-16 pt-4 pb-1 font-weight-700"><strong> Total Annual Subscription Fee for this Account (incl GST): ${{ $subfee->value * 7 * 52 * 1.1 * $facilities_cnt }} </strong></span> 
                                            </div>
                                            </div>
                                            </div>
                                           @endforeach

                                           @else
                                            @foreach($daily_ndis_subscription_fee as $subfee)
                                            <div class="d-flex gap-3 mt-4"> 
                                            
                                               <span class="text-16 pt-4 pb-1 font-weight-600">Subscription Fee Breakdown:</span> 
                                           </div>
                                           <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                Subscription Fees - daily fee per property: ${{$subfee->value}}
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                Number of Properties (as shown above): {{ $facilities_cnt }} properties
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <div class="pr-4">
                                                GST (10%): ${{ 0.1 * $subfee->value * 7 * 52 * $facilities_cnt}}

                                                @php $subscription_total = 0.1 * $subfee->value * 7 * 52 * $facilities_cnt; @endphp
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 mt-4"> 
                                               <span class="text-16 pt-4 pb-1 font-weight-700"><strong> Total Annual Subscription Fee for this Account (incl GST): ${{ $subfee->value * 7 * 52 * 1.1 * $facilities_cnt }} </strong></span> 
                                            </div>
                                            </div>
                                            </div>
                                           @endforeach
                                           @endif

                                            



<div class="accordion accordion-flush" id="accordionFlushPay">
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingOnePay">
      <button class="accordion-button btn-primary btn-lg vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
        
        <span class="text-16 pt-4 pb-1 font-weight-700"><strong> Pay Now </strong></span> 
      </button>
    </h2>
    <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushPay">
      <div class="accordion-body">

        

            <div class="col-sm-12 p-0">
                        <label for="exampleInputEmail1">{{ trans('messages.payment.payment_type') }}</label>
            </div>

            

                    <div class="col-sm-12 p-0 pb-3">
                        <select name="payment_method" class="form-control mb20" id="payment-method-select">
         
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
               
                    </div>

                

                    <div class="d-flex gap-3 mt-4"> 
                        <div class="d-grid gap-2 col-6 mx-auto">
                            <button id="payment-form-submit" class="h2 btn btn-primary btn-lg" type="submit">Pay</button>
                        </div>
                    </div>

            <!--</form>-->


                                    
      </div>
    </div>
  </div>
</div>
                            
                                            @else
                                                <button type="button" class="btn btn-lg text-white btn-success vbtn-outline-success" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Your subscription is active, enjoy unlimited listings at the properties listed above!">Subscription is Active!
                                                </button>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-3 mt-4"> 
                                            
                                               <span class="text-16 pt-4 pb-1 font-weight-600">Your Subscription Expires On: {{ $bname->sub_renewal_date}}</span> 
                                           </div>

                                            <div class="accordion accordion-flush" id="accordionFlushPay">
  <div class="accordion-item">
    <h4 class="accordion-header" id="flush-headingOnePay">
      <button class="accordion-button btn-primary btn-lg vbtn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
        
        <span class="text-12 pt-4 pb-1 font-weight-400"><strong> Renew Now </strong></span> 
      </button>
    </h2>
    <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushPay">
      <div class="accordion-body">

        

            <div class="col-sm-12 p-0">
                        <label for="exampleInputEmail1">{{ trans('messages.payment.payment_type') }}</label>
            </div>

            

                    <div class="col-sm-12 p-0 pb-3">
                        <select name="payment_method" class="form-control mb20" id="payment-method-select">
         
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
               
                    </div>

                                             <div class="d-flex gap-3 mt-4"> 
                                                <div class="d-grid gap-2 col-6 mx-auto">
                                                <button id="payment-form-submit" class="h3 btn btn-primary btn-lg" type="submit">Pay</button>
                                                </div>
                                            </div>

            </form>
            </div>
    </div>
  </div>
</div>

                         
                                            @endif
                                                 
                                            </div>
                                        </div>

                                    @empty
                                        <div class="row justify-content-center position-center w-100 p-4 mt-4">
                                        <div class="text-center w-100">
                                                <img src="{{ url('public/img/unnamed.png')}}" class="img-fluid"   alt="Not Found">
                                            <p class="text-center">Subscription does not apply to you!</p>
                                        </div>
                                        </div>
                                    @endforelse

      </div>
    </div>
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
    </div>



@push('scripts')

<script src="https://js.stripe.com/v3/"></script>

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
    </script>
@endpush
@stop



