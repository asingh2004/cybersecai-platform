@extends('template')
@section('main')
    <div class="container-fluid container-fluid-90 margin-top-85 min-height">
        <div class="row">
            <div class="col-md-8 col-sm-8 col-xs-12 mb-5 main-panel p-5 border rounded">
                <div class="pb-3 m-0 text-24 font-weight-700">{{trans('messages.payment.bank_pay')}}</div>
                <form action="{{URL::to('payments/bank-payment')}}" method="post" id="payment-form" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="row justify-content-center">
                        <input name="property_id" type="hidden" value="{{ $property_id }}">
                        <input name="checkin" type="hidden" value="{{ $checkin }}">
                        <input name="checkout" type="hidden" value="{{ $checkout }}">
                        <input name="number_of_guests" type="hidden" value="{{ $number_of_guests }}">
                        <input name="nights" type="hidden" value="{{ $nights }}">
                        <input name="currency" type="hidden" value="{{ $result->property_price->code }}">
                        <input name="booking_id" type="hidden" value="{{ $id }}">
                        <input name="booking_type" type="hidden" value="{{ $booking_type }}">


                        <div class="col-sm-12 p-0">
                            <label for="message">{{trans('messages.payment.bank_select')}}</label>
                        </div>
                        <div class="col-sm-12 p-0 pb-3">
                            <select id="bank-select" required name="bank" class="form-control mb20">
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" @if($loop->first) selected @endif>
                                        {{ $bank->iban }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-12 p-3 my-2 border-ddd border-r-10">
                            @foreach($banks as $bank)
                                <div class="banks {{ $loop->first ? '' : 'hide' }}" id="{{ $bank->id }}">
                                    <table class="table table-sm table-borderless">
                                        @if($bank->account_name)
                                            <tr>
                                                <td>{{trans('messages.account_preference.bank_holder')}}:</td>
                                                <td id="name" class="text-muted">{{$bank->account_name}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->iban)
                                            <tr>
                                                <td>{{trans('messages.account_preference.bank_account_num')}}:</td>
                                                <td id="iban" class="text-muted">{{$bank->iban}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->swift_code)
                                            <tr>
                                                <td>{{trans('messages.account_preference.swift_code')}}:</td>
                                                <td id="swift" class="text-muted">{{$bank->swift_code}}</td>
                                            </tr>
                                        @endif


                                        @if($bank->bank_name)
                                            <tr>
                                                <td>{{trans('messages.account_preference.bank_name')}}:</td>
                                                <td id="bank_name" class="text-muted">{{$bank->bank_name}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->routing_no)
                                            <tr>
                                                <td>{{trans('messages.account_preference.routing_no')}}:</td>
                                                <td id="route" class="text-muted">{{$bank->routing_no}}</td>
                                            </tr>
                                        @endif
                                        <tr>

                                        </tr>
                                        @if($bank->branch_name)
                                            <tr>
                                                <td>{{trans('messages.account_preference.branch_name')}}</td>
                                                <td id="br_name" class="text-muted">{{$bank->branch_name}}</td>
                                            </tr>
                                        @endif
                                        <tr>

                                        </tr>
                                        @if($bank->branch_city)
                                            <tr>
                                                <td>{{trans('messages.account_preference.branch_city')}}</td>
                                                <td id="br_city" class="text-muted">{{$bank->branch_city}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->country)
                                            <tr>
                                                <td>{{trans('messages.payment.country')}}:</td>
                                                <td id="country" class="text-muted">{{$bank->country}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->logo)
                                            <tr>
                                                <td>{{trans('messages.account_preference.logo')}}:</td>
                                                <td>
                                                    <img id="logo" src="{{$bank->logo}}" class="bank-logo" alt="">
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td>{{trans('messages.booking_my.total')}}:</td>
                                            <td id="total"
                                                class="text-muted">{!! $price_list->total_with_symbol !!}</td>
                                        </tr>
                                    </table>
                                    @if($bank->description)
                                        <hr>
                                        <div class="p-2">
                                            {!! $bank->description !!}
                                        </div>
                                        <hr>
                                    @endif
                                </div>
                            @endforeach


                            <table class="table table-borderless">
                                <tr>
                                    <td>{{trans('messages.payment.attach')}}<span class="danger-text">*</span>:</td>
                                </tr>
                                <tbody>
                                <tr>
                                    <td><input class="form-control" required name="attachment" type="file">
                                        <span class="text-danger">{{ $errors->first('attachment') }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('messages.trips_active.type_message')}}<span class="danger-text">*</span>:</td>
                                </tr>
                                <tbody>
                                <tr>
                                    <td><textarea class="form-control" required name="note"
                                                  type="text" style="height: 150px;" placeholder="Message">{{old('note')}}</textarea>
                                        <span class="text-danger">{{ $errors->first('note') }}</span>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-sm-12 p-0 text-right mt-4">
                        <button id="payment-form-submit" type="submit"
                                class="btn vbtn-outline-success text-16 font-weight-700 pl-5 pr-5 pt-3 pb-3">
                            <i class="spinner fa fa-spinner fa-spin d-none"></i>
                            {{trans('messages.general.confirm')}}
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-md-4 mb-5">
                <div class="card p-3">
                    <a href="{{ url('/') }}/properties/{{$result->slug}}">
                        <img class="card-img-top p-2 rounded" src="{{$result->cover_photo}}" alt="{{$result->name}}"
                             height="180px">
                    </a>
                    <div class="card-body p-2">
                        <a href="{{ url('/') }}/properties/{{$result->slug}}"><p
                                class="text-16 font-weight-700 mb-0">{{ $result->name }}</p></a>

                        <p class="text-14 mt-2 text-muted mb-0">
                            <i class="fas fa-map-marker-alt"></i>
                            {{$result->property_address->address_line_1}}, {{ $result->property_address->state }}
                            , {{ $result->property_address->country_name }}
                        </p>
                        <div class="border p-4 mt-4 text-center">
                            <p class="text-16 mb-0">
                                <strong
                                    class="font-weight-700 secondary-text-color">{{ $result->property_type_name }}</strong>
                                {{trans('messages.payment.for')}}
                                <strong
                                    class="font-weight-700 secondary-text-color">{{ $number_of_guests }} {{trans('messages.payment.guest')}}</strong>
                            </p>
                            <div class="text-14"><strong>{{ date('D, M d, Y', strtotime($checkin)) }}</strong> to
                                <strong>{{ date('D, M d, Y', strtotime($checkout)) }}</strong></div>
                        </div>

                        <div class="border p-4 mt-3">

                         
                            <hr>
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.payment.night')}}</p>
                                </div>
                                <div>
                                    <p class="pr-4">{{ $nights }}</p>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">{!! $price_list->per_night_price_with_symbol !!}
                                        x {{ $nights }} {{trans('messages.payment.nights')}}</p>
                                </div>
                                <div>
                                    <p class="pr-4">{!! $price_list->total_night_price_with_symbol !!}</p>
                                </div>
                            </div>

                    

                            @if($price_list->additional_guest)
                                <div class="d-flex justify-content-between text-16">
                                    <div>
                                        <p class="pl-4">{{trans('messages.payment.additional_guest_fee')}}</p>
                                    </div>

                                    <div>
                                        <p class="pr-4">{!! $price_list->additional_guest_fee_with_symbol !!}</p>
                                    </div>
                                </div>
                            @endif

                            @if($price_list->security_fee)
                                <div class="d-flex justify-content-between text-16">
                                    <div>
                                        <p class="pl-4">{{trans('messages.payment.security_deposit')}}</p>
                                    </div>

                                    <div>
                                        <p class="pr-4">{!! $price_list->security_fee_with_symbol !!}</p>
                                    </div>
                                </div>
                            @endif

                            @if($price_list->cleaning_fee)
                                <div class="d-flex justify-content-between text-16">
                                    <div>
                                        <p class="pl-4">{{trans('messages.payment.cleaning_fee')}}</p>
                                    </div>

                                    <div>
                                        <p class="pr-4">{!! $price_list->cleaning_fee_with_symbol !!}</p>
                                    </div>
                                </div>
                            @endif

                            @if($price_list->iva_tax)
                                <div class="d-flex justify-content-between text-16">
                                    <div>
                                        <p class="pl-4">{{trans('messages.property_single.iva_tax')}}</p>
                                    </div>

                                    <div>
                                        <p class="pr-4">{!!  $price_list->iva_tax_with_symbol !!}</p>
                                    </div>
                                </div>
                            @endif

                            @if($price_list->accomodation_tax)
                                <div class="d-flex justify-content-between text-16">
                                    <div>
                                        <p class="pl-4">{{trans('messages.property_single.accommodatiton_tax')}}</p>
                                    </div>

                                    <div>
                                        <p class="pr-4">{!! $price_list->accomodation_tax_with_symbol !!}</p>
                                    </div>
                                </div>
                            @endif
                            <hr>

                            <div class="d-flex justify-content-between font-weight-700 text-16">
                                <div>
                                    <p class="pl-4">{{trans('messages.payment.total')}}</p>
                                </div>

                                <div>
                                    <p class="pr-4">{!! $price_list->total_with_symbol !!}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>


            </div>
        </div>
    </div>
    </div>


    @push('css')
        <style>
            .bank-logo {
                margin: 0;
                text-align: left;
                max-height: 50px;
                max-width: 120px;
                object-fit: contain;
            }

            .hide {
                display: none;
            }

            strong {
                font-weight: bold !important;
            }

            td {
                width: 50% !important;
            }
        </style>
    @endpush
    @push('scripts')
        <!-- This js is already in foot.blade.php -->
<!-- <script  type="text/javascript" src="{{ url('public/js/jquery.validate.min.js') }}"></script> -->
        <script type="text/javascript">
            $(document).on('change', '#bank-select', () => {
                $('.banks').hide();
                $('#' + $('#bank-select').val()).show();
            })
        </script>
    @endpush
@stop
