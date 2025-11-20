@extends('template')
@push('css')
<link rel="stylesheet" type="text/css" href="{{ asset('public/js/intl-tel-input-13.0.0/build/css/intlTelInput.css')}}">
@endpush

@section('main')
<div class="margin-top-85">
    <div class="row m-0">
        <!-- sidebar start-->
        @include('users.sidebar')
        <!--sidebar end-->
        <div class="col-lg-10 p-0">
            <div class="container-fluid min-height">
                <div class="col-md-12 mt-5">
                    <div class="main-panel">
                        @include('users.profile_nav')

                        <!--Success Message -->
                        @if(Session::has('message'))
                            <div class="row mt-5">
                                <div class="col-md-12  alert {{ Session::get('alert-class') }} alert-dismissable fade in top-message-text opacity-1">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                                    {{ Session::get('message') }}
                                </div>
                            </div>
                        @endif 

                        <div class="row justify-content-center mt-5 border rounded-3 mb-5 pt-2 pb-2">
                            <div class="col-md-12 p-4">
                                <form id='profile_update' method='post' action="{{url('users/profile')}}">
                                    {{ csrf_field() }}
                                    <div class="row">
                                        <input type="hidden" name="customer_id" id="user_id" value="{{ Auth::user()->id }}">
                                        <input type="hidden" name="carrier_code" id="carrier_code" value="{{ $profile->carrier_code }}">
                                        <input type="hidden" name="formatted_phone" id="formatted_phone" value="{{ $profile->formatted_phone }}">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="first_name">First Name <span class="text-danger">*</span></label>
                                                <input class='form-control text-16' type='text' name='first_name' value="{{ old('first_name', $profile->first_name) }}" id='first_name' size='30'>
                                                @if ($errors->has('first_name')) <p class="error-tag">{{ $errors->first('first_name') }}</p> @endif
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="last_name">Surname <span class="text-danger">*</span></label>
                                                <input class='form-control  text-16' type='text' name='last_name' value="{{ old('last_name', $profile->last_name) }}" id='last_name' size='30'>
                                                @if ($errors->has('last_name')) <p class="error-tag">{{ $errors->first('last_name') }}</p> @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="email">Your Email (note it is also your log in)<span class="text-danger">*</span>
                                                    <i class="icon icon-lock" data-behavior="tooltip" aria-label="Private"></i>
                                                </label>
                                                <input class='form-control  text-16' type='text' name='email' value="{{ old('email', $profile->email) }}" id='email' size='30'>
                                                @if ($errors->has('email')) <p class="error-tag">{{ $errors->first('email') }}</p> @endif
                                            </div>
                                        </div>
                                         <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="phone">Phone <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control  text-16" value="{{ old('phone', $profile->formatted_phone) }}" id="phone" name="phone">
                                                <span id="phone-error" class="text-danger"></span>
                                                <span id="tel-error" class="text-danger"></span>
                                                @if ($errors->has('phone')) <p class="error-tag">{{ $errors->first('phone') }}</p> @endif
                                            </div>
                                        </div>    
                                    </div>
                                    <div class="row">
                                         <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="default_country">Country <span class="text-danger">*</span></label>
                                                <select name="default_country" id="default_country" class="form-control text-16" required>
                                                    <option value="">Select Country</option>
                                                    @foreach($countries as $short => $name)
                                                        <option value="{{ $short }}" {{ (old('default_country', $profile->default_country) == $short) ? 'selected':'' }}>
                                                            {{ $name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if($errors->has('default_country')) <p class="error-tag">{{ $errors->first('default_country') }}</p> @endif
                                            </div>
                                        </div>
                                         <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="ABN">Company ID (ABN)</label>
                                                <input class="form-control text-16" type="text" name="ABN" id="ABN" value="{{ old('ABN', $profile->ABN) }}">
                                                @if($errors->has('ABN')) <p class="error-tag">{{ $errors->first('ABN') }}</p> @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-6">
                                            <div class="form-group mt-3">
                                                <label for="supplier_type">Industry/Sector</label>
                                                <input class="form-control text-16" type="text" name="supplier_type" id="supplier_type" value="{{ old('supplier_type', $profile->supplier_type) }}">
                                                @if($errors->has('supplier_type')) <p class="error-tag">{{ $errors->first('supplier_type') }}</p> @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="live">Company/Business Address</label>
                                                <input class='form-control text-16' type='text' name='details[live]' value="{{ old('details.live', $details['live'] ?? '') }}" id='live' size='60'>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-12">
                                            <div class="form-group">
                                                <label  for="user_about">Describe your business/role</label>
                                                <textarea name='details[about]' class='form-control text-15' id='user_about'>{{ old('details.about', $details['about'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12 p-0">
                                        <div class="p-4">
                                            <button type="submit" class="btn vbtn-outline-success text-16 font-weight-700 pl-4 pr-4 pt-3 pb-3 float-right pl-4 pr-4 mb-4" id="save_btn">
                                                <i class="spinner fa fa-spinner fa-spin d-none"></i>
                                                <span id="save_btn-text">{{ trans('messages.users_profile.save') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="text/javascript" src="{{ asset('public/js/intl-tel-input-13.0.0/build/js/intlTelInput.js')}}"></script>
<script type="text/javascript" src="{{ asset('public/js/isValidPhoneNumber.js') }}"></script>
<script type="text/javascript">

    jQuery.validator.addMethod("laxEmail", function(value, element) {
        return this.optional(element) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(value);
    }, "{{ __('messages.jquery_validation.email') }}");

    $(document).ready(function() {
        $('#profile_update').validate({
            rules: {
                first_name: { required: true, maxlength: 255 },
                last_name: { required: true, maxlength: 255 },
                phone: { required: true, maxlength: 255 },
                email: { required: true, maxlength: 255, laxEmail: true },
                default_country: { required: true },
            },
            messages: {
                first_name: { required: "{{ __('messages.jquery_validation.required') }}", maxlength: "{{ __('messages.jquery_validation.maxlength255') }}" },
                last_name: { required: "{{ __('messages.jquery_validation.required') }}", maxlength: "{{ __('messages.jquery_validation.maxlength255') }}" },
                email: { required: "{{ __('messages.jquery_validation.required') }}", maxlength: "{{ __('messages.jquery_validation.maxlength255') }}" },
                phone: { required: "{{ __('messages.jquery_validation.required') }}" },
                default_country: { required: "{{ __('messages.jquery_validation.required') }}" },
            }
        });
    });

    var hasPhoneError = false;
    var hasEmailError = false;

    $.validator.setDefaults({
        highlight: function(element) {
            $(element).parent('div').addClass('has-error');
        },
        unhighlight: function(element) {
            $(element).parent('div').removeClass('has-error');
        },
        errorPlacement: function(error, element) {
            $('#tel-error').html('').hide();
            error.insertAfter(element);
        }
    });

    $(document).ready(function() {
        $("#phone").intlTelInput({
            separateDialCode: true,
            nationalMode: true,
            preferredCountries: ["us"],
            autoPlaceholder: "polite",
            placeholderNumberType: "MOBILE",
            utilsScript: '{{ URL::to("/") }}/public/js/intl-tel-input-13.0.0/build/js/utils.js'
        });

        var countryData = $("#phone").intlTelInput("getSelectedCountryData");
        $('#carrier_code').val(countryData.dialCode);

        $("#phone").on("countrychange", function(e, countryData) {
            formattedPhone();
            $('#carrier_code').val(countryData.dialCode);
            if ($.trim($(this).val()) !== '') {
                if (!$(this).intlTelInput("isValidNumber") || !isValidPhoneNumber($.trim($(this).val()))) {
                    $('#tel-error').addClass('error').html('Please enter a valid International Phone Number.').css("font-weight", "bold");
                    hasPhoneError = true;
                    enableDisableButton();
                    $('#phone-error').hide();
                } else {
                    $('#tel-error').html('');

                    $.ajax({
                        method: "POST",
                        url: "{{url('duplicate-phone-number-check-for-existing-customer')}}",
                        dataType: "json",
                        cache: false,
                        data: {
                            "_token": "{{ csrf_token() }}",
                            'phone': $.trim($(this).val()),
                            'carrier_code': $.trim(countryData.dialCode),
                            'id': $('#user_id').val(),
                        }
                    })
                    .done(function(response) {
                        if (response.status == true) {
                            $('#tel-error').html('');
                            $('#phone-error').show();
                            $('#phone-error').addClass('error').html(response.fail).css("font-weight", "bold");
                            hasPhoneError = true;
                            enableDisableButton();
                        } else if (response.status == false) {
                            $('#tel-error').show();
                            $('#phone-error').html('');
                            hasPhoneError = false;
                            enableDisableButton();
                        }
                    });
                }
            } else {
                $('#tel-error').html('');
                $('#phone-error').html('');
                hasPhoneError = false;
                enableDisableButton();
            }
        });
    });

    $(document).ready(function() {
        $("input[name=phone]").on('blur keyup', function(e) {
            formattedPhone();
            if ($.trim($(this).val()) !== '') {
                if (!$(this).intlTelInput("isValidNumber") || !isValidPhoneNumber($.trim($(this).val()))) {
                    $('#tel-error').addClass('error').html('Please enter a valid International Phone Number.').css("font-weight", "bold");
                    hasPhoneError = true;
                    enableDisableButton();
                    $('#phone-error').hide();
                } else {
                    var phone = $(this).val().replace(/-|\s/g, "");
                    var phone = $(this).val().replace(/^0+/, "");
                    var customer_id = $('#user_id').val();

                    var pluginCarrierCode = $('#phone').intlTelInput('getSelectedCountryData').dialCode;
                    $.ajax({
                        url: "{{url('duplicate-phone-number-check-for-existing-customer')}}",
                        method: "POST",
                        dataType: "json",
                        data: {
                            'phone': phone,
                            'carrier_code': pluginCarrierCode,
                            '_token': "{{csrf_token()}}",
                            'id': customer_id
                        }
                    })
                    .done(function(response) {
                        if (response.status == true) {
                            if (phone.length == 0) {
                                $('#phone-error').html('');
                            } else {
                                $('#phone-error').addClass('error').html("The number has already been taken!").css("font-weight", "bold");
                                hasPhoneError = true;
                                enableDisableButton();
                            }
                        } else if (response.status == false) {
                            $('#phone-error').html('');
                            hasPhoneError = false;
                            enableDisableButton();
                        }
                    });
                    $('#tel-error').html('');
                    $('#phone-error').show();
                    hasPhoneError = false;
                    enableDisableButton();
                }
            } else {
                $('#tel-error').html('');
                $('#phone-error').html('');
                hasPhoneError = false;
                enableDisableButton();
            }
        });
    });

    function formattedPhone() {
        if ($('#phone').val() != '') {
            var p = $('#phone').intlTelInput("getNumber").replace(/-|\s/g, "");
            $("#formatted_phone").val(p);
        }
    }

    function enableDisableButton() {
        if (!hasPhoneError && !hasEmailError) {
            $('form').find("button[type='submit']").prop('disabled', false);
        } else {
            $('form').find("button[type='submit']").prop('disabled', true);
        }
    }
</script>
@endpush