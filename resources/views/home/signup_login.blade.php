@extends('template')

@push('css')
<link rel="stylesheet" type="text/css" href="{{ asset('public/js/intl-tel-input-13.0.0/build/css/intlTelInput.min.css') }}">
@endpush

@section('main')
<div class="container mb-4 margin-top-85 min-height">
    <div class="d-flex justify-content-center">
        <div class="p-5 mt-5 mb-5 border w-450">
         

            <form id="signup_form" name="signup_form" method="post" action="{{ url('create') }}" class='signup-form login-form' accept-charset='UTF-8' onsubmit="return ageValidate();">
                {{ csrf_field() }}
                <div class="row text-16">
                    <input type="hidden" name="email_signup" id="form">
                    <input type="hidden" name="default_country" id="default_country" class="form-control">
                    <input type="hidden" name="carrier_code" id="carrier_code" class="form-control">
                    <input type="hidden" name="formatted_phone" id="formatted_phone" class="form-control">

                    <div class="form-group col-sm-12 p-0">
                        <label for="first_name">{{ trans('messages.sign_up.first_name') }} <span class="text-13 text-danger">*</span></label>
                        @if ($errors->has('first_name'))
                            <p class="error-tag">{{ $errors->first('first_name') }}</p>
                        @endif
                        <input type="text" class='form-control text-14 p-2' value="{{ old('first_name') }}" name='first_name' id='first_name' placeholder='{{ trans('messages.sign_up.first_name') }}'>
                    </div>

                    <div class="form-group col-sm-12 p-0">
                        <label for="last_name">{{ trans('messages.sign_up.last_name') }} <span class="text-13 text-danger">*</span></label>
                        @if ($errors->has('last_name'))
                            <p class="error-tag">{{ $errors->first('last_name') }}</p>
                        @endif
                        <input type="text" class='form-control text-14 p-2' value="{{ old('last_name') }}" name='last_name' id='last_name' placeholder='{{ trans('messages.sign_up.last_name') }}'>
                    </div>

                    <div class="form-group col-sm-12 p-0">
                        <label for="email">{{ trans('messages.login.email') }} <span class="text-13 text-danger">*</span></label>
                        <input type="email" class='form-control text-14 p-2' value="{{ old('email') }}" name='email' id='email' placeholder='{{ trans('messages.login.email') }}'>
                        @if ($errors->has('email'))
                            <p class="error-tag">{{ $errors->first('email') }}</p>
                        @endif
                        <div id="emailError"></div>
                    </div>

                    <div class="form-group col-sm-12 p-0">
                        <label for="password">{{ trans('messages.login.password') }} <span class="text-13 text-danger">*</span></label>
                        @if ($errors->has('password'))
                            <p class="error-tag">{{ $errors->first('password') }}</p>
                        @endif
                        <input type="password" class='form-control text-14 p-2' name='password' id='password' placeholder='{{ trans('messages.login.password') }}'>
                    </div>

                    <button type='submit' id="btn" class="btn pb-3 pt-3 text-15 button-reactangular vbtn-success w-100 ml-0 mr-0 mb-3">
                        <i class="spinner fa fa-spinner fa-spin d-none"></i>
                        <span id="btn_next-text">{{ trans('messages.sign_up.sign_up') }}</span>
                    </button>
                </div>
            </form>

            <div class="text-14">
                {{ trans('messages.sign_up.already') }} {{ $site_name }} {{ trans('messages.sign_up.member') }}?
                <a href="{{ URL::to('/') }}/login?" class="font-weight-600">
                    {{ trans('messages.sign_up.login') }}
                </a>
            </div>
        </div>
    </div>
</div>
@stop

@push('scripts')
<!-- The scripts remain unchanged for validation, unless they validate removed fields -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js" integrity="sha512-37T7leoNS06R80c8Ulq7cdCDU5MNQBwlYoy1TX/WUsLFC2eYNqtKlV0QjH7r8JpG/S0GUMZwebnVFLPd6SU5yg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">
    $('#signup_form').validate({
        rules: {
            first_name: { required: true, maxlength: 255 },
            last_name: { required: true, maxlength: 255 },
            email: { required: true, maxlength: 255, laxEmail: true },
            password: { required: true, minlength: 6 },
        },
        submitHandler: function(form) {
            // Enable the submit button and proceed with form submission.
            $(form).submit();
        },
        messages: {
            first_name: {
                required:  "{{ __('messages.jquery_validation.required') }}",
                maxlength: "{{ __('messages.jquery_validation.maxlength255') }}",
            },
            last_name: {
                required:  "{{ __('messages.jquery_validation.required') }}",
                maxlength: "{{ __('messages.jquery_validation.maxlength255') }}",
            },
            email: {
                required:  "{{ __('messages.jquery_validation.required') }}",
                maxlength: "{{ __('messages.jquery_validation.maxlength255') }}",
            },
            password: {
                required:  "{{ __('messages.jquery_validation.required') }}",
                minlength: "{{ __('messages.jquery_validation.minlength6') }}",
            },
        },
    });
</script>
@endpush