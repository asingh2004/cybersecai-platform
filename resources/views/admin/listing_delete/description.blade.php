@extends('admin.template')
@section('main')
  <div class="content-wrapper">
         <!-- Main content -->
  <section class="content-header">
      <h1>
          Description
          <small>Description</small>
      </h1>
      <ol class="breadcrumb">
        <li>
          <a href="{{url('/')}}/admin/dashboard"><i class="fa fa-dashboard"></i> Home</a>
        </li>
      </ol>
  </section>

  <section class="content">
      <div class="col-md-3 settings_bar_gap">
        @include('admin.common.property_bar')
      </div>

      <div class="col-md-9">
      <form id="list_des" method="post" action="{{url('admin/listing/'.$result->id.'/'.$step)}}" class='signup-form login-form' accept-charset='UTF-8'>
        {{ csrf_field() }}

      <div class="box box-info">
      <div class="box-body">

          <div class="row">
            <div class="col-md-8 col-sm-12 col-xs-12 mb20">
              <label class="label-large">{{trans('messages.listing_description.listing_name')}} 
                <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" value=" {{ old('name', $description->properties->name)  }}" placeholder="" maxlength="300">
              <!-- <span class="text-danger">{{ $errors->first('name') }}</span> -->
            </div>
          </div>
          <div class="row">
            <div class="col-md-8  col-sm-12 col-xs-12 mb20">
              <label class="label-large">{{trans('messages.listing_description.summary')}} 
                <!-- <span class="text-danger">*</span> -->
              </label>
              <textarea class="form-control" name="summary" rows="6" placeholder="" ng-model="summary">{{ old('summary', $description->summary)  }}</textarea>
              <!-- <span class="text-danger">{{ $errors->first('summary') }}</span> -->
            </div>
          </div>

          <div class="row ">
            <div class="col-md-8  col-sm-12 col-xs-12 mb20">
              <p>
                {{trans('messages.listing_description.add_more')}} <a href="{{ url('admin/listing/'.$result->id.'/details') }}" class="secondary-text-color" id="js-write-more">{{trans('messages.listing_description.detail')}}</a> {{trans('messages.listing_description.detail_data')}}.
              </p>
            </div>
          </div>
          <br>
          <div class="row">
            <div class="col-md-6  col-sm-6 col-xs-6 text-left">
                <a data-prevent-default="" href="{{ url('admin/listing/'.$result->id.'/basics') }}" class="btn btn-large btn-primary">{{trans('messages.listing_description.back')}}</a>
            </div>
            <div class="col-md-6 col-sm-6 col-xs-6 text-right">
                <!-- <a data-prevent-default="" href="{{ url('admin/listing/'.$result->id.'/location') }}" class="btn btn-large btn-primary">{{trans('messages.listing_basic.next')}}</a> -->

               <button type="submit" class="btn btn-large btn-primary next-section-button">
               {{trans('messages.listing_basic.next')}}
              </button> 
            </div>
          </div>  
          </div>
          </div>
      </form>
      </div>
    </section>
    <!-- /.content -->
     <div class="clearfix"></div>      
    </div>
@stop

@section('validate_script')
<script type="text/javascript">
   $(document).ready(function () {

            $('#list_des').validate({
                rules: {
                    name: {
                        required: true
                    },
                    summary: {
                        required: false
                    }
                }
            });

        });
</script>
@endsection