<?php
namespace App\Http\Controllers;


use Auth;
use Illuminate\Support\Facades\Validator;
use Session;
use DateTime;

use App\Http\{
    Requests,
    Helpers\Common,
    Controllers\EmailController
};

use App\Models\{
    Bank,
    BankDate,
    User,
    Payouts,
    Currency,
    Country,
    Settings,
    Payment,
    Photo,
    Withdraw,
    Messages,
    Wallet,
    Properties,
    Bookings,
    PaymentMethods,
    BookingDetails,
    PropertyDates,
    PropertyPrice,
    PropertyFees};
use Omnipay\Omnipay;
use Illuminate\Http\Request;


class PaymentController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function setup($way = 'PayPal_Express')
    {
        $paypal_data = Settings::getAll()->where('type', 'PayPal')->pluck('value', 'name');
        $this->omnipay  = Omnipay::create($way);
        $this->omnipay->setUsername($paypal_data['username']);
        $this->omnipay->setPassword($paypal_data['password']);
        $this->omnipay->setSignature($paypal_data['signature']);
        $this->omnipay->setTestMode(($paypal_data['mode'] == 'sandbox') ? true : false);
        if ($way == 'Paypal_Express') {
            $this->omnipay->setLandingPage('Login');
        }
    }

    public function index(Request $request)
    {
        $data['paypal_status'] = Settings::getAll()->where('name', 'paypal_status')
                                ->where('type', 'PayPal')->first();

        $data['stripe_status'] = Settings::getAll()->where('name', 'stripe_status')
                                ->where('type', 'Stripe')->first();
        $data['banks'] = Bank::getAll()->where('status', 'Active')->count();

        $data['referral_code'] = User::where('id','=', Auth::user()->id)
                                ->select('referral_code')->first();

        $data['is_discounted'] = null;

        if ($request->isMethod('post')) {
            Session::put('payment_property_id', $request->id);
            Session::put('payment_checkin', $request->checkin);
            Session::put('payment_checkout', $request->checkout);
            Session::put('payment_number_of_guests', $request->number_of_guests);
            Session::put('payment_booking_type', $request->booking_type);
            Session::put('payment_booking_status', $request->booking_status);
            Session::put('payment_booking_id', $request->booking_id);
            Session::put('payment_respite_type', $request->respite_service_type);
            Session::put('payment_discount_code', $request->discount_code);
            //dump($request->respite_service_type);
            //dump($request->discount_code);



            $id               = Session::get('payment_property_id');
            $checkin          = Session::get('payment_checkin');
            $checkout         = Session::get('payment_checkout');
            $number_of_guests = Session::get('payment_number_of_guests');
            $booking_type     = Session::get('payment_booking_type');
            $booking_status   = Session::get('payment_booking_status');
            $booking_id       = Session::get('payment_booking_id');
            $respite_type     = Session::get('payment_respite_type');


        } else {
            $id               = Session::get('payment_property_id');
            $number_of_guests = Session::get('payment_number_of_guests');
            $checkin          = Session::get('payment_checkin');
            $checkout         = Session::get('payment_checkout');
            $booking_type     = Session::get('payment_booking_type');
            $booking_status   = Session::get('payment_booking_status');
        }

        if ( !$request->isMethod('post') && ! $checkin) {
            return redirect('properties/'.$request->id);
        }

        $data['result']           = Properties::find($id);
        $data['property_id']      = $id;
        $data['number_of_guests'] = $number_of_guests;
        $data['booking_type']     = $booking_type;
        $data['checkin']          = setDateForDb($checkin);
        $data['checkout']         = setDateForDb($checkout);
        $data['status']           = $booking_status ?? "";
        $data['booking_id']       = $booking_id ?? "";

        $from                     = new DateTime(setDateForDb($checkin));
        $to                       = new DateTime(setDateForDb($checkout));
        $data['nights']           = $to->diff($from)->format("%a");

        $data['price_list']    = json_decode($this->helper->getPrice($data['property_id'], $data['checkin'], $data['checkout'], $data['number_of_guests']));
        Session::put('payment_price_list', $data['price_list']);

        if (((isset($data['price_list']->status) && ! empty($data['price_list']->status)) ? $data['price_list']->status : '') == 'Not available') {
            $this->helper->one_time_message('success', trans('messages.error.property_available_error'));
            return redirect('properties/'.$id);
        }

        $data['currencyDefault']  = $currencyDefault = Currency::getAll()->firstWhere('default',1);

        $data['price_eur']        = numberFormat($this->helper->convert_currency($data['result']->property_price->code, $currencyDefault->code, $data['price_list']->total),2);
        $data['price_rate']       = $this->helper->currency_rate($data['result']->property_price->currency_code, $this->helper->getCurrentCurrencycode());
        $data['country']          = Country::getAll()->pluck('name', 'short_name');
        $data['title']            = 'Pay for your reservation';
        $data['currentCurrency'] = $this->helper->getCurrentCurrency();

        return view('payment.payment', $data);
    }


    public function createBooking(Request $request)
    {
        $paypal_credentials = Settings::getAll()->where('type', 'PayPal')->pluck('value', 'name');
        $currencyDefault    = Currency::getAll()->where('default', 1)->first();
        $price_list         = json_decode($this->helper->getPrice($request->property_id, $request->checkin, $request->checkout, $request->number_of_guests));

        $amount             = round($this->helper->convert_currency($request->currency, $currencyDefault->code, $price_list->total),2);
        $country            = $request->payment_country;
        $message_to_host    = $request->message_to_host . ". REFERRAL CODE: " . $request->referralcode . " CARE TYPE REQUESTED:" . $request->respite_type;

        
        if ($request->referralcode) {
            User::where('id', Auth::user()->id)
                ->update(
                    ['referral_code' => $request->referralcode]          
                    );
        }

        $purchaseData   =   [
            'testMode'  => ($paypal_credentials['mode'] == 'sandbox') ? true : false,
            'amount'    => $amount,
            'currency'  => $currencyDefault->code,
            'returnUrl' => url('payments/success'),
            'cancelUrl' => url('payments/cancel')
        ];

        Session::put('amount', $amount);
        Session::put('payment_country', $country);
        Session::put('message_to_host_'.Auth::user()->id, $message_to_host);
        Session::save();


        if ($request->payment_method == 'stripe') {
            return redirect('payments/stripe');
        } elseif ($request->payment_method == 'paypal') {
            $this->setup();
            if ($amount) {
                $response = $this->omnipay->purchase($purchaseData)->send();
                if ($response->isRedirect()) {
                    $response->redirect();
                } else {
                    $this->helper->one_time_message('error', $response->getMessage());
                    return redirect('payments/book/'.$request->property_id);
                }
            }
        } elseif ($request->payment_method == 'bank') {
            $data = $this->getDataForBooking();
            $data['banks'] = Bank::getAll()->where('status', 'Active');
            return view('payment.bank', $data);
        } else {
            $booking_id = Session::get('payment_booking_id');

            if($booking_id){
                $is_discounted = Bookings::where('id','=',Session::get('payment_booking_id'))->pluck('discount_code');
                //dump($is_discounted);

                if($is_discounted){
                    //dump("Calling no Fees");
                    $code = $this->noFees($request);
                    //return view()
                    return view('payment.discounted');
                }
            }else{
                $data = [
                'property_id'      => $request->property_id,
                'checkin'          => $request->checkin,
                'checkout'         => $request->checkout,
                'number_of_guests' => $request->number_of_guests,
                'transaction_id'   => '',
                'price_list'       => $price_list,
                'paymode'          => '',
                'first_name'       => $request->first_name,
                'last_name'        => $request->last_name,
                'postal_code'      => '',
                'country'          => '',
                'message_to_host'  => $message_to_host,
                'referral_code'     => $request->referralcode
                //'respite_type' => $request->respite_service_type,
                //'discount_code' => $request->discount_code
                ];

                $code = $this->store($data);
                $this->helper->one_time_message('success', trans('messages.booking_request.request_has_sent'));
                //return redirect('booking/requested?code='.'3Ev9pg');
                return redirect('booking/requested?code='.$code);
            }
        }
    }

    public function getDataForBooking() {
        $data['id'] = $id         = Session::get('payment_property_id');
        $data['result']           = Properties::find($id);
        $data['property_id']      = $id;

        $checkin                  = Session::get('payment_checkin');
        $checkout                 = Session::get('payment_checkout');
        $number_of_guests         = Session::get('payment_number_of_guests');
        $booking_type             = Session::get('payment_booking_type');

        $data['checkin']          = setDateForDb($checkin);
        $data['checkout']         = setDateForDb($checkout);
        $data['number_of_guests'] = $number_of_guests;
        $data['booking_type']     = $booking_type;

        $from                     = new DateTime(setDateForDb($checkin));
        $to                       = new DateTime(setDateForDb($checkout));

        $data['nights']           = $to->diff($from)->format("%a");

        $data['price_list']       = json_decode($this->helper->getPrice($data['property_id'], $data['checkin'], $data['checkout'], $data['number_of_guests']));

        $data['currencyDefault']  = $currencyDefault = Currency::getAll()->where('default', 1)->first();

        $data['price_eur']        = $this->helper->convert_currency($data['result']->property_price->default_code, $currencyDefault->code, $data['price_list']->total);

        $data['price_rate']       = $this->helper->currency_rate( $currencyDefault->code, $this->helper->getCurrentCurrencyCode());
        $data['symbol'] = $this->helper->getCurrentCurrencySymbol();
        $data['code'] = $this->helper->getCurrentCurrencyCode();
        $data['title']            = 'Pay for your reservation';
        return $data;
    }

    public function stripePayment(Request $request)
    {
        $data = $this->getDataForBooking();
        $stripe                   = Settings::getAll()->where('type', 'Stripe')->pluck('value', 'name');
        $data['publishable']      = $stripe['publishable'];
        return view('payment.stripe', $data);
    }

    public function stripeRequest(Request $request)
    {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();

        if ($request->isMethod('post')) {

            if (isset($request->stripeToken)) {
                $id            = Session::get('payment_property_id');
                $booking_id    = Session::get('payment_booking_id');
                $booking_type  = Session::get('payment_booking_type');
                $price_list    = Session::get('payment_price_list');
                $price_eur     = $this->helper->convert_currency($this->helper->getCurrentCurrencyCode(), $currencyDefault->code, $price_list->total);

                $stripe        = Settings::getAll()->where('type', 'Stripe')->pluck('value', 'name');
                $gateway = Omnipay::create('Stripe');
                $gateway->setApiKey($stripe['secret']);
                info('Price = ' . $price_eur);
                $response = $gateway->purchase([
                    'amount' => $price_eur,
                    'currency' => $currencyDefault->code,
                    'token' => $request->stripeToken,
                ])->send();


                if ($response->isSuccessful()) {
                    $pm    = PaymentMethods::where('name', 'Stripe')->first();
                    $data  = [
                        'property_id'      => Session::get('payment_property_id'),
                        'checkin'          => Session::get('payment_checkin'),
                        'checkout'         => Session::get('payment_checkout'),
                        'number_of_guests' => Session::get('payment_number_of_guests'),
                        'transaction_id'   => $response->getTransactionReference(),
                        'price_list'       => Session::get('payment_price_list'),
                        'country'          => Session::get('payment_country'),
                        'message_to_host'  => Session::get('message_to_host_'.Auth::user()->id),
                        'payment_method_id'=> $pm->id,
                        'paymode'          => 'Stripe',
                        'booking_id'       => $booking_id,
                        'booking_type'     => $booking_type
                    ];

                    if (isset($booking_id) && !empty($booking_id)) {
                         $code = $this->update($data);
                     }else{
                        $code = $this->store($data);
                    }

                    $this->helper->one_time_message('success', trans('messages.success.payment_complete_success'));
                    return redirect('booking/requested?code='.$code);
                } else {
                    $this->helper->one_time_message('error', $response->getMessage());
                    return back();
                }
            } else {

                $this->helper->one_time_message('success', trans('messages.error.payment_request_error'));
                return redirect('payments/book/'.Session::get('payment_property_id'));
            }
        }
    }


    public function noFees(Request $request)
    {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();

        if ($request->isMethod('post')) {

                    //dump("In no Fees");

                    $id            = Session::get('payment_property_id');
                    $booking_id    = Session::get('payment_booking_id');
                    $booking_type  = Session::get('payment_booking_type');

                    $pm    = PaymentMethods::where('name', 'Stripe')->first();
                    $data  = [
                        'property_id'      => Session::get('payment_property_id'),
                        'checkin'          => Session::get('payment_checkin'),
                        'checkout'         => Session::get('payment_checkout'),
                        'number_of_guests' => Session::get('payment_number_of_guests'),
                        'price_list'       => Session::get('payment_price_list'),
                        'country'          => Session::get('payment_country'),
                        'message_to_host'  => Session::get('message_to_host_'.Auth::user()->id),
                        'payment_method_id'=> $pm->id,
                        'paymode'          => 'No Fees',
                        'booking_id'       => $booking_id,
                        'booking_type'     => $booking_type
                    ];

                    if (isset($booking_id) && !empty($booking_id)) {
                        //dump("Calling UPdate");
                         $code = $this->update($data);
                     }else{
                        //dump("Calling Store");
                        $code = $this->store($data);
                    }

                    $this->helper->one_time_message('success', trans('messages.success.payment_complete_success'));
                    return redirect('booking/requested?code='.$code);
          
        }
    }

    public function bankPayment(Request $request) {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();

        if(!$request->isMethod('post')) {
            $data = $this->getDataForBooking();
            $data['banks'] = Bank::getAll()->where('status', 'Active');
            return view('payment.bank', $data);
        }

        $validate = Validator::make($request->all(), [
            'attachment' => 'required|file|mimes:jpeg,bmp,png,jpg,JPG,JPEG,pdf,doc,docx|max:1024',
            'bank' => 'required'
        ]);

        if ($validate->fails()) {
            return redirect('/payments/bank-payment')->withErrors($validate)->withInput();

        }

        $id = Session::get('payment_property_id');
        $result = Properties::find($id);
        $booking_id = Session::get('payment_booking_id');
        $booking_type = Session::get('payment_booking_type');
        $price_list = Session::get('payment_price_list');
        $price_eur = $this->helper->convert_currency($this->helper->getCurrentCurrencyCode(), $currencyDefault->code, $price_list->total);

        $attachment = $this->helper->uploadSingleFile($_FILES["attachment"], 'public/uploads/booking/');
        if ($attachment) {
            $this->helper->one_time_message('error', trans('messages.payment.upload_failed'));
        }
        $pm = PaymentMethods::where('name', 'Bank')->first();

        $data = [
            'property_id' => Session::get('payment_property_id'),
            'checkin' => Session::get('payment_checkin'),
            'checkout' => Session::get('payment_checkout'),
            'number_of_guests' => Session::get('payment_number_of_guests'),
            'price_list' => Session::get('payment_price_list'),
            'country' => Session::get('payment_country'),
            'message_to_host' => Session::get('message_to_host_' . Auth::user()->id),
            'payment_method_id' => $pm->id,
            'paymode' => 'Bank',
            'booking_id' => $booking_id,
            'booking_type' => $booking_type,
            'attachment' => $attachment,
            'bank_id' => $request->bank,
            'note' => $request->note
        ];

        if (isset($booking_id) && !empty($booking_id)) {
            $code = $this->update($data);
        } else {
            $code = $this->store($data);
        }

        $this->helper->one_time_message('success', trans('messages.success.payment_success'));
        return redirect('booking/requested?code='.$code);
    }

    public function success(Request $request)
    {
        $this->setup();
        $currencyDefault = Currency::getAll()->where('default', 1)->first();
        $transaction = $this->omnipay->completePurchase(array(
            'payer_id'              => $request->PayerID,
            'transactionReference'  => $request->token,
            'amount'                => Session::get('amount'),
            'currency'              => $currencyDefault->code
        ));

        $response = $transaction->send();

        $result = $response->getData();

        if ($result['ACK'] == 'Success') {
            $pm = PaymentMethods::where('name', 'PayPal')->first();
            $booking_id    = Session::get('payment_booking_id');
            $booking_type  = Session::get('payment_booking_type');
            $data = [
                'property_id'      => Session::get('payment_property_id'),
                'checkin'          => Session::get('payment_checkin'),
                'checkout'         => Session::get('payment_checkout'),
                'number_of_guests' => Session::get('payment_number_of_guests'),
                'transaction_id'   => isset($result['PAYMENTINFO_0_TRANSACTIONID']) ? $result['PAYMENTINFO_0_TRANSACTIONID'] : '',
                'price_list'       => Session::get('payment_price_list'),
                'country'          => Session::get('payment_country'),
                'message_to_host'  => Session::get('message_to_host_'.Auth::user()->id),
                'payment_method_id'=> $pm->id,
                'paymode'          => 'PayPal',
                'booking_id'       => $booking_id

            ];

            if (isset($booking_id) && !empty($booking_id)) {
                 $code = $this->update($data);
             }else{
                $code = $this->store($data);
            }

            $this->helper->one_time_message('success', trans('messages.success.payment_success'));
            return redirect('booking/requested?code='.$code);
        } else {
            $this->helper->one_time_message('error', $result['L_SHORTMESSAGE0'] . ' ERROR_CODE (' . $result['L_ERRORCODE0'] . ')');
            return redirect('payments/book/'.Session::get('payment_property_id'));
        }
    }

    public function cancel(Request $request)
    {
        $this->helper->one_time_message('success', trans('messages.error.payment_process_error'));
        return redirect('payments/book/'.Session::get('payment_property_id'));
    }

    public function store($data)
    {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();
        $booking = new Bookings;
        $booking->property_id       = $data['property_id'];
        $booking->host_id           = properties::find($data['property_id'])->host_id;
        $booking->user_id           = Auth::user()->id;
        $booking->start_date        = setDateForDb($data['checkin']);
        $checkinDate                = onlyFormat($booking->start_date);
        $booking->end_date          = setDateForDb($data['checkout']);
        $booking->guest             = $data['number_of_guests'];
        $booking->respite_service_type = Session::get('payment_respite_type');
        $booking->discount_code = Session::get('payment_discount_code');
        $booking->attachment        = $data['attachment'] ?? null;
        $booking->bank_id             = $data['bank_id'] ?? null;
        $booking->note             = $data['note'] ?? null;
        $booking->bank_id             = $data['bank_id'] ?? null;
        $booking->total_night       = $data['price_list']->total_nights;
        $booking->per_night         = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->property_price);

        $booking->referral_code     = $data['referral_code'] ?? null;

        $booking->custom_price_dates= isset($data['price_list']->different_price_dates_default_curr) ? json_encode($data['price_list']->different_price_dates_default_curr) : null ;



        // Following logic is added to deal with Discount codes and it is zeroed out for the users
        if ($booking->discount_code){
            //dump("Discount code applies");
            $booking->base_price        = 0;
            $booking->cleaning_charge   = 0;
            $booking->guest_charge      = 0;
            $booking->iva_tax           = 0;
            $booking->accomodation_tax  = 0;
            $booking->security_money    = 0;
            $booking->service_charge    = 0;
            $booking->host_fee          = 0;
            $booking->total             = 0;
        } else {
            //dump("No discount for you");
            $booking->base_price        = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->subtotal);
            $booking->cleaning_charge   = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->cleaning_fee);
            $booking->guest_charge      = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->additional_guest);
            $booking->iva_tax           = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->iva_tax);
            $booking->accomodation_tax  = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->accomodation_tax);
            $booking->security_money    = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->security_fee);
            $booking->service_charge    = $service_fee  = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->service_fee);
            $booking->host_fee          = $host_fee     = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->host_fee);
            $booking->total             = $total_amount = $this->helper->convert_currency('', $currencyDefault->code, $data['price_list']->total);
        }

        

        $booking->currency_code     = $currencyDefault->code;
        $booking->transaction_id    = $data['transaction_id'] ?? " ";
        $booking->payment_method_id = $data['payment_method_id'] ?? " ";
        $booking->cancellation      = Properties::find($data['property_id'])->cancellation;
        //if($data['paymode'] == 'Bank') {
            //$booking->status            = 'Pending';
        //} else {
            $booking->status            = (Session::get('payment_booking_type') == 'instant') ? 'Accepted' : 'Pending';
        //}
        $booking->booking_type      = Session::get('payment_booking_type');

        foreach ($data['price_list']->date_with_price as $key => $value) {
            $allData[$key]['price'] = $this->helper->convert_currency('', $currencyDefault->code, $value->original_price);
            $allData[$key]['date'] = setDateForDb($value->date);
        }

       $booking->date_with_price   = json_encode($allData);



        if ($booking->booking_type == "instant" && $data['paymode'] <> 'Bank') {
            $this->addBookingPaymentInHostWallet($booking);
        }

        $booking->save();

        if ($data['paymode'] == 'Credit Card') {
            $booking_details['first_name']   = $data['first_name'];
            $booking_details['last_name ']   = $data['last_name'];
            $booking_details['postal_code']  = $data['postal_code'];
        }

        $booking_details['country']          = $data['country'];

        foreach ($booking_details as $key => $value) {
            $booking_details = new BookingDetails;
            $booking_details->booking_id = $booking->id;
            $booking_details->field = $key;
            $booking_details->value = $value;
            $booking_details->save();
        }

        do {
            $code = $this->helper->randomCode(6);
            $check_code = Bookings::where('code', $code)->get();
        } while (empty($check_code));

        $booking_code = Bookings::find($booking->id);

        $booking_code->code = $code;

        if ($booking->booking_type == "instant") {
            $dates = [];
            $propertyCurrencyCode = PropertyPrice::firstWhere('property_id', $data['property_id'])->currency_code;
            foreach ($data['price_list']->date_with_price as $dp) {
                $tmp_date = setDateForDb($dp->date);
                $property_data = [
                    'property_id' => $data['property_id'],
                    'status'      => 'Not available',
                    'price'       => $this->helper->convert_currency($data['price_list']->currency, $propertyCurrencyCode, $dp->original_price),
                    'date'        => $tmp_date
                ];

                PropertyDates::updateOrCreate(['property_id' => $data['property_id'], 'date' => $tmp_date], $property_data);
                if($data['paymode'] == 'Bank') {
                    array_push($dates, ['booking_id'=> $booking->id, 'date' => $tmp_date ]);
                }
            }

            if($data['paymode'] == 'Bank'  && count($dates) > 0) {
                BankDate::insert($dates);
            }
        }
        $booking_code->save();

        
        if ($booking->status == 'Accepted') {
            $payouts = new Payouts;
            $payouts->booking_id     = $booking->id;
            $payouts->user_id        = $booking->host_id;
            $payouts->property_id    = $booking->property_id;
            $payouts->user_type      = 'host';
            $payouts->amount         = $booking->original_host_payout;
            $payouts->penalty_amount = 0;
            $payouts->currency_code  = $booking->currency_code;
            $payouts->status         = 'Future';
            $payouts->save();
        }

        $message = new Messages;
        $message->property_id    = $data['property_id'];
        $message->booking_id     = $booking->id;
        $message->sender_id      = $booking->user_id;
        $message->receiver_id    = $booking->host_id;
        $message->message        = isset($data['message_to_host']) ? $data['message_to_host'] : '';
        $message->type_id        = 4;
        $message->read           = 0;
        $message->save();

        $email_controller = new EmailController;
        $email_controller->booking($booking->id, $checkinDate);
        $email_controller->booking_user($booking->id, $checkinDate);

        if($booking->booking_type == "instant" && $data['paymode'] == 'Bank') {
            $email_controller->bankAdminNotify($booking->id, $checkinDate);
        }


        if ($booking->status =='Accepted') {
            $companyName = Settings::getAll()->where('type', 'general')->where('name', 'name')->first()->value;
            $instantBookingConfirm = ($companyName.': ' .'Your booking is confirmed from'.' '. $booking->start_date.' '.'to'.' '.$booking->end_date );
            $instantBookingPaymentConfirm =($companyName.' ' .'Your payment is completed for'.' '.$booking->properties->name);

            twilioSendSms(Auth::user()->formatted_phone, $instantBookingConfirm);
            twilioSendSms(Auth::user()->formatted_phone, $instantBookingPaymentConfirm);

        } else {
            twilioSendSms(Auth::user()->formatted_phone, 'Your booking is initiated, Wait for confirmation');

        }

        Session::forget('payment_property_id');
        Session::forget('payment_checkin');
        Session::forget('payment_checkout');
        Session::forget('payment_number_of_guests');
        Session::forget('payment_booking_type');

        clearCache('.calc.property_price');
        return $code;
    }

    public function update($data)   {
        $code = $this->helper->randomCode(6);
        $booking = Bookings::find($data['booking_id']);
        $booking->transaction_id = $data['transaction_id'] ?? ' ';
        $booking->payment_method_id = $data['payment_method_id'] ?? ' ';
        $booking->code = $code;
        $booking->attachment        = $data['attachment'] ?? null;
        $booking->bank_id             = $data['bank_id'] ?? null;
        $booking->note             = $data['note'] ?? null;
        $booking->status            = 'Accepted';
        if($data['paymode'] == 'Bank') {
            $booking->status            = 'Processing';
        }
        $booking->save();

        $email_controller = new EmailController;
        $email_controller->booking($booking->id, $data['checkin'], $data['paymode'] == 'Bank');
        $email_controller->booking_user($booking->id, $data['checkin']);

        if($booking->booking_type == "instant" && $data['paymode'] == 'Bank') {
            $email_controller->bankAdminNotify($booking->id, $data['checkin']);
        }

        if($data['paymode'] <> 'Bank') {
            $this->addBookingPaymentInHostWallet($booking);
        }

        $dates = [];
        $propertyCurrencyCode = PropertyPrice::firstWhere('property_id', $data['property_id'])->currency_code;
        foreach ($data['price_list']->date_with_price as $dp) {
            $tmp_date = setDateForDb($dp->date);

            $property_data = [
                'property_id' => $data['property_id'],
                'status'      => 'Not available',
                'price'       => $this->helper->convert_currency($data['price_list']->currency, $propertyCurrencyCode, $dp->original_price),
                'date'        => $tmp_date,
            ];

            PropertyDates::updateOrCreate(['property_id' => $booking->property_id, 'date' => $tmp_date], $property_data);
            if($data['paymode'] == 'Bank') {
                array_push($dates, ['booking_id'=> $booking->id, 'date' => $tmp_date ]);
            }

            if($data['paymode'] == 'Bank' && count($dates) > 0) {
                BankDate::insert($dates);
            }
        }

        Bookings::where([['status', 'Processing'], ['property_id', $booking->property_id], ['start_date', $booking->start_date], ['payment_method_id','!=', 4]])
            ->orWhere([['status', 'Pending'], ['property_id', $booking->property_id], ['start_date', $booking->start_date], ['payment_method_id','!=', 4]])
            ->update(['status' => 'Expired']);



        if(!$data['paymode'] == 'Bank') {
            Payouts::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'user_type' => 'host',
                ],
                [
                    'user_id' => $booking->host_id,
                    'property_id' => $booking->property_id,
                    'amount' => $booking->original_host_payout,
                    'currency_code' => $booking->currency_code,
                    'status' => 'Future',
                ]);
        }

        $message = new Messages;
        $message->property_id    = $data['property_id'];
        $message->booking_id     = $booking->id;
        $message->sender_id      = $booking->user_id;
        $message->receiver_id    = $booking->host_id;
        
        $message->message        = isset($data['message_to_host']) ? $data['message_to_host'] : '';
        $message->type_id        = 4;
        $message->read           = 0;
        $message->save();

        BookingDetails::where(['id' => $data['booking_id']])->update(['value' => $data['country']]);


        $companyName = Settings::getAll()->where('type', 'general')->where('name', 'name')->first()->value;
        $instantBookingConfirm = ($companyName.': ' .'Your booking is confirmed from'.' '. $booking->start_date.' '.'to'.' '.$booking->end_date );
        $instantBookingPaymentConfirm =($companyName.' ' .'Your payment is completed for'.' '.$booking->properties->name);

        if($data['paymode'] == 'Bank') {
            $instantBookingConfirm = ($companyName.': ' .'Your booking is confirmed from'.' '. $booking->start_date.' '.'to'.' '.$booking->end_date .'. Admin will approve the booking very soon');
            $instantBookingPaymentConfirm =($companyName.' ' .'Your payment is completed for'.' '.$booking->properties->name. '. Admin will approve the booking very soon');
        }


        twilioSendSms(Auth::user()->formatted_phone, $instantBookingConfirm);
        twilioSendSms(Auth::user()->formatted_phone, $instantBookingPaymentConfirm);

        Session::forget('payment_property_id');
        Session::forget('payment_checkin');
        Session::forget('payment_checkout');
        Session::forget('payment_number_of_guests');
        Session::forget('payment_booking_type');
        Session::forget('payment_booking_status');
        Session::forget('payment_booking_id');

        clearCache('.calc.property_price');
        return $code;

    }

    public function withdraws(Request $request)
    {
        $photos = Photo::where('user_id', \Auth::user()->id)->get();
        $photo_ids = [];
        foreach ($photos as $key => $value) {
            $photo_ids[] = $value->id;
        }
        $payment_sum = Payment::whereIn('photo_id', $photo_ids)->sum('amount');
        $withdraw_sum = Withdraw::where('user_id', Auth::user()->id)->sum('amount');
        $data['total'] = $total = $payment_sum - $withdraw_sum;
        if ($request->isMethod('post')) {
            if ($total >= $request->amount) {
                $withdraw = new Withdraw;
                $withdraw->user_id = Auth::user()->id;
                $withdraw->amount = $request->amount;
                $withdraw->status = 'Pending';
                $withdraw->save();
                $data['success'] = 1;
                $data['message'] = 'Success';
            } else {
                $data['success'] = 0;
                $data['message'] = 'Balance exceed';
            }
            echo json_encode($data);
            exit;
        }

        $data['details'] = Auth::user()->details_key_value();
        $data['results'] = Withdraw::where('user_id', Auth::user()->id)->get();
        return view('payment.withdraws', $data);
    }
    public function addBookingPaymentInHostWallet($booking)
    {
        $walletBalance = Wallet::where('user_id',$booking->host_id)->first();
        $default_code = Currency::getAll()->firstWhere('default',1)->code;
        $wallet_code = Currency::getAll()->firstWhere('id', $walletBalance->currency_id)->code;
        $balance = ( $walletBalance->balance + $this->helper->convert_currency($default_code, $wallet_code, $booking->total)  - $this->helper->convert_currency($default_code, $wallet_code, $booking->service_charge) - $this->helper->convert_currency($default_code, $wallet_code, $booking->accomodation_tax) - $this->helper->convert_currency($default_code, $wallet_code, $booking->iva_tax) );
        Wallet::where(['user_id' => $booking->host_id])->update(['balance' => $balance]);
    }

}
