@extends('admin.template')
@section('main')
  <div class="content-wrapper">
         <!-- Main content -->
  <section class="content-header">
          <h1>{{trans('messages.listing_price.base_price')}}</h1>
        <ol class="breadcrumb">
    <li><a href="{{url('/')}}/admin/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    </ol>
  </section>
<section class="content">
<div class="row">
        <div class="col-md-3 settings_bar_gap">
          @include('admin.common.property_bar')
        </div>

   <div class="col-md-9">
    <div class="box box-info">
    <div class="box-body">
    <form id="listing_pricing" method="post" action="{{url('admin/listing/'.$result->id.'/'.$step)}}" class='signup-form login-form' accept-charset='UTF-8'>
      {{ csrf_field() }}
      
        <div class="row">
          <div class="col-md-12">
           <h4>{{trans('messages.listing_price.base_price')}}</h4>
          </div>
        </div>
        

        <div class="row">
          <div class="col-md-8">

                  <input class="form-control text-16" name="file" id="photo_file"
                                                                           type="file" value="">
                  <input type="hidden" id="photo" type="text" name="photos">
                  <input type="hidden" name="img_name" id="img_name">
                  <input type="hidden" name="crop" id="type" value="crop">
                  <p class="text-13">(Upload the Resident Agreement draft here in PDF format, ensure your fees and charges are clearly outlined)
                   </p>
                  <div id="result" class="hide">
                                <img src="#" alt="">
                  </div>

             </div>
              <div class="col-md-3">
                     <button type="submit"
                           class="btn btn-large btn-photo text-16" id="up_button">
                       <i class="spinner fa fa-spinner fa-spin d-none"
                                   id="up_spin"></i>
                       <span
                          id="up_button_txt">{{ trans('messages.listing_description.upload') }}</span>

                         </button>
              </div>
          
          </div>
          


          <div class="col-md-8">
            @if($result->property_price->weekly_discount == 0 && $result->property_price->monthly_discount == 0)
              <p id="js-set-long-term-prices" class="row-space-top-6 text-center text-muted set-long-term-prices">
               {{trans('messages.listing_price.access_offer')}} 
              </p>
              <hr class="row-space-top-6 row-space-5 set-long-term-prices">
            @endif
          </div>
        </div>

                  
      
        <div class="row">
           
          <div class="col-md-12">
            <label for="listing_cleaning_fee_native_checkbox" class="label-large label-inline">
              <input type="checkbox" class="pricing_checkbox" data-rel="security" {{(@$result->property_price->original_security_fee == 0)?'':'checked="checked"'}}>
              &nbsp
             {{trans('messages.listing_price.security_deposit')}}
            </label>
          </div>

          <div id="security" class="{{($result->property_price->original_security_fee == 0)?'display-off':''}}">
            <div class="col-md-12">
              <div class="col-md-4 l-pad-none">
                <div class="input-addon">
                  <span class="input-prefix pay-currency">$</span>
                  <input type="text" class="money-input" data-extras="true" value="{{ $result->property_price->original_security_fee }}" id="price-security" name="security_fee" class="autosubmit-text input-stem input-large" data-saving="additional-saving">
                </div>
              </div>
            </div>
          </div>
          
        </div>



        <div class="row">
          <div class="d-grid gap-2 d-md-block">
          <!-- <div class="col-md-12">
          <div class="col-md-10 col-sm-6 col-xs-6 text-left"> -->
            <a data-prevent-default="" href="{{ url('admin/listing/'.$result->id.'/photos') }}" class="btn btn-large btn-primary">{{trans('messages.listing_description.back')}}</a>

          <!-- </div> -->
          <!-- <div class="col-md-2 col-sm-6 col-xs-6 text-right"> -->

            <a  data-prevent-default="" href="{{url('admin/listing/'.$result->id.'/booking')}}" class="btn btn-large btn-primary next-section-button" id="btn_next">
                  <i class="spinner fa fa-spinner fa-spin d-none" id="btn_next_spinner"></i>
                  <span id="btn_next-text">{{trans('messages.listing_basic.next')}}</span> 
            </a>

         <!-- </div> -->

          </div>
        </div>





    
    </form>
    </div>
    </div>
    </div>
    </div>
    </section>
  </div>

@push('scripts')
  <script type="text/javascript">
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
      var currency = $(this).val();
      var dataURL = '{{url("currency-symbol")}}';
      //console.log(currency);
      $.ajax({
        url: dataURL,
        data: {'currency': currency},
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
@endpush
@stop

@section('validate_script')
<script type="text/javascript">
   $(document).ready(function () {

            $('#listing_pricing').validate({
                rules: {
                    price: {
                        required: true
                    }
                }
            });

        });
</script>
@endsection
