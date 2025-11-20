@extends('template')

@section('main')

<div class="container margin-top-85 p-0 mb-5 min-height">
  <div class="panel-body text-success">
   <h6 class="text-16">{{trans('messages.trips_receipt.receipt')}} # {{ $booking->id }}</h6>
 </div>
 <div class="card">
  <div class="card-header pt-3 pb-4">
    <strong class="font-weight-700">{{trans('messages.trips_receipt.customer_receipt')}}</strong>
    <span class="float-right"> <strong class="font-weight-700">{{trans('messages.trips_receipt.confirmation_code')}} :</strong> {{ $booking->code }}</span>
  </div>

  <div class="card-body pt-0 pb-0 pl-4 pr-4">
    <div class="row mb-4 mt-5">
      <div class="col-md-6 l-pad-none p-0">
       <img src="{{@$logo}}">
     </div>

     <div class="col-md-6 print-div text-right p-0" id="print-div">
      <a href="#" onclick="print_receipt()" class="btn vbtn-outline-success text-14 font-weight-700 pt-2 pb-2 mt-2 pl-3 pr-4 button">PDF</a>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 col-sm-6 col-xs-12 mt-3 ow rpt pl-0">
      <div class="p-0">
        <span> <strong class="font-weight-700 text-18">{{trans('messages.trips_receipt.name')}} :</strong> {{ $booking->users->full_name }}</span>
      </div>
    </div>
    <div class="col-md-6 text-right pt-4 pr-0">
      <h4></h4>
    </div>
  </div>

  <div class="row rpt border pt-3 mb-5 mt-2">
    <div class="col-md-3 col-sm-3 col-xs-12"><!-- card pt-4 mb-5 mt-2 rounded-3 -->
      <h4 class="margin-top20"><strong>{{trans('messages.trips_receipt.accommodatoin_address')}}</strong></h4>
      <h5 class="margin-top20"><p class="text-lead">
        <strong>{{ @$booking->properties->name }}</strong>
      </p>
      <p class="text-lead">{{ @$booking->properties->property_address->address_line_1 }}<br>{{ @$booking->properties->property_address->city }}, {{ @$booking->properties->property_address->state }} {{ @$booking->properties->property_address->postal_code }}<br>{{ @$booking->properties->property_address->country_name }}<br></h5>
      </div>
      <div class="col-md-3 col-sm-3 col-xs-12">
        <h4><strong>{{trans('messages.trips_receipt.travel_destination')}}</strong></h4>
        <h5 class="margin-top20">{{ @$booking->properties->property_address->city }}</h5>
        <h4 class="margin-top20"><strong>{{trans('messages.trips_receipt.accommodation_host')}}</strong></h4>
        <h5 class="margin-top20">{{ @$booking->properties->users->full_name }}</h5>
      </div>

      <div class="col-md-3 col-sm-3 col-xs-12">
        <h4><strong>{{trans('messages.trips_receipt.duration')}}</strong></h4>
        <h5 class="margin-top20">{{ $booking->total_night }} {{trans('messages.trips_receipt.night')}}</h5>
        <h4 class="margin-top20"><strong>{{trans('messages.trips_receipt.check_in')}}</strong></h4>
        <h5 class="margin-top20">{{ $booking->startdate_dmy }}<br>{{trans('messages.trips_receipt.flexible_check_time')}}</h5>
      </div>

      <div class="col-md-3 col-sm-3 col-xs-12">
        <h4><strong>{{trans('messages.trips_receipt.accommodation_type')}}</strong></h4>
        <h5 class="margin-top20">{{ @$booking->properties->property_type_name }}</h5>
        <h4 class="margin-top20"><strong>{{trans('messages.trips_receipt.check_out')}}</strong></h4>
        <h5 class="margin-top20">{{ $booking->enddate_dmy }}<br>{{trans('messages.trips_receipt.flexible_check_out')}}</h5>
      </div>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-bordered table-hover p-0 m-0">
        <thead class="thead-dark">
          <tr>
            <th colspan="6">{{trans('messages.trips_receipt.booking_charge')}}</th>
          </tr>
        </thead>
        <tbody class="border">
        
          <tr>
            <td>{{ $site_name }} {{trans('messages.trips_receipt.service_fee')}}</td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->service_charge !!}</td>
          </tr>
          <tr>
            <td>{{ $booking->total_night }} {{trans('messages.trips_receipt.night')}}</td>
            <td class="text-right pr-4">{!! $booking->total_night !!}</td>
          </tr>
          @if($booking->guest_charge)
          <tr>
            <td class=""> {{trans('messages.trips_receipt.additional_guest_fee')}} </td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->guest_charge !!}</td>
          </tr>
          @endif
          @if($booking->cleaning_charge)
          <tr>
            <td class=""> {{trans('messages.trips_receipt.cleaning_fee')}} </td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->cleaning_charge !!}</td>
          </tr>
          @endif
          @if($booking->security_money)
          <tr>
            <td class=""> {{trans('messages.trips_receipt.security_fee')}} </td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->security_money !!}</td>
          </tr>
          @endif
          @if($booking->iva_tax)
          <tr>
            <td class=""> GST (10%)  </td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->iva_tax !!}</td>
          </tr>
          @endif
           @if($booking->accomodation_tax)
          <tr>
            <td class="">Accomadation Tax </td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->accomodation_tax !!}</td>
          </tr>
          @endif
          
          <tr>
            <td>{{trans('messages.trips_receipt.total')}}</td>
            <td class="text-right pr-4">{!! $booking->currency->symbol.$booking->total !!}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="row">
      <div class="col-lg-3 col-sm-5 ml-auto pr-0">
        <table class="table table-clear">
          <tbody>
            <tr>
              <td class="left">
                <strong>{{trans('messages.trips_receipt.payment_received')}}:{{ $booking->receipt_date }}</strong>
              </td>
              <td class="text-right pr-4"> $0</td>
            </tr>

          </tbody>
        </table>

      </div>

    </div>

  </div>
</div>
</div>

<script ttype="text/javascript">
  function print_receipt()
  {
    document.getElementById("print-div").classList.add("d-none");
    document.getElementById("footer").classList.add("d-none");
    window.print();

     $("#print-div").removeClass("d-none");
  }

</script>
@stop
