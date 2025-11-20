<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\Common;
use Illuminate\Support\Facades\Route;
use App\Models\OpenAIConfig;
use App\Models\AIModel;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailController;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Illuminate\Support\Facades\Cache;


use App\DataTables\TransactionDataTable;


use Auth, Validator, Socialite, Mail, DateTime, Hash, Excel, DB, Image, Session;

use App\Models\{
    User,
    UserDetails,
    Messages,
    Country,
    PasswordResets,
    Payment,
    Notification,
    Timezone,
    Reviews,
    Accounts,
    UsersVerification,
    Properties,
    Payouts,
    Bookings,
    Currency,
    Settings,
    Wallet,
    Withdrawal
};




class UserController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }
  
      
    public function create(Request $request, EmailController $email_controller)
{
    $rules = array(
        'first_name' => 'required|max:255',
        'last_name' => 'required|max:255',
        'email' => 'required|max:255|email|unique:users',
        'password' => 'required|min:6',
    );

    $messages = array(
        'required' => ':attribute is required.',
    );

    $fieldNames = array(
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'password' => 'Password',
    );

    $validator = Validator::make($request->all(), $rules, $messages);
    $validator->setAttributeNames($fieldNames);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    } else {
        $user = new User();
        $user->first_name = strip_tags($request->first_name);
        $user->last_name  = strip_tags($request->last_name);
        $user->email      = $request->email;
        $user->password   = bcrypt($request->password);
        $user->status     = 'Inactive';          // <--- Required!
        $user->email_verified_at = null;         // <--- Required!
        $user->save();

        $user_verification = new UsersVerification();
        $user_verification->user_id = $user->id;
        $user_verification->save();

        $this->wallet($user->id);

        // Send verification and welcome email
        $email_controller->welcome_email($user);

    try {
        app(\App\Http\Controllers\EmailController::class)->saas_admin_new_user($user);
    } catch (\Throwable $ex) {
        \Log::error('SaaS admin approval email (UserController) failed: ' . $ex->getMessage());
    }

        // DO NOT LOG USER IN!
        $this->helper->one_time_message('success', 'Registration complete. Please verify your email and await admin approval.');
        return redirect('login');
    }
      
      
}

  
  
  	public function dashboard()
    {
        // Fetch all 'name' values where user_id is 1 and status is "Listed"
        $configs = OpenAIConfig::where('user_id', 1) // You might want to use Auth::id() for dynamic user access
            ->where('status', 'Listed')
            ->pluck('name'); // Get only the name values

        // Return to the dashboard view with the list of names
        return view('users.dashboard', compact('configs'));
    }


  
  	public function updateConfigs(Request $request)
	{
    $selectedNames = $request->input('selected_names'); // Assuming input name is 'selected_names[]'

    // Get the logged-in user's ID
    $userId = Auth::id();

    foreach ($selectedNames as $name) {
        // Create a new entry in OpenAIConfig for the logged-in user with the same values
        $config = OpenAIConfig::where('name', $name)
            ->where('user_id', 1) // Get original values from user_id=1
            ->first();

        if ($config) {
            // Create a new instance for the logged-in user
            $newConfig = $config->replicate(); // Copy model properties
            $newConfig->user_id = $userId; // Update user_id
            $newConfig->save(); // Save new instance
            
            // Check if the ai_template_type_id is 2
            if ($config->ai_template_type_id == 2) {
                // Get the corresponding variables from the ai_models table
                $aiModel = AiModel::find($config->ai_model_id); // Assuming you have an AiModel model

                if ($aiModel) {
                    $name = escapeshellarg($aiModel->name);
                    $instructions = escapeshellarg($config->instructions);
                    $aiFormalName = escapeshellarg($aiModel->ai_formal_name);

                    // Construct the command to call the Python script
                    $command = "python3 /home/cybersecai/htdocs/www.cybersecai.io/public/python/assistant/assistant.py " .
                        "$name " .
                        "$instructions " . 
                        "$aiFormalName 2>&1"; // Capture stderr

                    // Execute the command and capture output
                    exec($command, $output, $returnVar);

                    // Log the output from the Python script
                    Log::info("Command executed: $command");
                    Log::info("Instruction: $instructions");
                    Log::info("AI Model: $aiFormalName");        
                    
                    // Execute the command and capture the output
                    $output = [];
                    $returnVar = 0;
                    exec($command, $output, $returnVar);
                    
                    if ($returnVar === 0) {
                        // Get the assistant_id from the output (assuming it prints the id as a single line)
                        if (isset($output[0])) {
                            $assistantId = trim($output[0]);  // Ensure it's trimmed to remove whitespace

                            // Update the assistant_id in the OpenAIConfig table
                            $newConfig->assistant_id = $assistantId;
                            $newConfig->save();  // Save the updated configuration
                        } else {
                            Log::error("Python script did not return an assistant ID. Output: " . implode("\n", $output));
                        }
                    } else {
                        // Handle error case
                        Log::error('Python script execution failed. Return code: ' . $returnVar . '. Output: ' . implode("\n", $output));
                    }

                } else {
                    // Log error if the AiModel is not found
                    Log::error("AiModel with ID {$config->ai_model_id} not found.");
                }
            }
        }
    }

    return redirect()->back()->with('success', 'Configurations updated successfully!');
	}

  

    public function execdashboard()
    {
        $data['title'] = 'Dashboard';
        $user_id = Auth::user()->id;

        //Fro TRIAl PURPOSE only
        //$user_id = 1847;

        $data['account_email'] = DB::table('users')
                                ->where('id', $user_id)
                                ->select('email')
                                ->pluck('email');

        $email_domain1 = explode("@", $data['account_email']);
        $email_domain = explode("\"", $email_domain1[1]);

        $data['domain_email'] = $email_domain[0];

        /*$data['domain_email'] = DB::table('Analytics_email')
                            ->where('email', '=', json_decode( json_encode($data['account_email']), true))
                            ->select('email_domain')->get();
        */

        $data['cnt_accounts'] = DB::table('users')
                            ->where(DB::raw("SUBSTRING_INDEX(email, '@', -1)"), '=', json_decode( json_encode($data['domain_email']), true))
                            ->select('id')
                            ->count('id');

        $data['accounts'] = DB::table('users')
                            ->where(DB::raw("SUBSTRING_INDEX(email, '@', -1)"), '=', json_decode( json_encode($data['domain_email']), true))
                            ->select('id')
                            ->get();

        $data['accounts_facilities_arr'] = DB::table('users')
                            ->wherein('id', json_decode( json_encode($data['accounts']), true))
                            ->select('email', 'first_name','MAC_facilities', 'facilities_subscribed', 'subscription_paid')
                            ->get();


        $data['account_properties']  = DB::table('users')
                        ->join('properties', 'properties.host_id', '=', 'users.id')
                        ->wherein('users.id', json_decode( json_encode($data['accounts']), true))
                        ->select('properties.id')
                        ->get();

                          
        $data['acct_facilities_cnt']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['account_properties']), true))
                            ->count(DB::raw('DISTINCT property_address.address_line_1'));

        $data['acct_listings_cnt']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['account_properties']), true))
                            ->count('property_address.address_line_1');

        $data['acct_facilities_all']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['account_properties']), true))
                            ->select(DB::raw('DISTINCT property_address.address_line_1'))
                            ->get();
                         

        $data['acct_facilities_listings_all']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['account_properties']), true))
                            ->select('property_address.address_line_1', 'properties.name')
                            ->get();


        $data['wallet'] = wallet::where('user_id', $user_id)->first();
        $data['list'] = Properties::where('host_id', $user_id)->count();
        $data['trip'] = Bookings::where(['user_id' => $user_id, 'status' => 'Accepted'])->count();

        $bookings = Bookings::select('payment_method_id', 'currency_code',
            DB::raw('(total - service_charge - iva_tax - accomodation_tax) as total'), 'created_at', DB::raw('1 as type'))
            ->where(['host_id' => $user_id, 'status' => 'Accepted']);

        $trips = Bookings::select('payment_method_id', 'currency_code', 'total', 'created_at', DB::raw('-1 as type'))
            ->where(['user_id' => $user_id, 'status' => 'Accepted']);

        $data['transactions'] = Withdrawal::with('payment_methods','currency')
            ->select('payment_method_id', 'currency_id', 'amount', 'created_at', DB::raw('0 as type'))
            ->where(['user_id' => $user_id, 'status' => 'Success'])->union($bookings)->union($trips)
            ->orderBy('created_at', 'desc')->take(12)->get();

        $data['bookings'] = Bookings::with('users', 'properties')
            ->where('status', '=', 'Accepted')
            ->wherein('host_id', json_decode( json_encode($data['accounts']), true))
            ->orderBy('id', 'desc')->take(12)->get();


        $data['bookings_pending'] = Bookings::with('users', 'properties')
            ->where('status', '=', 'Pending')
            ->wherein('host_id', json_decode( json_encode($data['accounts']), true))
            ->orderBy('id', 'desc')->take(12)->get();
            

        $data['bookings_expired'] = Bookings::with('users', 'properties')
            ->where('status', '=', 'Expired')
            ->wherein('host_id', json_decode( json_encode($data['accounts']), true))
            //->where(DB::raw("SUBSTRING_INDEX(email, '@', -1)"), '=', json_decode( json_encode($data['domain_email']), true))
            ->orderBy('id', 'desc')->take(12)->get();


        $data['bookings_declined'] = Bookings::with('users', 'properties')
            ->where('status', '=', 'Declined')
            ->wherein('host_id', json_decode( json_encode($data['accounts']), true))
            ->orderBy('id', 'desc')->take(12)->get();

        
        $data['bookings_processing'] = Bookings::with('users', 'properties')
            ->where('status', '=', 'Processing')
            ->wherein('host_id', json_decode( json_encode($data['accounts']), true))
            ->orderBy('id', 'desc')->take(12)->get();



        $data['currentCurrency'] = $this->helper->getCurrentCurrency();

        $data['properties']  = DB::table('users')
                        ->join('properties', 'properties.host_id', '=', 'users.id')
                        ->where('users.id', $user_id)
                        ->select('properties.id')
                        ->get();

                          
        $data['facilities_cnt']     = DB::table('property_address')
                            ->join('properties', 'property_address.property_id', '=', 'properties.id')
                            ->where('properties.status', '=', 'Listed')
                            ->wherein('properties.id', json_decode( json_encode($data['properties']), true))
                            ->count(DB::raw('DISTINCT property_address.address_line_1'));
                           
                       

        return view('users.exec_dashboard', $data);

    }

  
  
  	    public function profile(Request $request, EmailController $email_controller)
        {
            $user = User::find(Auth::user()->id);

            // For country dropdown in blade
            $data['countries'] = Country::orderBy('name')->pluck('name', 'short_name')->toArray();

            // For user details textarea/address (if you want to use the UserDetails table)
            $data['details'] = UserDetails::where('user_id', $user->id)->pluck('value', 'field')->toArray();

            if ($request->isMethod('post')) {
                $rules = [
                    'first_name'     => 'required|max:255',
                    'last_name'      => 'required|max:255',
                    'email'          => 'required|email|max:255|unique:users,email,' . Auth::user()->id,
                    'phone'          => 'required|max:255',
                    'default_country'=> 'required|max:5',
                    'ABN'            => 'nullable|max:255',
                    'supplier_type'  => 'nullable|max:255',
                ];

                $fieldNames = [
                    'first_name'     => 'First name',
                    'last_name'      => 'Last name',
                    'email'          => 'Email',
                    'phone'          => 'Phone',
                    'default_country'=> 'Country',
                    'ABN'            => 'Company ID',
                    'supplier_type'  => 'Industry/Sector',
                ];

                $validator = Validator::make($request->all(), $rules);
                $validator->setAttributeNames($fieldNames);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Save main user data
                $user->first_name      = $request->first_name;
                $user->last_name       = $request->last_name;
                $user->email           = $request->email;
                $user->phone           = preg_replace("/[\s-]+/", '', $request->phone);
                $user->carrier_code    = $request->carrier_code;
                $user->default_country = $request->default_country;
                $user->formatted_phone = $request->formatted_phone;
                $user->ABN             = $request->ABN;
                $user->supplier_type   = $request->supplier_type;
                $user->save();

                // Save user_verification info as needed
                $user_verification = UsersVerification::where('user_id', $user->id)->first();
                if ($user_verification) {
                    $user_verification->email = 'no';
                    $user_verification->save();
                }

                // Save user details/live/about via details[] in form
                if ($request->has('details') && is_array($request->details)) {
                    foreach ($request->details as $key => $value) {
                        UserDetails::updateOrCreate(
                            ['user_id' => $user->id, 'field' => $key],
                            ['value' => $value]
                        );
                    }
                }

                $this->helper->one_time_message('success', trans('messages.profile.profile_updated'));
                return redirect()->back();
            }

            $data['profile'] = $user;

            return view('users.profile', $data);
        }

    public function media()
    {
        $data['result'] = $user = User::find(Auth::user()->id);

        if (isset($_FILES["photos"]["name"])) {
            foreach ($_FILES["photos"]["error"] as $key => $error) {
                $tmp_name     = $_FILES["photos"]["tmp_name"][$key];
                $name         = str_replace(' ', '_', $_FILES["photos"]["name"][$key]);
                $ext          = pathinfo($name, PATHINFO_EXTENSION);
                $name         = 'profile_'.time().'.'.$ext;
                $path         = 'public/images/profile/'.Auth::user()->id;
                $oldImagePath =  public_path('images/profile').'/'.Auth::user()->id.'/'.$data['result']->profile_image;
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                if ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif') {
                    if(!empty($user->profile_image) && file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                    if (move_uploaded_file($tmp_name, $path."/".$name)) {
                        $user->profile_image  = $name;
                        $user->save();
                        $this->helper->one_time_message('success', trans('messages.users_media.uploaded'));
                    }
                }

            }
        }
        return view('users.media', $data);
    }


    public function accountPreferences(Request $request, EmailController $email_controller)
    {
        $data['currency_code'] = Currency::where('default', 1)->first();
        $currency_code = $data['currency_code']->code;

        if (!$request->isMethod('post')) {
            $data['payouts']   = Accounts::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->get();
            $data['country']   = Country::all()->pluck('name', 'short_name');
            return view('account.preferences', $data);
        } else {
            $account                    =   new Accounts;
            $account->user_id           = Auth::user()->id;
            $account->address1          = $request->address1;
            $account->address2          = $request->address2;
            $account->city              = $request->city;
            $account->state             = $request->state;
            $account->postal_code       = $request->postal_code;
            $account->country           = $request->country;
            $account->payment_method_id = $request->payout_method;
            $account->account           = $request->account;
            $account->currency_code     = $currency_code;

            $account->save();


            $account_check = Accounts::where('user_id', Auth::user()->id)->where('selected', 'Yes')->get();

            if ($account_check->count() == 0) {
                $account->selected = 'Yes';
                $account->save();
            }
            $updateTime = dateFormat($account->updated_at);


            $email_controller->account_preferences($account->id,$type = "update", $updateTime);

            $this->helper->one_time_message('success', trans('messages.success.payout_update_success'));
            return redirect('users/account-preferences');
        }
    }

    public function accountDelete(Request $request, EmailController $email_controller)
    {
        $account = Accounts::find($request->id);
        if ($account->selected == 'Yes') {
            $this->helper->one_time_message('success', "Selected payout is default");
            return redirect('users/account-preferences');
        } else {


            $account->delete();
            $updateTime = dateFormat($account->updated_at);
            $email_controller->account_preferences($account->id, 'delete', $updateTime);

            $this->helper->one_time_message('success', "Payout account successfully deleted");
            return redirect('users/account-preferences');
        }
    }

    public function accountDefault(Request $request, EmailController $email_controller)
    {
        $account = Accounts::find($request->id);




        if ($account->selected == 'Yes') {
            $this->helper->one_time_message('success', trans('messages.error.payout_account_error'));
            return redirect('users/account-preferences');
        } else {
            $account_all       = Accounts::where('user_id', \Auth::user()->id)->update(['selected'=>'No']);
            $account->selected = 'Yes';
            $account->save();
            $updateTime = dateFormat($account->updated_at);

            $email_controller->account_preferences($account->id, 'default_update', $updateTime );

            $this->helper->one_time_message('success', trans('messages.success.default_payout_success'));
            return redirect('users/account-preferences');
        }
    }

    public function security(Request $request)
    {
        if ($request->isMethod('post')) {
            $rules = array(
                'old_password'          => 'required',
                'new_password'          => 'required|min:6|max:30|different:old_password',
                'password_confirmation' => 'required|same:new_password|different:old_password'
            );

            $fieldNames = array(
                'old_password'          => 'Old Password',
                'new_password'          => 'New Password',
                'password_confirmation' => 'Confirm Password'
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $user = User::find(Auth::user()->id);

                if (!Hash::check($request->old_password, $user->password)) {
                    return back()->withInput()->withErrors(['old_password' => trans('messages.profile.pwd_not_correct')]);
                }

                $user->password = bcrypt($request->new_password);

                $user->save();

                $this->helper->one_time_message('success', trans('messages.profile.pwd_updated'));
                return redirect('users/security');
            }
        }
        return view('account.security');
    }

    public function show(Request $request)
    {
        $data['result'] = User::findOrFail($request->id);
        $data['details'] = UserDetails::where('user_id', $request->id)->pluck('value', 'field')->toArray();

        $data['reviews_from_guests'] = Reviews::with('users', 'properties')->where(['receiver_id'=>$request->id, 'reviewer'=>'guest'])->orderBy('id', 'desc')->get();
        $data['reviews_from_hosts'] = Reviews::with('users', 'properties')->where(['receiver_id'=>$request->id, 'reviewer'=>'host'])->orderBy('id', 'desc')->get();

        $data['reviews_count'] = $data['reviews_from_guests']->count() + $data['reviews_from_hosts']->count();

        $data['title'] = $data['result']->first_name."'s Profile ";

        return view('users.show', $data);
    }

    public function transactionHistory(TransactionDataTable $dataTable)
    {

        $data['from'] = isset(request()->from) ? request()->from : null;
        $data['to']   = isset(request()->to) ? request()->to : null;

        $data['title']  = 'Transaction History';
        return $dataTable->render('account.transaction_history',$data);
    }

    public function getCompletedTransaction(Request $request)
    {
        $transaction        = $this->transaction_result();

        if ($request->from) {

            $transaction->whereDate('payouts.created_at', '>=', $request->from);
        }
        if ($request->to) {
            $transaction->whereDate('payouts.created_at', '<=', $request->to);
        }
        if ($request->status) {
            $transaction->where('payouts.status', '=', $request->status);
        }
        $transaction_result = $transaction->paginate(Session::get('row_per_page'))->toJson();
        echo $transaction_result;
    }

    public function transaction_result()
    {
        $where['user_id']   = Auth::user()->id;

        $transaction        = Payouts::join('properties', function ($join) {
            $join->on('properties.id', '=', 'payouts.property_id');
        })
        ->select('payouts.*', 'properties.name as property_name')
        ->where($where)
        ->orderBy('updated_at', 'DESC');

        return $transaction;
    }


    public function verification(Request $request)
    {
        $data          = [];
        $data['title'] = 'Verify your account';
        return view('users.verification', $data);
    }

   
  public function confirmEmail(Request $request)
{
    \Log::info('confirmEmail CALLED with token=' . $request->code);

    $password_resets = PasswordResets::whereToken($request->code);

    if ($password_resets->count()) {
        $password_result = $password_resets->first();
        \Log::info('PasswordResets email: ' . $password_result->email . ', created_at=' . $password_result->created_at);

        $user = User::where('email', $password_result->email)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$user) {
            \Log::warning('User NOT FOUND for email: ' . $password_result->email);
            $this->helper->one_time_message('danger', trans('messages.login.invalid_token'));
            return redirect('login');
        }

        $user->email_verified_at = now();
        $user->save();
        \Log::info('Set email_verified_at for: ' . $user->email);

        $user_verification = UsersVerification::where('user_id', $user->id)->first();
        if ($user_verification) {
            $user_verification->email = 'yes';
            $user_verification->save();
        }

        $password_resets->delete();

        $this->helper->one_time_message('success', 'Your email has been confirmed. Please wait for admin approval before logging in.');
        return redirect('login');
    } else {
        \Log::warning('[confirmEmail] password_resets not found for token=' . $request->code);
        $this->helper->one_time_message('danger', trans('messages.login.invalid_token'));
        return redirect('login');
    }
}
  
    public function newConfirmEmail(Request $request, EmailController $emailController)
    {
        $userInfo = User::find(Auth::user()->id);

        $emailController->new_email_confirmation($userInfo);

        $this->helper->one_time_message('success', trans('messages.profile.new_confirm_link_sent', ['email'=>$userInfo->email]));
        if ($request->redirect == 'verification') {
            return redirect('users/edit-verification');
        } else {
            return redirect('dashboard');
        }
    }

    public function facebookLoginVerification()
    {
        Session::put('verification', 'yes');
        return Socialite::with('facebook')->redirect();
    }

    public function facebookConnect(Request $request)
    {
        $facebook_id = $request->id;

        $verification = UsersVerification::find(Auth::user()->id);
        $verification->facebook = 'yes';
        $verification->fb_id = $facebook_id;
        $verification->save();
        $this->helper->one_time_message('success', trans('messages.profile.connected_successfully', ['social'=>'Facebook']));
        return redirect('users/edit_verification');
    }

    public function facebookDisconnectVerification(Request $request)
    {
        $verification = UsersVerification::find(Auth::user()->id);
        $verification->facebook = 'no';
        $verification->fb_id = '';
        $verification->save();
        $this->helper->one_time_message('success', trans('messages.profile.disconnected_successfully', ['social'=>'Facebook']));
        return redirect('users/edit_verification');
    }

    public function googleLoginVerification()
    {
        Session::put('verification', 'yes');
        return Socialite::with('google')->redirect();
    }

    public function googleConnect(Request $request)
    {
        $google_id = $request->id;

        $verification = UsersVerification::find(Auth::user()->id);

        $verification->google = 'yes';
        $verification->google_id = $google_id;

        $verification->save();

        $this->helper->one_time_message('success', trans('messages.profile.connected_successfully', ['social'=>'Google']));
        return redirect('users/edit-verification');
    }

    public function googleDisconnect(Request $request)
    {
        $verification = UsersVerification::find(Auth::user()->id);

        $verification->google = 'no';
        $verification->google_id = '';

        $verification->save();

        $this->helper->one_time_message('success', trans('messages.profile.disconnected_successfully', ['social'=>'Google']));
        return redirect('users/edit-verification');
    }


    public function reviews(Request $request)
    {
        $data['title'] = "Reviews";
        $data['reviewsAboutYou'] = Reviews::where('receiver_id', Auth::user()->id)
        ->orderBy('id', 'desc')
        ->get();
        return view('users.reviews_tpl', $data);
    }

    public function reviewsByYou(Request $request)
    {
        $data['title'] = "Reviews";
        $data['reviewsByYou'] = Reviews::with('properties','bookings')->where('sender_id', Auth::user()->id)
                                ->orderBy('id', 'desc')
                                ->paginate(Session::get('row_per_page'), ['*'], 'you');

        $data['reviewsToWrite'] = Bookings::with('properties','host','users')->whereRaw('DATEDIFF(now(),end_date) <= 14')
            ->whereRaw('DATEDIFF(now(),end_date)>=1')
            ->where('status', 'Accepted')
            ->where(function ($query) {
                return $query->where('user_id', Auth::id())->orWhere('host_id', Auth::id());
            })
            ->whereDoesntHave('reviews')->paginate(Session::get('row_per_page'), ['*'], 'write');

        $data['expiredReviews'] = Bookings::with(['reviews'])->whereRaw('DATEDIFF(now(),end_date) > 14')->where('status', 'Accepted')->where(function ($query) {
            return $query->where('user_id', Auth::user()->id)->orWhere('host_id', Auth::user()->id);
        })->has('reviews', '<', 1)->paginate(Session::get('row_per_page'), ['*'], 'expired');

        if ($request->expired) {
            $data['expired'] = 'active';
        } elseif ($request->you){
            $data['you'] = 'active';
        } else {
            $data['write'] = 'active';
        }

        return view('users.reviews_you', $data);
    }

    public function editReviews(Request $request)
    {
        $data['title']  = 'Update your reviews';
        $data['result'] = $reservationDetails = Bookings::findOrFail($request->id);

        if (Auth::user()->id == $reservationDetails->user_id) {
            $reviewsChecking = Reviews::where(['booking_id'=>$request->id, 'reviewer'=>'guest'])->get();
            $data['review_id'] = ($reviewsChecking->count()) ? $reviewsChecking[0]->id : '';
        } else {
            $reviewsChecking = Reviews::where(['booking_id'=>$request->id, 'reviewer'=>'host'])->get();
            $data['review_id'] = ($reviewsChecking->count()) ? $reviewsChecking[0]->id : '';
        }

        if (!$request->isMethod('post')) {
            if ($reservationDetails->user_id == Auth::user()->id) {
                return view('users.edit_reviews_guest', $data);
            } elseif ($reservationDetails->host_id == Auth::user()->id) {
                return view('users.edit_reviews_host', $data);
            } else {
                return abort(404);
            }
        } else {
            $data  = $request;
            if ($data->review_id == '') {
                $reviews = new Reviews;
            } else {
                $reviews = Reviews::find($data->review_id);
            }

            $reviews->booking_id = $reservationDetails->id;
            $reviews->property_id = $reservationDetails->property_id;

            if ($reservationDetails->user_id == Auth::user()->id) {
                $reviews->sender_id = $reservationDetails->user_id;
                $reviews->receiver_id = $reservationDetails->host_id;
                $reviews->reviewer = 'guest';
            } elseif ($reservationDetails->host_id == Auth::user()->id) {
                $reviews->sender_id = $reservationDetails->host_id;
                $reviews->receiver_id = $reservationDetails->user_id;
                $reviews->reviewer = 'host';
            }

            foreach ($data->all() as $key => $value) {
                if ($key != 'section' && $key != 'review_id') {
                    $reviews->$key = $value;
                }
            }


            $reviews->save();

            return json_encode(['success'=>true, 'review_id'=>$reviews->id]);
        }
    }


    public function reviewDetails(Request $request)
    {
        $review_id = $request->id;
        $data['reviewDetails'] = Reviews::where('id', '=', $review_id)->where(function($query){
            return $query->where('sender_id', Auth::id())->orWhere('receiver_id', Auth::id());
            return $query;
        })->firstOrFail();
        return view('users.reviews_details', $data)->render();
    }


    /**
     * Check duplicate phone number for new user
     *
     * @param Request $request
     *
     * @return status true/false
     *
     * @return message fail/success
     */

    public function duplicatePhoneNumberCheck(Request $request)
    {
        $req_id = $request->id;

        if (isset($req_id)) {
            $user = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone), 'carrier_code' => $request->carrier_code])->where(function ($query) use ($req_id)
            {
                $query->where('id', '!=', $req_id);
            })->first(['phone', 'carrier_code']);
        } else {
            $user = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone), 'carrier_code' => $request->carrier_code])->first(['phone', 'carrier_code']);
        }

        if (!empty($user->phone) && !empty($user->carrier_code)) {
            $data['status'] = true;
            $data['fail']   = "The phone number has already been taken!";
        } else {
            $data['status']  = false;
            $data['success'] = "The phone number is Available!";
        }
        return json_encode($data);
    }

    /**
     * Checking duplicate hone numebr for existing customer during manual booking
     *
     * @param string Request as $request
     *
     * @return status and message
     */

    public function duplicatePhoneNumberCheckForExistingCustomer(Request $request)
    {

        $req_id = isset($request->id) ? $request->id : $request->customer_id;

        if (isset($req_id)) {
            $user = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone), 'carrier_code' => $request->carrier_code])->where(function ($query) use ($req_id)
            {
                $query->where('id', '!=', $req_id);
            })->first(['phone', 'carrier_code']);
        } else {
            $user = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone), 'carrier_code' => $request->carrier_code])->first(['phone', 'carrier_code']);
        }

        if (!empty($user->phone) && !empty($user->carrier_code)) {
            $data['status'] = true;
            $data['fail']   = "The phone number has already been taken!";
        } else {
            $data['status']  = false;
            $data['success'] = "The phone number is Available!";
        }
        return json_encode($data);
    }
       /**
     * Add for user wallet info
     *
     * @param string Request as $request
     *
     * @return  user info
     */
       public function wallet($userId)
       {
           $defaultCurrencyId    = Settings::getAll()->where('name', 'default_currency')->first();
           $wallet               = new Wallet();
           $wallet->user_id      = $userId;
           $wallet->currency_id  = (int)$defaultCurrencyId->value;
           $wallet->save();

       }
   }

