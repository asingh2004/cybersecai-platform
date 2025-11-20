@extends('template')
@section('main')

    <div class="container-fluid container-fluid-90 margin-top-85 min-height">
        <div class="row">
                    <div>
                        <p class="h2 success-text">Congrats! Your subscription has been activated. If there are any issues with the payment, we will contact you. Enjoy listing!</p>
                    </div>

                    <div>
                        <p class="h3">Click on the myRespiteAccom icon at the top left hand side or your profile to go back to your Dashboard.</p>
                    </div>


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

