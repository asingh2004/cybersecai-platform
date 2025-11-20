<?php

namespace App\Http\Controllers;

use Cache;
use Auth;
use DB;
use Session;
use DateTime;

//New added on 29 May 2022
use Carbon\Carbon;

use App\Http\Helpers\Common;
use App\Http\Controllers\CalendarController;
use Omnipay\Omnipay;
use Illuminate\Http\Request;
use Validator;

use App\Models\{Favourite,
    Properties,
    Bank,
    Payment,
    User,
    PaymentMethods,
    Messages,
    PropertyDetails,
    PropertyAddress,
    PropertyPhotos,
    PropertyPrice,
    PropertyType,
    PropertyDates,
    PropertyDescription,
    Currency,
    Settings,
    SpaceType,
    BedType,
    RespiteType,
    PropertySteps,
    Country,
    Amenities,
    AmenityType};

class PropertyController extends Controller
{
    public function __construct()
    {
        $this->helper = new Common;
    }

    public function userProperties(Request $request)
    {
        switch ($request->status) {
            case 'Listed':
            case 'Unlisted':
                $pram = [['status', '=', $request->status]];
                break;
            default:
                $pram = [];
                break;
        }

        $data['status'] = $request->status;
        $data['properties'] = Properties::with('property_price', 'property_address')
                                ->where('host_id', Auth::id())
                                ->where($pram)
                                ->orderBy('id', 'desc')
                                ->paginate(Session::get('row_per_page'));
        $data['currentCurrency'] =  $this->helper->getCurrentCurrency();

   
      
        return view('property.listings', $data);
    }

    public function userFacilities(Request $request)
    {
        switch ($request->subscription_status) {
            case 'active':
            case 'inactive':
                $pram = [['status', '=', $request->subscription_status]];
                break;
            default:
                $pram = [];
                break;
        }

        $data['paypal_status'] = Settings::getAll()->where('name', 'paypal_status')
                                ->where('type', 'PayPal')->first();

        $data['stripe_status'] = Settings::getAll()->where('name', 'stripe_status')
                                ->where('type', 'Stripe')->first();

        $data['banks'] = Bank::getAll()->where('status', 'Active')->count();

        $data['business_name'] = DB::table('users')
                                        ->where('users.id', Auth::id())
                                         ->get();
                                    
       
        $properties  = DB::table('users')
                        ->join('properties', 'properties.host_id', '=', 'users.id')
                        ->where('users.id', Auth::id())
                        ->select('properties.id')
                        ->get();

         
        $data['daily_subscription_fee'] = DB::table('property_fees')
                                        ->where('field', '=', 'nursing_home_daily_subs_fees')
                                        ->select('value')
                                         ->get();



        $data['properties'] = $properties;

                          
        $data['facilities']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['properties']), true))
                            ->select('property_address.address_line_1')
                            ->distinct()
                            ->get();

        $data['facilities_cnt']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['properties']), true))
                            ->count(DB::raw('DISTINCT property_address.address_line_1'));
                                

        session(['properties_count' => $data['facilities_cnt']]);
        session(['amount'  => $data['daily_subscription_fee']]);
        session(['userId' => Auth::id()]);
        session(['businessname' => $data['business_name']]);


        return view('property.facilities')->with($data);
    }


public function createBooking(Request $request)
    {
        $paypal_credentials = Settings::getAll()->where('type', 'PayPal')->pluck('value', 'name');
        $currencyDefault    = Currency::getAll()->where('default', 1)->first();
        $user_id = Auth::user()->id;
        $account_id = $request->email;
        $properties_subscribed = $request->facilities_cnt;
        //$subs_status = $request->subscription_status;
        $amount = $request->subscription_total;
        $sub_renewal_value = $request->sub_renewal_date;
    

        // Set the new timezone
        date_default_timezone_set('Australia/Adelaide');       
        // Following two lines are working as at 29 May, changign to add more logic to it - 29 May 2022
        //$today = date('Y-m-d H:i:s');
        //$end = date('Y-m-d H:i:s', strtotime('+1 year'));

        // Begin new code - Logic 29 May 2022

        // Check if use is already subscribed, if yes, start date must be  set to renewal day

        $sub_renewal_value = User::where('id', $user_id)->pluck('sub_renewal_date')->first();

        $launch_date = Carbon::create(2022, 9, 1, 0);
        $currentDay = Carbon::now();
        
        // Check if user is already subscribed, if yes, start date must be  set to renewal day rather than the today
        if(preg_match("/0{4}/" , $sub_renewal_value))
        {

            $difference_calc = $currentDay->diffForHumans($launch_date);

            //If subscription is occuring in future, 1 year subscription period applies, else few extra months plus 1 year for price of 1 year subs
            if(preg_match("/after/" , $difference_calc)){

                $today = date('Y-m-d H:i:s');
                $end = date('Y-m-d H:i:s', strtotime('+1 year'));
            } else {
                $today = date('Y-m-d H:i:s');
                $end= $launch_date->addYear();
            }
        }
        else
        {
            $today = date('Y-m-d H:i:s', strtotime($sub_renewal_value));
            $end_carbon = Carbon::create($today);
            $end= $end_carbon->addYear();
        }
         
        // End new code - Logic 29 May 2022   

        Session::put('subs_start_date', $today);
        Session::put('sub_renewal_date', $end);
        Session::save();

  
        if ($request->payment_method == 'stripe') {
            return redirect('facilities/stripe');


        } elseif ($request->payment_method == 'bank') {
            $data = $this->getDataForBooking();
            $data['banks'] = Bank::getAll()->where('status', 'Active');
            return view('payment.bankprovider', $data);
        } else {
            $data = [
                'checkin'          => $today,
                'checkout'         => $end,
                'transaction_id'   => '',
                'paymode'          => ''
            ];

            // Temp deactivation ----         $code = $this->store($data);
            return view('property.facilities')->with($data);
        }
    }



public function getDataForBooking() {
        $data['userid']             = Session::get('userId');
        $data['accountemail']           = Session::get('username'); 
        $data['subscription_fees']  = Session::get('amount');
        $data['countofproperty']    = Session::get('properties_count'); 
        $data['substatus']          = Session::get('subs_status');
        $data['subs_start_date']    = Session::get('subs_start_date');
        $data['sub_renewal_date']   = Session::get('sub_renewal_date');

        return $data;
    }

public function stripePayment(Request $request)
    {
        $data = $this->getDataForBooking();
        $stripe                   = Settings::getAll()->where('type', 'Stripe')->pluck('value', 'name');
        $data['publishable']      = $stripe['publishable'];
        return view('payment.stripeprovider', $data);
    }


public function bankPayment(Request $request) {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();

        if(!$request->isMethod('post')) {
            $data = $this->getDataForBooking();
            $data['banks'] = Bank::getAll()->where('status', 'Active');
            return view('payment.bankprovider', $data);
        }

        $validate = Validator::make($request->all(), [
            'attachment' => 'required|file|mimes:jpeg,bmp,png,jpg,JPG,JPEG,pdf,doc,docx|max:1024',
            'bank' => 'required'
        ]);

        if ($validate->fails()) {
            return redirect('/facilities/bank-payment')->withErrors($validate)->withInput();

        }

    
        Session::put('payment_method', 'BANK Transfer');
        Session::save();

        $this->success();
        return view('payment.ackbankpayment');
    }


public function stripeRequest(Request $request)
    {
        $currencyDefault = Currency::getAll()->where('default', 1)->first();

        foreach(Session::get('amount') as $total) {
            //$subscription_paidvar = Session::get('amount');
            $subscription_paidvar =$total->value * Session::get('properties_count') * 7 * 52 * 1.1;
        }

        if ($request->isMethod('post')) {

            if (isset($request->stripeToken)) {
  
                $stripe        = Settings::getAll()->where('type', 'Stripe')->pluck('value', 'name');
                $gateway = Omnipay::create('Stripe');
                $gateway->setApiKey($stripe['secret']);
                //info('Price = ' . $price_eur);
                $response = $gateway->purchase([
                    'amount' => $subscription_paidvar,
                    'currency' => $currencyDefault->code,
                    'token' => $request->stripeToken,
                ])->send();


                if ($response->isSuccessful()) {
                    $pm    = PaymentMethods::where('name', 'Stripe')->first();
                    $data  = [
                        'transaction_id'   => $response->getTransactionReference()
                    ];


                    $vStripe = "STRIPE- ";

                    $payment_method = $vStripe . $data['transaction_id'];
                    Session::put('payment_method', $payment_method);
                    Session::save();

                    $this->success();

                    /*if (isset($booking_id) && !empty($booking_id)) {
                         $code = $this->update($data);
                     }else{
                        $code = $this->store($data);
                    }*/

                    $this->helper->one_time_message('success', trans('messages.success.payment_complete_success'));
                    //return redirect('booking/requested?code='.$code);
                    return view('payment.ackbankpayment');
                } else {
                    $this->helper->one_time_message('error', $response->getMessage());
                    return back();
                }
            } else {

                $this->helper->one_time_message('success', trans('messages.error.payment_request_error'));
                return back();
                //return redirect('payments/book/'.Session::get('payment_property_id'));
            }
        }
    }



    public function success()
    {
        //Following two line are replaced by 2 lines below it - 30 May2022
        //$todayvar = date('Y-m-d H:i:s');
        //$endvar = date('Y-m-d H:i:s', strtotime('+1 year'));

        $todayvar = Session::get('subs_start_date');
        $endvar = Session::get('sub_renewal_date');


        $today                    = new DateTime(setDateForDb($todayvar));
        $end                       = new DateTime(setDateForDb($endvar));



      
        foreach(Session::get('amount') as $total) {
            //$subscription_paidvar = Session::get('amount');
            $subscription_paidvar =$total->value * Session::get('properties_count') * 7 * 52 * 1.1;
        }


       User::where('id', Session::get('userId'))
              ->update(
                        ['facilities_subscribed' => Session::get('properties_count'),
                        'sub_activation_date' => $today,
                        'sub_renewal_date' => $end,
                        'subscription_paid' => $subscription_paidvar,
                        'sub_payment_method'=> Session::get('payment_method'),
                        'subscription_status' => 'active']          
       );
    }


    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            $rules = array(
                'property_type_id'  => 'required',
                'space_type'        => 'required',
                'accommodates'      => 'required',
                'map_address'       => 'required',
            );

            $fieldNames = array(
                'property_type_id'  => 'Home Type',
                'space_type'        => 'Room Type',
                'accommodates'      => 'Accommodates',
                'map_address'       => 'City',
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $property                  = new Properties;
                $property->host_id         = Auth::id();
                $property->name            = SpaceType::getAll()->find($request->space_type)->name.' in '.$request->city;
                $property->property_type   = $request->property_type_id;
                $property->space_type      = $request->space_type;
                $property->respite_service_type = $request->respite_service_type;
                $property->accommodates    = $request->accommodates;
                $property->save();

                $property_address                 = new PropertyAddress;
                $property_address->property_id    = $property->id;
                $property_address->address_line_1 = $request->route;
                $property_address->city           = $request->city;
                $property_address->state          = $request->state;
                $property_address->country        = $request->country;
                $property_address->postal_code    = $request->postal_code;
                $property_address->latitude       = $request->latitude;
                $property_address->longitude      = $request->longitude;
                $property_address->save();

                $property_price                 = new PropertyPrice;
                $property_price->property_id    = $property->id;
                $property_price->currency_code  = \Session::get('currency');
                $property_price->save();

                $property_steps                   = new PropertySteps;
                $property_steps->property_id      = $property->id;
                $property_steps->save();

                $property_description              = new PropertyDescription;
                $property_description->property_id = $property->id;
                $property_description->save();

                return redirect('listing/'.$property->id.'/basics');
            }
        }

        $data['property_type'] = PropertyType::getAll()->where('status', 'Active')->pluck('name', 'id');
        $data['space_type']    = SpaceType::getAll()->where('status', 'Active')->pluck('name', 'id');

        return view('property.create', $data);
    }

    public function listing(Request $request, CalendarController $calendar)
    {

        $step            = $request->step;
        $property_id     = $request->id;
        $data['step']    = $step;
        $data['result']  = Properties::where('host_id', Auth::id())->findOrFail($property_id);
        $data['details'] = PropertyDetails::pluck('value', 'field');
        $data['missed']  = PropertySteps::where('property_id', $request->id)->first();


        if ($step == 'basics') {
            if ($request->isMethod('post')) {
                $property                     = Properties::find($property_id);
                $property->bedrooms           = $request->bedrooms;
                $property->beds               = $request->beds;
                $property->bathrooms          = $request->bathrooms;
                $property->bed_type           = $request->bed_type;
                $property->property_type      = $request->property_type;
                $property->space_type         = $request->space_type;

                //$property->respite_service_type = $request->respite_service_type;
                $property->respite_type = $request->respite_type;

                if ($request->respite_type == 1){
                    $property->respite_service_type = 'LOW Care';
                } elseif ($request->respite_type == 2) {
                    $property->respite_service_type = 'HIGH Care';
                } elseif ($request->respite_type == 3){
                    $property->respite_service_type = 'NDIS';
                } elseif ($request->respite_type == 4){
                    $property->respite_service_type = 'Tour';
                } else {
                   $property->respite_service_type = 'Other'; 
                }
    

                $property->accommodates       = $request->accommodates;
                $property->save();

                $property_steps         = PropertySteps::where('property_id', $property_id)->first();
                $property_steps->basics = 1;
                $property_steps->save();
                return redirect('listing/'.$property_id.'/description');
            }

            $data['bed_type']       = BedType::getAll()->pluck('name', 'id');
            Cache::forget(config('cache.prefix') . '.property.types.respite');
            $data['respite_type']       = RespiteType::getAll()->pluck('name', 'id');
            $data['property_type']  = PropertyType::getAll()->where('status', 'Active')->pluck('name', 'id');
            $data['space_type']     = SpaceType::getAll()->pluck('name', 'id');
            if($this->scattered()) {
                Session::flush();
                return view('vendor.installer.errors.user');
            }
        } elseif ($step == 'description') {
            if ($request->isMethod('post')) {

                $rules = array(
                    'name'     => 'required|max:300',
                    'summary'  => 'required|max:1000'
                );

                $fieldNames = array(
                    'name'     => 'Name',
                    'summary'  => 'Summary',
                );

                $validator = Validator::make($request->all(), $rules);
                $validator->setAttributeNames($fieldNames);

                if ($validator->fails())
                {
                    return back()->withErrors($validator)->withInput();
                }
                else
                {
                    $property           = Properties::find($property_id);
                    $property->name     = $request->name;
                    $property->slug     = $this->helper->pretty_url($request->name);
                    $property->save();

                    $property_description              = PropertyDescription::where('property_id', $property_id)->first();
                    $property_description->summary     = $request->summary;
                    $property_description->save();

                    $property_steps              = PropertySteps::where('property_id', $property_id)->first();
                    $property_steps->description = 1;
                    $property_steps->save();
                    return redirect('listing/'.$property_id.'/location');
                }
            }
            $data['description']       = PropertyDescription::where('property_id', $property_id)->first();
        } elseif ($step == 'details') {
            if ($request->isMethod('post')) {
                $property_description                       = PropertyDescription::where('property_id', $property_id)->first();
                $property_description->about_place          = $request->about_place;
                $property_description->place_is_great_for   = $request->place_is_great_for;
                $property_description->guest_can_access     = $request->guest_can_access;
                $property_description->interaction_guests   = $request->interaction_guests;
                $property_description->other                = $request->other;
                $property_description->about_neighborhood   = $request->about_neighborhood;
                $property_description->get_around           = $request->get_around;
                $property_description->save();

                return redirect('listing/'.$property_id.'/description');
            }
        } elseif ($step == 'location') {
            if ($request->isMethod('post')) {
                $rules = array(
                    'address_line_1'    => 'required|max:250',
                    'address_line_2'    => 'max:250',
                    'country'           => 'required',
                    'city'              => 'required',
                    'state'             => 'required',
                    'latitude'          => 'required|not_in:0',
                );

                $fieldNames = array(
                    'address_line_1' => 'Address Line 1',
                    'country'        => 'Country',
                    'city'           => 'City',
                    'state'          => 'State',
                    'latitude'       => 'Map',
                );

                $messages = [
                    'not_in' => 'Please set :attribute pointer',
                ];

                $validator = Validator::make($request->all(), $rules, $messages);
                $validator->setAttributeNames($fieldNames);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                } else {
                    $property_address                 = PropertyAddress::where('property_id', $property_id)->first();
                    $property_address->address_line_1 = $request->address_line_1;
                    $property_address->address_line_2 = $request->address_line_2;
                    $property_address->latitude       = $request->latitude;
                    $property_address->longitude      = $request->longitude;
                    $property_address->city           = $request->city;
                    $property_address->state          = $request->state;
                    $property_address->country        = $request->country;
                    $property_address->postal_code    = $request->postal_code;
                    $property_address->save();

                    $property_steps           = PropertySteps::where('property_id', $property_id)->first();
                    $property_steps->location = 1;
                    $property_steps->save();

                    return redirect('listing/'.$property_id.'/amenities');
                }
            }
            $data['country']       = Country::pluck('name', 'short_name');
        } elseif ($step == 'amenities') {
            if ($request->isMethod('post') && is_array($request->amenities)) {
                $rooms            = Properties::find($request->id);
                $rooms->amenities = implode(',', $request->amenities);
                $rooms->save();
                return redirect('listing/'.$property_id.'/photos');
            }
            $data['property_amenities'] = explode(',', $data['result']->amenities);
            $data['amenities']          = Amenities::where('status', 'Active')->get();
            $data['amenities_type']     = AmenityType::get();



        } elseif ($step == 'photos') {
            if($request->isMethod('post')) {
                if($request->crop == 'crop' && $request->photos) {
                    $baseText = explode(";base64,", $request->photos);
                    $name = explode(".", $request->img_name);
                    $convertedImage = base64_decode($baseText[1]);
                    $request->request->add(['type'=>end($name)]);
                    $request->request->add(['image'=>$convertedImage]);


                    $validate = Validator::make($request->all(), [
                        'type' => 'required|in:png,jpg,JPG,JPEG,jpeg,bmp',
                        'img_name' => 'required',
                        'photos' => 'required',
                    ]);
                } else {
                    $validate = Validator::make($request->all(), [
                        'file' => 'required|file|mimes:jpg,jpeg,bmp,png,gif,JPG',
                        'file' => 'dimensions:min_width=640,min_height=360'
                    ]);
                }

                if($validate->fails()) {
                    return back()->withErrors($validate)->withInput();
                }

                $path = public_path('images/property/'.$property_id.'/');

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                if($request->crop == "crop") {
                    $image = $name[0].uniqid().'.'.end($name);
                    $uploaded = file_put_contents($path . $image, $convertedImage);
                } else {
                    if (isset($_FILES["file"]["name"])) {
                        $tmp_name = $_FILES["file"]["tmp_name"];
                        $name = str_replace(' ', '_', $_FILES["file"]["name"]);
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $image = time() . '_' . $name;
                        $path = 'public/images/property/' . $property_id;
                        if ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'JPG') {
                            $uploaded = move_uploaded_file($tmp_name, $path . "/" . $image);
                        }
                    }
                }

                if ($uploaded) {
                    $photos = new PropertyPhotos;
                    $photos->property_id = $property_id;
                    $photos->photo = $image;
                    $photos->serial = 1;
                    $photos->cover_photo = 1;

                    $exist = PropertyPhotos::orderBy('serial', 'desc')
                        ->select('serial')
                        ->where('property_id', $property_id)
                        ->take(1)->first();

                    if (!empty($exist->serial)) {
                        $photos->serial = $exist->serial + 1;
                        $photos->cover_photo = 0;
                    }
                    $photos->save();
                    $property_steps = PropertySteps::where('property_id', $property_id)->first();
                    $property_steps->photos = 1;
                    $property_steps->save();
                }

                return redirect('listing/'.$property_id.'/photos')->with('success', 'File Uploaded Successfully!');

            }

            $data['photos'] = PropertyPhotos::where('property_id', $property_id)
                ->orderBy('serial', 'asc')
                ->get();

        } elseif ($step == 'pricing') {


            if ($request->isMethod('post')) {


                if ( isset( $_FILES['file'] ) ) {
                    if ($_FILES['file']['type'] == "application/pdf") {

                        $path = public_path('agreements/'.$property_id.'/');



                        if (!file_exists($path)) {
                            mkdir($path, 0777, true);
                        }

                    if (isset($_FILES["file"]["name"])) {
                        $tmp_name = $_FILES["file"]["tmp_name"];
                        $name = str_replace(' ', '_', $_FILES["file"]["name"]);
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $fpdf = time() . '_' . $name;
                        $path = 'public/agreements/' . $property_id;
                        if ($ext == 'docx' || $ext == 'doc' || $ext == 'pdf' || $ext == 'PDF' || $ext == 'DOCX') {
                            $uploaded = move_uploaded_file($tmp_name, $path . "/" . $fpdf);
                        }
                    }
     
                } else {
                    
                    return redirect('listing/'.$property_id.'/pricing')->with('error', 'File Uploaded Failed, check filetype!');
                }

                Properties::where('id', $property_id)
                ->update(
                    ['agreement' => $path . "/" . $fpdf]          
                    );

                $property_steps = PropertySteps::where('property_id', $property_id)->first();
                $property_steps->pricing =1;
                $property_steps->save();
                
            }
            
            return redirect('listing/'.$property_id.'/pricing')->with('success', 'File Uploaded Successfully!');

        } 

        } elseif ($step == 'booking') {
            if ($request->isMethod('post')) {


                $property_steps          = PropertySteps::where('property_id', $property_id)->first();
                $property_steps->booking = 1;
                $property_steps->save();

                $properties               = Properties::find($property_id);
                $properties->booking_type = $request->booking_type;
                $properties->status       = ( $properties->steps_completed == 0 ) ?  'Listed' : 'Unlisted';
                $properties->save();


                return redirect('listing/'.$property_id.'/calendar');
            }
        } elseif ($step == 'calendar') {
            $data['calendar'] = $calendar->generate($request->id);
        }

        return view("listing.$step", $data);
    }


    public function updateStatus(Request $request)
    {
        $property_id = $request->id;
        $reqstatus = $request->status;
        if ($reqstatus == 'Listed') {
            $status = 'Unlisted';
        }else{
            $status = 'Listed';
        }
        $properties         = Properties::where('host_id', Auth::id())->find($property_id);
        $properties->status = $status;
        $properties->save();
        return  response()->json($properties);

    }

    public function getPrice(Request $request)
    {

        return $this->helper->getPrice($request->property_id, $request->checkin, $request->checkout, $request->guest_count);
    }

    public function single(Request $request)
    {

        $data['property_slug'] = $request->slug;


        $data['result'] = $result = Properties::where('slug', $request->slug)->first();

        if ( empty($result)  ) {
            abort('404');
        }

         $data['property_id'] = $id = $result->id;

        $data['property_photos']     = PropertyPhotos::where('property_id', $id)->orderBy('serial', 'asc')
            ->get();

        $data['amenities']        = Amenities::normal($id);
        $data['safety_amenities'] = Amenities::security($id);

        $property_address         = $data['result']->property_address;

        $latitude                 = $property_address->latitude;

        $longitude                = $property_address->longitude;

        $data['checkin']          = (isset($request->checkin) && $request->checkin != '') ? $request->checkin:'';
        $data['checkout']         = (isset($request->checkout) && $request->checkout != '') ? $request->checkout:'';

        $data['guests']           = (isset($request->guests) && $request->guests != '')?$request->guests:'';

        $data['respite_service_type'] = (isset($request->respite_service_type) && $request->respite_service_type != '')?$request->respite_service_type:'';

        $data['similar']  = Properties::join('property_address', function ($join) {
                                        
                                        $join->on('property_address.property_id', '=', 'properties.id');
        })
                                    ->select(DB::raw('*, ( 3959 * acos( cos( radians('.$latitude.') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians('.$longitude.') ) + sin( radians('.$latitude.') ) * sin( radians( latitude ) ) ) ) as distance'))
                                    ->having('distance', '<=', 5)
                                    ->where('properties.host_id', '!=', Auth::id())
                                    ->where('properties.id', '!=', $id)
                                    ->where('properties.status', 'Listed')
                                    ->get();

        $data['title']    =   $data['result']->name.' in '.$data['result']->property_address->city;
        $data['symbol'] = $this->helper->getCurrentCurrencySymbol();
        $data['shareLink'] = url('/').'/'.'properties/'.$data['property_id'];

        $data['date_format'] = Settings::getAll()->firstWhere('name', 'date_format_type')->value;
        return view('property.single', $data);
    }

    public function currencySymbol(Request $request)
    {
        $symbol          = Currency::code_to_symbol($request->currency);
        $data['success'] = 1;
        $data['symbol']  = $symbol;

        return json_encode($data);
    }

    public function photoMessage(Request $request)
    {
        $property = Properties::find($request->id);
        if ($property->host_id == \Auth::user()->id) {
            $photos = PropertyPhotos::find($request->photo_id);
            $photos->message = $request->messages;
            $photos->save();
        }

        return json_encode(['success'=>'true']);
    }

    public function photoDelete(Request $request)
    {
        $property   = Properties::find($request->id);
        if ($property->host_id == \Auth::user()->id) {
            $photos = PropertyPhotos::find($request->photo_id);
            $photos->delete();
        }

        return json_encode(['success'=>'true']);
    }

    public function makeDefaultPhoto(Request $request)
    {

        if ($request->option_value == 'Yes') {
            PropertyPhotos::where('property_id', '=', $request->property_id)
            ->update(['cover_photo' => 0]);

            $photos = PropertyPhotos::find($request->photo_id);
            $photos->cover_photo = 1;
            $photos->save();
        }
        return json_encode(['success'=>'true']);
    }

    public function makePhotoSerial(Request $request)
    {

        $photos         = PropertyPhotos::find($request->id);
        $photos->serial = $request->serial;
        $photos->save();

        return json_encode(['success'=>'true']);
    }


    public function set_slug()
    {

       $properties   = Properties::where('slug', NULL)->get();
       foreach ($properties as $key => $property) {

           $property->slug     = $this->helper->pretty_url($property->name);
           $property->save();
       }
       return redirect('/');

    }

    public function userBookmark()
    {

        $data['bookings'] = Favourite::with(['properties' => function ($q) {
            $q->with('property_address');
        }])->where(['user_id' => Auth::id(), 'status' => 'Active'])->orderBy('id', 'desc')
            ->paginate(Settings::getAll()->where('name', 'row_per_page')->first()->value);
        return view('users.favourite', $data);
    }

    public function addEditBookMark()
    {
        $property_id = request('id');
        $user_id = request('user_id');

        $favourite = Favourite::where('property_id', $property_id)->where('user_id', $user_id)->first();

        if (empty($favourite)) {
            $favourite = Favourite::create([
                'property_id' => $property_id,
                'user_id' => $user_id,
                'status' => 'Active',
            ]);

        } else {
            $favourite->status = ($favourite->status == 'Active') ? 'Inactive' : 'Active';
            $favourite->save();
        }

        return response()->json([
            'favourite' => $favourite
        ]);
    }

    public function scattered() {
        if(!g_e_v()) {
            return true;
        }
        if(!g_c_v()) {
            try {
                $d_ = g_d();
                $e_ = g_e_v();
                $e_ = explode('.', $e_);
                $c_ = md5($d_ . $e_[1]);
                if($e_[0] == $c_) {
                    p_c_v();
                    return false;
                }
            } catch(\Exception $e) {
                return true;
            }
        }
        return false;
    }
}
