<?php

/**
 * Settings Controller
 *
 * Settings Controller manages Settings by admin.
 *
 * @category   Settings
 * @package    OzzieAccom
 * @author     Abhishek Singh
 * @copyright  OzzieAccom 2022
 * @license
 * @version    2.7
 * @link       Ozzieaccom.com
 * @email      info@ozzieaccom.com.au
 * @since      Version 1.3
 * @deprecated None
 */

namespace App\Http\Controllers\Admin;

use App\DataTables\BankDataTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Validator;
use Mail;
use DB;
use Session;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Common;

use App\Models\{Country, Settings, PaymentSetting, Language, Currency, PropertyFees, Admin};




class SettingsController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function general(Request $request)
    {
        if (! $request->isMethod('post')) {
            $general          = Settings::getAll()->whereIn('type', ['general','googleMap'])->toArray();
            $data['result']   = $this->helper->key_value('name', 'value', $general);
            $data['language'] = $this->helper->key_value('id', 'name', Language::where('status', '=', 'active')->get()->toArray());
            $data['currency'] = $this->helper->key_value('id', 'name', Currency::where('status', '=', 'active')->get()->toArray());

            return view('admin.settings.general', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'name'              => 'required',
                    'email'              => 'required|email',
                    'default_currency'  => 'required',
                    'default_language'  => 'required',
                );

            $fieldNames = array(
                    'name'              => 'Name',
                    'email'              => 'Email',
                    'default_currency'  => 'Default Currency',
                    'default_language'  => 'required',
                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    $head_code = is_null($request->head_code)?'':$request->head_code;
                    Settings::where(['name' => 'name'])->update(['value' => $request->name]);
                    Settings::where(['name' => 'email'])->update(['value' => $request->email]);
                    Settings::where(['name' => 'head_code'])->update(['value' =>  $head_code]);
                    Settings::where(['name' => 'default_currency'])->update(['value' => $request->default_currency]);
                    Settings::where(['name' => 'default_language'])->update(['value' => $request->default_language]);
                    Language::where('default', '=', '1')->update(['default' => '0']);
                    Language::where('id', $request->default_language)->update(['default' => '1']);

                    Currency::where('default', '=', '1')->update(['default' => '0']);
                    Currency::where('id', $request->default_currency)->update(['default' => '1']);

                    foreach ($_FILES["photos"]["error"] as $key => $error) {
                        $tmp_name = $_FILES["photos"]["tmp_name"][$key];

                        $name = str_replace(' ', '_', $_FILES["photos"]["name"][$key]);

                        $ext = pathinfo($name, PATHINFO_EXTENSION);

                        $name = time().'_'.$key.'.'.$ext;

                        if ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'ico') {
                            if (move_uploaded_file($tmp_name, "public/front/images/logos/".$name)) {
                                Settings::where(['name' => $key])->update(['value' => $name]);
                            }
                        }
                    }
                }
                event('eloquent.updated: App\Models\Settings', (new Settings()));

                $this->helper->one_time_message('success', 'Updated Successfully');

                Cache::forget(config('cache.prefix') . '.currency');

                return redirect('admin/settings');
            }
        }
    }

    public function preferences(Request $request)
    {
        if (!$request->isMethod('post')) {
            $preferences          = Settings::getAll()->where('type','preferences')->toArray();
            $data['result']       = $this->helper->key_value('name', 'value', $preferences);
            $data['row_per_page'] = ['10' => '10', '25' => '25', '50' => '50', '100' => '100'];
            $data['timezones']    = $timezones = phpDefaultTimeZones();
            return view('admin.settings.preference', $data);
        } else {
            $rules = array(
                    'row_per_page'            => 'required',
                    'min_search_price'               => 'required|numeric|min:1',
                    'max_search_price'               => 'required|numeric|gt:min_search_price'
                );

            $fieldNames = array(
                    'row_per_page'            => 'Row Per Page',
                    'min_search_price'               => 'Search Price (Min)',
                    'max_search_price'               => 'Search Price (Max)',
                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    unset($request['_token']);
                    if ($request['date_format'] == 0) {
                        $request['date_format_type']       = 'yyyy' . $request['date_separator'] . 'mm' . $request['date_separator'] . 'dd';
                        $request['front_date_format_type'] = 'yy' . $request['date_separator'] . 'mm' . $request['date_separator'] . 'dd';
                        $request['search_date_format_type']= 'yy' . $request['date_separator'] . 'm' . $request['date_separator'] . 'd';
                    } elseif ($request['date_format'] == 1) {
                        $request['date_format_type']       = 'dd' . $request['date_separator'] . 'mm' . $request['date_separator'] . 'yyyy';
                        $request['front_date_format_type'] = 'dd' . $request['date_separator'] . 'mm' . $request['date_separator'] . 'yy';
                        $request['search_date_format_type']= 'd' . $request['date_separator'] . 'm' . $request['date_separator'] . 'yy';
                    } elseif ($request['date_format'] == 2) {
                        $request['date_format_type']       = 'mm' . $request['date_separator'] . 'dd' . $request['date_separator'] . 'yyyy';
                        $request['front_date_format_type'] = 'mm' . $request['date_separator'] . 'dd' . $request['date_separator'] . 'yy';
                        $request['search_date_format_type']= 'm' . $request['date_separator'] . 'd' . $request['date_separator'] . 'yy';
                    } elseif ($request['date_format'] == 3) {
                        $request['date_format_type']       = 'dd' . $request['date_separator'] . 'M' . $request['date_separator'] . 'yyyy';
                        $request['front_date_format_type'] = 'dd' . $request['date_separator'] . 'M' . $request['date_separator'] . 'yy';
                        $request['search_date_format_type']= 'd' . $request['date_separator'] . 'M' . $request['date_separator'] . 'yy';
                    } elseif ($request['date_format'] == 4) {
                        $request['date_format_type']       = 'yyyy' . $request['date_separator'] . 'M' . $request['date_separator'] . 'dd';
                        $request['front_date_format_type'] = 'yy' . $request['date_separator'] . 'M' . $request['date_separator'] . 'dd';
                        $request['search_date_format_type']= 'yy' . $request['date_separator'] . 'M' . $request['date_separator'] . 'd';
                    }

                    foreach($request->all() as $key => $value) {
                        $matches            = ['name' => $key, 'type'=>'preferences'];
                        $preferences        = Settings::firstOrNew($matches);
                        $preferences->name  = $key;
                        $preferences->value = $value;
                        $preferences->type  = 'preferences';
                        $preferences->save();
                    }

                    $pref = Settings::getAll()->where('type', 'preferences');
                    if (! empty($pref)) {
                        foreach ($pref as $value) {
                            $prefer[$value->name] = $value->value;
                        }
                        Session::put($prefer);
                    }
                }
            }
            event('eloquent.updated: App\Models\Settings', (new Settings()));

            $this->helper->one_time_message('success', 'Updated Successfully');

            return redirect('admin/settings/preferences');
        }
    }

    public function photos(Request $request)
    {
        if (! $request->isMethod('post')) {
             $photos = Settings::getAll()->where('type', 'photos')->toArray();
             $data['result'] = $this->helper->key_value('name', 'value', $photos);
             return view('admin.settings.photos', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'photo_min_height' => 'required',
                    'photo_min_width'  => 'required',
                    'photo_max_size'   => 'required'
                );


            $fieldNames = array(
                    'photo_min_height' => 'Minimum Height',
                    'photo_min_width'  => 'Minimum Width',
                    'photo_max_size'   => 'Max Size'
                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'photo_min_height'])->update(['value' => $request->photo_min_height]);
                    Settings::where(['name' => 'photo_min_width'])->update(['value' => $request->photo_min_width]);
                    Settings::where(['name' => 'photo_max_size'])->update(['value' => $request->photo_max_size]);

                }
                event('eloquent.updated: App\Models\Settings', (new Settings()));

                $this->helper->one_time_message('success', 'Updated Successfully');

                return redirect('admin/settings/photos');
            }
        }
    }

    public function email(Request $request)
    {
        if (! $request->isMethod('post')) {
             $general         = Settings::getAll()->where('type', 'email')->toArray();
             $data['result']  = $this->helper->key_value('name', 'value', $general);
             $data['drivers'] = array('smtp'=>'SMTP','sendmail'=>'Send Mail');
             return view('admin.settings.email', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'driver'            => 'required',
                    'host'              => 'required',
                    'port'              => 'required',
                    'from_address'      => 'required',
                    'from_name'         => 'required',
                    'encryption'        => 'required',
                    'username'          => 'required',
                    'password'          => 'required',
                );

            $fieldNames = array(
                    'driver'            => 'Driver',
                    'host'              => 'Host',
                    'port'              => 'Port',
                    'from_address'      => 'From Address',
                    'from_name'         => 'From Name',
                    'encryption'        => 'Encryption',
                    'username'          => 'Username',
                    'password'          => 'Password',
                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if ($request->driver=='smtp') {
                    \Config::set([
                    'mail.driver'     => isset($request->driver) ? $request->driver : '',

                    'mail.host'       => isset($request->host) ? $request->host : '',

                    'mail.port'       => isset($request->port) ?$request->port : '',

                    'mail.from'       => ['address' => isset($request->from_address) ? $request->from_address : '',
                    'name'            => isset($request->from_name) ? $request->from_name : '' ],

                    'mail.encryption' => isset($request->encryption) ? $request->encryption : '',

                    'mail.username'   => isset($request->username) ? $request->username : '',

                    'mail.password'   => isset($request->password) ? $request->password : ''
                    ]);
                    $adminDetails     = Admin::where('status', 'active')->first();
                    $fromInfo         = \Config::get('mail.from');
                    $user             = [];
                    $user['to']       = $fromInfo['address'];
                    $user['from']     = $adminDetails->email;
                    $user['fromName'] = ucfirst($adminDetails->username);
                    try {
                        $ok = Mail::send('emails.verify', ['user' => $user], function ($m) use ($user) {
                            $m->from($user['from'], $user['fromName']);
                            $m->to($user['to']);
                            $m->subject('Verify SMTP Settings');
                        });
                        $field    = 'email_status';
                        $res      =  DB::table('settings')->where(['name' => $field])->count();
                        if ($res==0) {
                            DB::insert(DB::raw("INSERT INTO settings(name,value,type) VALUES ('$field','1','email')"));
                        } else {
                            DB::table('settings')->where(['name' => $field])->update(array('name'=>$field,'value' => 1));
                        }
                        $this->helper->one_time_message('success', 'Updated Successfully');
                    } catch (\Exception $e) {
                        $field    = 'email_status';
                        $res      =  DB::table('settings')->where(['name' => $field])->count();
                        if ($res == 0) {
                            DB::insert(DB::raw("INSERT INTO settings(name,value,type) VALUES ('$field','0','email')"));
                        } else {
                            DB::table('settings')->where(['name' => $field])->update(array('name'=>$field,'value' => 0));
                        }
                    }

                    if (env('APP_MODE', '') != 'test') {
                        Settings::where(['name' => 'driver', 'type' => 'email'])->update(['value' => $request->driver]);
                        Settings::where(['name' => 'host', 'type' => 'email'])->update(['value' => $request->host]);
                        Settings::where(['name' => 'port', 'type' => 'email'])->update(['value' => $request->port]);
                        Settings::where(['name' => 'from_address', 'type' => 'email'])->update(['value' => $request->from_address]);
                        Settings::where(['name' => 'from_name', 'type' => 'email'])->update(['value' => $request->from_name]);
                        Settings::where(['name' => 'encryption', 'type' => 'email'])->update(['value' => $request->encryption]);
                        Settings::where(['name' => 'username', 'type' => 'email'])->update(['value' => $request->username]);
                        Settings::where(['name' => 'password', 'type' => 'email'])->update(['value' => $request->password]);
                    }
                } else {
                    if (env('APP_MODE', '') != 'test') {
                        Settings::where(['name' => 'driver'])->update(['value' => $request->driver]);
                    }

                    $this->helper->one_time_message('success', 'Updated Successfully');
                }
                event('eloquent.updated: App\Models\Settings', (new Settings()));


                return redirect('admin/settings/email');
            }
        }
    }



    public function paymentMethods(Request $request, BankDataTable $dataTable)
    {

        if (! $request->isMethod('post')) {
             $paypal = Settings::getAll()->where('type', 'PayPal')->toArray();
             $stripe = Settings::getAll()->where('type', 'Stripe')->toArray();
             $data['paypal'] = $this->helper->key_value('name', 'value', $paypal);
             $data['stripe'] = $this->helper->key_value('name', 'value', $stripe);
             $data['countries'] = Country::getAll()->pluck('name','id');
             if($this->n_as_k_c()) {
                Session::flush();
                return view('vendor.installer.errors.admin');
            }

             return $dataTable->render('admin.settings.payment', $data);
        } elseif ($request['gateway'] == 'paypal') {
            $rules = array(
                    'username'      => 'required',
                    'password'      => 'required',
                    'signature'     => 'required',
                    'mode'          => 'required',
                    'paypal_status' => 'required',
                );


            $fieldNames = array(
                    'username'      => 'PayPal Username',
                    'password'      => 'PayPal Password',
                    'signature'     => 'PayPal Signature',
                    'mode'          => 'PayPal Mode',
                    'paypal_status' => 'Paypal Status',
                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                $data['success'] = 0;
                $data['errors']  = $validator->messages();
                echo json_encode($data);
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'username', 'type' => 'PayPal'])->update(['value' => $request->username]);
                    Settings::where(['name' => 'password', 'type' => 'PayPal'])->update(['value' => $request->password]);
                    Settings::where(['name' => 'signature', 'type' => 'PayPal'])->update(['value' => $request->signature]);
                    Settings::where(['name' => 'mode', 'type' => 'PayPal'])->update(['value' => $request->mode]);

                    $match                  = ['type'=>'PayPal','name'=>'paypal_status'];
                    $paymentSettings        = Settings::firstOrNew($match);
                    $paymentSettings->name  = 'paypal_status';
                    $paymentSettings->value = $request->paypal_status;
                    $paymentSettings->type  = 'PayPal';
                    $paymentSettings->save();

                }

                $data['message'] = 'Updated Successfully';
                $data['success'] = 1;
                echo json_encode($data);
            }
        } elseif ($request['gateway'] == 'stripe') {
            $rules = array(
                'secret_key'            => 'required',
                'publishable_key'       => 'required',
                'stripe_status'         => 'required',
            );

            $fieldNames = array(
                'secret_key'        => 'Secret Key',
                'publishable_key'   => 'Publishable Key',
                'stripe_status'     => 'Stripe Status',
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                $data['success'] = 0;
                $data['errors'] = $validator->messages();
                echo json_encode($data);
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'secret', 'type' => 'Stripe'])->update(['value' => $request->secret_key]);
                    Settings::where(['name' => 'publishable', 'type' => 'Stripe'])->update(['value' => $request->publishable_key]);

                    $match                  = ['type'=>'Stripe','name'=>'stripe_status'];
                    $paymentSettings        = Settings::firstOrNew($match);
                    $paymentSettings->name  = 'stripe_status';
                    $paymentSettings->value = $request->stripe_status;
                    $paymentSettings->type  = 'Stripe';
                    $paymentSettings->save();
                }

                $data['message'] = 'Updated Successfully';
                $data['success'] = 1;
                echo json_encode($data);
            }
        }
    }

    public function socialLinks(Request $request)
    {
        if (! $request->isMethod('post')) {
              $general = Settings::getAll()->where('type', 'join_us')->toArray();
              $data['result'] = $this->helper->key_value('name', 'value', $general);

              return view('admin.settings.social', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'facebook'          => 'required',
                    'google_plus'       => 'required',
                    'twitter'           => 'required',
                    'linkedin'          => 'required',
                    'pinterest'         => 'required',
                    'youtube'           => 'required',
                    'instagram'         =>'required'

                );

            $fieldNames = array(
                    'facebook'            => 'Facebook',
                    'google_plus'         => 'Google Plus',
                    'twitter'             => 'Twitter',
                    'linkedin'            => 'Linkedin',
                    'pinterest'           => 'Pinterest',
                    'youtube'             => 'Youtube',
                    'instagram'           => 'Instagram'

                 );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'facebook'])->update(['value' => $request->facebook]);
                    Settings::where(['name' => 'google_plus'])->update(['value' => $request->google_plus]);
                    Settings::where(['name' => 'twitter'])->update(['value' => $request->twitter]);
                    Settings::where(['name' => 'linkedin'])->update(['value' => $request->linkedin]);
                    Settings::where(['name' => 'pinterest'])->update(['value' => $request->pinterest]);
                    Settings::where(['name' => 'youtube'])->update(['value' => $request->youtube]);
                    Settings::where(['name' => 'instagram'])->update(['value' => $request->instagram]);

                }
                event('eloquent.updated: App\Models\Settings', (new Settings()));
                $this->helper->one_time_message('success', 'Updated Successfully');
                return redirect('admin/settings/social-links');
            }
        }
    }

    public function socialLogin(Request $request) {
        if (! $request->isMethod('post')) {
            $general = Settings::getAll()->where('type', 'social')->toArray();
            $data['social'] = $this->helper->key_value('name', 'value', $general);
            return view('admin.settings.socialite', $data);
        }

        elseif ($request->isMethod('post')) {
            $rules = array(
                'facebook_login'          => ['required', Rule::in([0,1]),],
                'google_login'       => ['required', Rule::in([0,1]),],
            );

            $fieldNames = array(
                'facebook_login'            => 'Facebook Login',
                'google_login'         => 'Google Login'
            );

            $validator = Validator::make($request->all(), $rules);

            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'facebook_login'])->update(['value' => $request->facebook_login]);
                    Settings::where(['name' => 'google_login'])->update(['value' => $request->google_login]);
                }
                event('eloquent.updated: App\Models\Settings', (new Settings()));
                $this->helper->one_time_message('success', 'Updated Successfully');
                return redirect('admin/settings/social-logins');
            }
        }
    }

    public function apiInformations(Request $request)
    {
        if (! $request->isMethod('post')) {
              $data['google'] = Settings::where('type', 'google')->pluck('value', 'name')->toArray();
              $data['google_map'] = Settings::where('type', 'googleMap')->pluck('value', 'name')->toArray();
              $data['facebook'] = Settings::where('type', 'facebook')->pluck('value', 'name')->toArray();
              return view('admin.api_credentials', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'facebook_client_id'     => 'required',
                    'facebook_client_secret' => 'required',
                    'google_client_id'       => 'required',
                    'google_client_secret'   => 'required',
                    'google_map_key'         => 'required',
                    );

            $fieldNames = array(
                        'facebook_client_id'     => 'Facebook Client ID',
                        'facebook_client_secret' => 'Facebook Client Secret',
                        'google_client_id'       => 'Google Client ID',
                        'google_client_secret'   => 'Google Client Secret',
                        'google_map_key'   => 'Google Map Browser Key',
                        'google_map_server_key'   => 'Google Map Server Key',
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    Settings::where(['name' => 'client_id', 'type' => 'Facebook'])->update(['value' => $request->facebook_client_id]);

                    Settings::where(['name' => 'client_secret', 'type' => 'Facebook'])->update(['value' => $request->facebook_client_secret]);

                    Settings::where(['name' => 'client_id', 'type' => 'Google'])->update(['value' => $request->google_client_id]);

                    Settings::where(['name' => 'client_secret', 'type' => 'Google'])->update(['value' => $request->google_client_secret]);

                    Settings::where(['name' => 'key', 'type' => 'GoogleMap'])->update(['value' => $request->google_map_key]);

                    event('eloquent.updated: App\Models\Settings', (new Settings()));

                    $this->helper->one_time_message('success', 'Updated Successfully');
                }

                return redirect('admin/settings/api-informations');
            }
        } else {
            return redirect('admin/settings/api-informations');
        }
    }

    public function fees(Request $request)
    {
        if ($request->isMethod('post')) {
            $rules = array(

                    'guest_service_charge'  => 'required|numeric|max:99|min:0',
                    'iva_tax'               => 'required|numeric|max:99|min:0',
                    'accomodation_tax'      => 'required|numeric|max:99|min:0'

                );

            $fieldNames = array(

                    'guest_service_charge'   => 'Guest service charge',
                    'iva_tax'                => 'I.V.A Tax',
                    'accomodation_tax'       => 'Accomadation Tax',
                );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {

                PropertyFees::where(['field' => 'guest_service_charge'])->update(['value' => $request->guest_service_charge]);
                PropertyFees::where(['field' => 'iva_tax'])->update(['value' => $request->iva_tax]);
                PropertyFees::where(['field' => 'accomodation_tax'])->update(['value' => $request->accomodation_tax]);

                $this->helper->one_time_message('success', 'Updated Successfully');
            }
        }

        $fees = PropertyFees::pluck('value', 'field')->toArray();
        $data['result'] = $fees;
        return view('admin.settings.fees', $data);
    }
    public function deleteLogo(Request $request)
    {

        $logo             = $request->company_logo;
        if (isset($logo)) {
            $record = Settings::where('name', 'logo')->first();
            if ($record) {
                $record->value=null;
                $record->save();

                if ($logo != null) {
                    $dir = public_path("front/images/logos/$logo");
                    if (file_exists($dir)) {
                        unlink($dir);
                    }
                }
                $data['success']  = 1;
                $data['message'] = 'Image has been deleted successfully!';
            } else {
                $data['success']  = 0;
                $data['message'] = "No Record Found!";
            }
        }
        echo json_encode($data);
        exit();
    }

    public function deleteFavIcon(Request $request)
    {
        $favicon = $request->company_favicon;
        if (isset($favicon)) {
            $record = Settings::where('name', 'favicon')->first();
            if ($record) {
                $record->value=null;
                $record->save();

                if ($record != null) {
                    $dir = public_path("front/images/logos/$record");
                    if (file_exists($dir)) {
                        unlink($dir);
                    }
                }
                $data['success']  = 1;
                $data['message'] = 'Favicon has been deleted successfully!';
            } else {
                $data['success']  = 0;
                $data['message'] = "No Record Found!";
            }
        }
        echo json_encode($data);
        exit();
    }
     public function smsSettings(Request $request)
    {

        $phoneSms         = Settings::where('type','twilio')->get()->toArray();
        $data['phoneSms']  = $this->helper->key_value('name', 'value', $phoneSms);

      if (!$request->isMethod('post')) {
          return view('admin.settings.sms',$data);
      } else {
            unset($request['_token']);
            foreach ($request->all() as $key => $value) {
                $match               = ['name' => $key, 'type' => 'twilio'];
                $smsSettings         = Settings::firstOrNew($match);
                $smsSettings->name   = $key;
                $smsSettings->value  = $value;
                $smsSettings->type   = 'twilio';
                $smsSettings->save();
            }
           $this->helper->one_time_message('success', 'Updated Successfully');
           return redirect('admin/settings/sms');
        }
  }
  public function n_as_k_c() {
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

