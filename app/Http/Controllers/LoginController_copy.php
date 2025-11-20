<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EmailController;
use App\Http\Controllers\UserController;
use App\Http\Helpers\Common;
use Illuminate\Http\Request;

use App\Models\{PasswordResets, Settings, User, UserDetails, UsersVerification, TwoFactorToken};

use Auth;
use DateTime;
use Session;
use Socialite;
use Validator;
use DB;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LoginController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common();
    }

    public function index()
    {
        $data['social'] = Settings::getAll()->where('type','social')->pluck('value','name');
        return view('login.view', $data);
    }

    public function authenticate(Request $request)
    {
        $rules = array(
            'email'    => 'required|email|max:200',
            'password' => 'required',
        );

        $fieldNames = array(
            'email'    => 'Email',
            'password' => 'Password',
        );

        $remember = ($request->remember_me) ? true : false;

        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($fieldNames);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        } else {
            if ($this->n_as_k_c()) {
                Session::flush();
                return view('vendor.installer.errors.user');
            }

            $users = User::where('email', $request->email)->first();

            if (!empty($users)) {
                if ($users->status != 'Inactive') {
                    // Validate credentials without logging in
                    if (Auth::validate(['email' => $request->email, 'password' => $request->password])) {
                        // Start 2FA flow (do NOT log the user in yet)
                        $this->startTwoFactor($users, $remember, '/wizard/visuals-dashboard');
                        return redirect()->route('2fa.form');
                    } else {
                        $this->helper->one_time_message('error', trans('messages.error.login_info_error'));
                        return redirect('login');
                    }
                } elseif ($users->status == 'Inactive') {
                    $this->helper->one_time_message('error', "User is inactive. Please contact system administrator to activate it by emailing us on info@myrespiteaccom.com.au!");
                    return redirect('login');
                } else {
                    $this->helper->one_time_message('error', trans('messages.error.login_info_error'));
                    return redirect('login');
                }
            } else {
                $this->helper->one_time_message('error', trans('There isn’t an account associated with this email address.'));
                return redirect('login');
            }
        }
    }

    public function showTwoFactorForm()
    {
        if (!Session::has('2fa:user:id')) {
            $this->helper->one_time_message('error', 'Your session for verification has expired. Please login again.');
            return redirect('login');
        }
        return view('login.two_factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ], [
            'code.required' => 'Verification code is required.',
            'code.digits'   => 'Verification code must be 6 digits.',
        ]);

        $userId = Session::get('2fa:user:id');
        $remember = (bool) Session::get('2fa:remember', false);
        $intended = Session::get('2fa:intended', '/wizard/visuals-dashboard');

        if (!$userId) {
            $this->helper->one_time_message('error', 'Your session for verification has expired. Please login again.');
            return redirect('login');
        }

        $token = TwoFactorToken::where('user_id', $userId)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (!$token) {
            $this->helper->one_time_message('error', 'Verification code not found or already used. Please request a new code.');
            return redirect()->route('2fa.form');
        }

        // Check expiry
        if (Carbon::now()->greaterThan($token->expires_at)) {
            // Clean up old tokens
            TwoFactorToken::where('user_id', $userId)->delete();
            $this->helper->one_time_message('error', 'Your verification code has expired. We sent a new code to your email.');
            // Automatically resend after expiry
            $user = User::find($userId);
            if ($user) {
                $this->startTwoFactor($user, $remember, $intended);
            }
            return redirect()->route('2fa.form');
        }

        // Verify code
        if (!Hash::check($request->code, $token->code_hash)) {
            $this->helper->one_time_message('error', 'Invalid verification code. Please try again.');
            return redirect()->route('2fa.form')->withInput();
        }

        // Mark token as consumed and cleanup others
        $token->consumed_at = Carbon::now();
        $token->save();
        TwoFactorToken::where('user_id', $userId)->where('id', '<>', $token->id)->delete();

        // Finalize login
        Auth::loginUsingId($userId, $remember);

        // Clear session keys
        Session::forget('2fa:user:id');
        Session::forget('2fa:remember');
        Session::forget('2fa:intended');

        $this->helper->one_time_message('success', 'Successfully verified.');
        return redirect()->intended($intended);
    }

    public function resendTwoFactor(Request $request)
    {
        $userId = Session::get('2fa:user:id');
        $remember = (bool) Session::get('2fa:remember', false);
        $intended = Session::get('2fa:intended', '/wizard/visuals-dashboard');

        if (!$userId) {
            $this->helper->one_time_message('error', 'Your session for verification has expired. Please login again.');
            return redirect('login');
        }

        $user = User::find($userId);
        if (!$user) {
            $this->helper->one_time_message('error', 'User not found. Please login again.');
            return redirect('login');
        }

        // Delete old tokens and send a fresh one
        TwoFactorToken::where('user_id', $userId)->delete();
        $this->startTwoFactor($user, $remember, $intended);

        $this->helper->one_time_message('success', 'A new verification code has been sent to your email.');
        return redirect()->route('2fa.form');
    }

    protected function startTwoFactor(User $user, bool $remember = false, string $intended = '/wizard/visuals-dashboard'): void
    {
        // Remove old tokens
        TwoFactorToken::where('user_id', $user->id)->delete();

        $code = random_int(100000, 999999);

        TwoFactorToken::create([
            'user_id'    => $user->id,
            'code_hash'  => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(10),
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
        ]);

        // Save session details for verification step
        Session::put('2fa:user:id', $user->id);
        Session::put('2fa:remember', $remember);
        // Use existing intended if set, else use provided fallback
        $sessionIntended = Session::pull('url.intended'); // Laravel sets this automatically for intended redirects
        Session::put('2fa:intended', $sessionIntended ?? $intended);

        // Send email with code
        try {
            app(EmailController::class)->two_factor_code($user, $code);
        } catch (\Throwable $e) {
            Log::error('2FA email failed: ' . $e->getMessage());
        }
    }

    public function check(Request $request)
    {
        if ($request->get('email')) {
            $email = $request->get('email');
            $data  = DB::table("users")
            ->where('email', $email)
            ->count();
            if ($data > 0) {
                echo 'not_unique';
            } else {
                echo 'unique';
            }
        }
    }

    public function signup(Request $request)
    {
        $data['social'] = Settings::getAll()->where('type','social')->pluck('value','name');
        return view('home.signup_login', $data);
    }

    public function forgotPassword(Request $request, EmailController $email_controller)
    {
        if (!$request->isMethod('post')) {
            return view('login.forgot_password');
        } else {
            $rules = array(
                'email' => 'required|email|exists:users,email|max:200',
            );

            $messages = array(
                'required' => ':attribute is required.',
                'exists'   => trans('messages.jquery_validation.email_not_existed'),
            );

            $fieldNames = array(
                'email' => 'Email',
            );

            $validator = Validator::make($request->all(), $rules, $messages);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $user = User::whereEmail($request->email)->first();
                $email_controller->forgot_password($user);

                $this->helper->one_time_message('success', trans('messages.success.reset_pass_send_success'));
                return redirect('login');
            }
        }
    }

    public function resetPassword(Request $request)
    {
        if (! $request->isMethod('post')) {
            $password_resets = PasswordResets::whereToken($request->secret);

            if ($password_resets->count()) {
                $password_result = $password_resets->first();

                $datetime1 = new DateTime();
                $datetime2 = new DateTime($password_result->created_at);
                $interval  = $datetime1->diff($datetime2);
                $hours     = $interval->format('%h');

                if ($hours >= 1) {
                    $password_resets->delete();

                    $this->helper->one_time_message('error', trans('messages.error.token_expire_error'));
                    return redirect('login');
                }

                $data['result'] = User::whereEmail($password_result->email)->first();
                $data['token']  = $request->secret;

                return view('login.reset_password', $data);
            } else {
                $this->helper->one_time_message('error', trans('Invalid Token'));
                return redirect('login');
            }
        } else {
            $rules = array(
                'password'              => 'required|min:6|max:30',
                'password_confirmation' => 'required|same:password',
            );

            $fieldNames = array(
                'password'              => 'New Password',
                'password_confirmation' => 'Confirm Password',
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $password_resets = PasswordResets::whereToken($request->token)->delete();

                $user           = User::find($request->id);
                $user->password = bcrypt($request->password);
                $user->save();

                $this->helper->one_time_message('success', trans('messages.success.pass_change_success'));
                return redirect('login');
            }
        }
    }

    public function facebookAuthenticate(EmailController $email_controller, UserController $user_controller)
    {
        if (!isset(request()->error)) {
            $userNode = Socialite::with('facebook')->user();

            $verificationUser = Session::get('verification');

            if ($verificationUser == 'yes') {
                return redirect('facebookConnect/' . $userNode->id);
            }

            $ex_name   = explode(' ', $userNode->name);
            $firstName = $ex_name[0] ?? '';
            $lastName  = $ex_name[1] ?? '';

            $email = $userNode->email;

            $user = User::where('email', $email);

            if ($user->count() > 0) {
                $user = User::where('email', $email)->first();

                UserDetails::updateOrCreate(
                    ['user_id' => $user->id, 'field' => 'fb_id'],
                    ['value' => $userNode->id]
                );

                $user_id = $user->id;
            } else {
                $user = User::where('email', $email);
                if ($user->count() > 0) {
                    $data['title'] = 'Disabled ';
                    return view('users.disabled', $data);
                }

                $user             = new User();
                $user->first_name = $firstName;
                $user->last_name  = $lastName;
                $user->email      = $email;
                $user->status     = 'Active';
                $user->save();

                $user_details          = new UserDetails();
                $user_details->user_id = $user->id;
                $user_details->field   = 'fb_id';
                $user_details->value   = $userNode->id;
                $user_details->save();

                $user_verification           = new UsersVerification();
                $user_verification->user_id  = $user->id;
                $user_verification->fb_id    = $userNode->id;
                $user_verification->facebook = 'yes';
                $user_verification->save();

                $user_id = $user->id;
                $user_controller->wallet($user->id);
                $email_controller->welcome_email($user);
            }

            $users = User::where('id', $user_id)->first();

            if ($users->status != 'Inactive') {
                // Start 2FA for social login (do not log in yet)
                $this->startTwoFactor($users, false, 'dashboard');
                return redirect()->route('2fa.form');
            } else {
                $data['title'] = 'Disabled ';
                return view('users.disabled', $data);
            }
        } else {
            return redirect('login');
        }
    }

    public function googleLogin()
    {
        return Socialite::with('google')->redirect();
    }

    public function facebookLogin()
    {
        return Socialite::with('facebook')->redirect();
    }

    public function googleAuthenticate(EmailController $email_controller, UserController $user_controller)
    {
        if (!isset(request()->error)) {
            $userNode = Socialite::with('google')->user();

            $verificationUser = Session::get('verification');
            if ($verificationUser == 'yes') {
                return redirect('googleConnect/' . $userNode->id);
            }

            $ex_name   = explode(' ', $userNode->name ?? '');
            $firstName = $ex_name[0] ?? '';
            $lastName  = $ex_name[1] ?? '';

            $email = ($userNode->email == '') ? $userNode->id . '@gmail.com' : $userNode->email;

            $user = User::where('email', $email);

            if ($user->count() > 0) {
                $user = User::where('email', $email)->first();

                UserDetails::updateOrCreate(
                    ['user_id' => $user->id, 'field' => 'fb_id'],
                    ['value' => $userNode->id]
                );

                $user_id = $user->id;
            } else {
                $user = User::where('email', $email);
                if ($user->count() > 0) {
                    $data['title'] = 'Disabled ';
                    return view('users.disabled', $data);
                }

                $user             = new User();
                $user->first_name = $firstName;
                $user->last_name  = $lastName;
                $user->email      = $email;
                $user->status     = 'Active';
                $user->save();

                $user_details          = new UserDetails();
                $user_details->user_id = $user->id;
                $user_details->field   = 'google_id';
                $user_details->value   = $userNode->id;
                $user_details->save();

                $user_id = $user->id;

                $user_verification            = new UsersVerification();
                $user_verification->user_id   = $user->id;
                $user_verification->google_id = $userNode->id;
                $user_verification->google    = 'yes';
                $user_verification->save();

                $user_controller->wallet($user->id);
                $email_controller->welcome_email($user);
            }

            $users = User::where('id', $user_id)->first();

            if ($users->status != 'Inactive') {
                // Start 2FA for social login (do not log in yet)
                $this->startTwoFactor($users, false, 'dashboard');
                return redirect()->route('2fa.form');
            } else {
                $data['title'] = 'Disabled ';
                return view('users.disabled', $data);
            }
        } else {
            return redirect('login');
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