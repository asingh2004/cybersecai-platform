@extends('template')
@section('main')
    <div class="container-fluid container-fluid-90 margin-top-85 min-height">
        <div class="row">
            <div class="col-md-8 col-sm-8 col-xs-12 mb-5 main-panel p-5 border rounded">
                <div class="pb-3 m-0 text-24 font-weight-700">{{trans('messages.payment.bank_pay')}}</div>
                <form action="{{URL::to('facilities/bank-payment')}}" method="post" id="payment-form" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="row justify-content-center">
                       
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
                                                <td id="name" class="text-muted font-weight-700 text-16">{{$bank->account_name}}</td>
                                            </tr>
                                        @endif

                                        @if($bank->iban)
                                            <tr>
                                                <td>{{trans('messages.account_preference.bank_account_num')}}:</td>
                                                <td id="iban" class="text-muted font-weight-700 text-16">{{$bank->iban}}</td>
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
                                            <td>Subscription Due:</td>
                                            <td id="total"
                                                class="text-muted font-weight-700 text-16">
                                                @foreach(session('amount') as $total)
                                                    <p class="pr-4">${{ $total->value * Session::get('properties_count') * 7 * 52 * 1.1}}</p>

                                                @endforeach
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Payment Reference: </td>
                                            <td id="reference"
                                                class="text-muted font-weight-700 text-16">
                                                    <p class="pr-4">{{ Session::get('userId') }}</p>


                                            </td>
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
                                    <td>{{trans('messages.trips_active.subs_payment_message')}}<span class="danger-text">*</span>:</td>
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
                

                    <div class="border p-4 mt-3">
                    
                           <div>
                                <p class="pl-4 text-16 font-weight-700">Subscription Summary</p>
                            </div>
                        

                             
                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">Subscription Start Date:</p>
                                </div>

                                <div>
                                    <p class="pr-4"> {{ Session::get('subs_start_date') }}</p>
                                </div>
                            </div>
                            

                            <div class="d-flex justify-content-between text-16">
                                <div>
                                    <p class="pl-4">Next Renewal Date: </p>
                                </div>
                                
                                <div>
                                    <p class="pr-4"> {{ Session::get('sub_renewal_date') }} </p>
                                </div>
                            </div>
                        

                        <hr>
                        <div class="d-flex justify-content-between text-16">
                            <div>
                                <p class="pl-4">Account ID: </p>
                            </div>
                            <div>

                            @foreach(session('businessname') as $email)
                                <p class="pr-4">{{ $email->email }} </p>
                            @endforeach
                            </div>
                        </div>

                        <div class="d-flex justify-content-between text-16">
                            <div>
                                <p class="pl-4">Number of Properties Subscribed:</p>
                            </div>
                            <div>
                                <p class="pr-4"> {{ Session::get('properties_count') }} </p>
                            </div>
                        </div>
                

                        <div class="d-flex justify-content-between text-16">
                            <div>
                                <p class="pl-4">Subscription Amount:</p>
                            </div>
                            <div>
                                @foreach(session('amount') as $total)
                                <p class="pr-4">${{ $total->value * Session::get('properties_count') * 7 * 52}}</p>

                                @endforeach
                            </div>
                        </div>

                        <div class="d-flex justify-content-between text-16">
                            <div>
                                <p class="pl-4">GST Amount:</p>
                            </div>
                            <div>
                                @foreach(session('amount') as $total)
                                <p class="pr-4">${{ $total->value * Session::get('properties_count') * 7 * 52 * 0.1}}</p>

                                @endforeach
                            </div>
                        </div>
                        
                        <hr>

                        <div class="d-flex justify-content-between font-weight-700 text-16">
                            <div>
                                <p class="pl-4">Total Subscription Fees (incl GST): </p>
                            </div>

                            <div>
                            @foreach(session('amount') as $total)
                                <p class="pr-4">${{ $total->value * Session::get('properties_count') * 7 * 52 * 1.1}}</p>

                            @endforeach

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
