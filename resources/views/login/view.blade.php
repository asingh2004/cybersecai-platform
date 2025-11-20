@extends('template')

@section('main')
<div class="container mb-4 margin-top-85 min-height">
	<div class="d-flex justify-content-center">
		<div class="p-5 mt-5 mb-5 border w-450" >
			@if(Session::has('success'))
    <div class="row mt-3">
        <div class="col-md-12 p-2 text-center text-14 alert alert-success alert-dismissable fade in opacity-1">
            <a href="#"  class="close text-18" data-bs-dismiss="alert" aria-label="close">&times;</a>
            {{ Session::get('success') }}
        </div>
    </div>
@endif

@if(Session::has('message'))
    <div class="row mt-3">
        <div class="col-md-12 p-2 text-center text-14 alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissable fade in opacity-1">
            <a href="#"  class="close text-18" data-bs-dismiss="alert" aria-label="close">&times;</a>
            {{ Session::get('message') }}
        </div>
    </div>
@endif

@if($errors->any())
    <div class="row mt-3">
        <div class="col-md-12 p-2 text-center text-14 alert alert-danger alert-dismissable fade in opacity-1">
            <a href="#"  class="close text-18" data-bs-dismiss="alert" aria-label="close">&times;</a>
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    </div>
@endif
        

			<form id="login_form" method="post" action="{{url('authenticate')}}"  accept-charset='UTF-8'>
				{{ csrf_field() }}
				<div class="form-group col-sm-12 p-0">
                    <label for="first_name">{{ trans('messages.login.email') }} <span class="text-13 text-danger">*</span></label>
					@if ($errors->has('email'))
						<p class="error">{{ $errors->first('email') }}</p>
					@endif
					<input type="email" class="form-control text-14" value="{{ old('email') }}" name="email" placeholder = "{{trans('messages.login.email')}}">
				</div>

				<div class="form-group col-sm-12 p-0">
                    <label for="first_name">{{ trans('messages.login.password') }} <span class="text-13 text-danger">*</span></label>
					@if ($errors->has('password'))
						<p class="error">{{ $errors->first('password') }}</p>
					@endif
					<input type="password" class="form-control text-14" value="" name="password" placeholder = "{{trans('messages.login.password')}}">
				</div>

				<div class="form-group col-sm-12 p-0 mt-3" >
					<div class="d-flex justify-content-between">
						<div class="m-3 text-14">
							<input type="checkbox" class='remember_me' id="remember_me2" name="remember_me" value="1">
							{{trans('messages.login.remember_me')}}
						</div>

						<div class="m-3 text-14">
							<a href="{{URL::to('/')}}/forgot_password" class="forgot-password text-right">{{trans('messages.login.forgot_pwd')}}</a>
						</div>
					</div>
				</div>

				<div class="form-group col-sm-12 p-0" >
					<button type='submit' id="btn" class="btn pb-3 pt-3  button-reactangular text-15 vbtn-success w-100 rounded"> <i class="spinner fa fa-spinner fa-spin d-none" ></i>
							<span id="btn_next-text">{{trans('messages.login.login')}}</span>
					</button>
				</div>
			</form>

			<div class="mt-3 text-14">
				{{trans('messages.login.do_not_have_an_account')}}
				<a href="{{URL::to('/')}}/signup" class="font-weight-600">
				{{trans('messages.login.register')}}
				</a>
			</div>
		</div>
	</div>
</div>
@stop

@section('validation_script')
<!-- <script type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js" integrity="sha512-37T7leoNS06R80c8Ulq7cdCDU5MNQBwlYoy1TX/WUsLFC2eYNqtKlV0QjH7r8JpG/S0GUMZwebnVFLPd6SU5yg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">
jQuery.validator.addMethod("laxEmail", function(value, element) {
	// allow any non-whitespace characters as the host part
	return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
}, "{{ __('messages.jquery_validation.email') }}" );

$(document).ready(function () {
	$('#login_form').validate({
		rules: {
			email: {
				required: true,
				maxlength: 255,
				laxEmail: true
			},

			password: {
				required: true
			}
		},
		submitHandler: function(form)
        {
 			$("#btn").on("click", function (e)
            {
            	$("#btn").attr("disabled", true);
                e.preventDefault();
            });


            $(".spinner").removeClass('d-none');
            $("#btn_next-text").text("{{trans('messages.login.login')}}..");
            return true;
        },
		messages: {
			email: {
				required:  "{{ __('messages.jquery_validation.required') }}",
				maxlength: "{{ __('messages.jquery_validation.maxlength255') }}",
			},

			password: {
				required: "{{ __('messages.jquery_validation.required') }}",
			}
		}
	});
});
</script>
@endsection
