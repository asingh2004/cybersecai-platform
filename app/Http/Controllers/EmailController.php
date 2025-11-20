<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Http\Requests;

use Illuminate\Support\Facades\Log;

use App\Models\{
    Accounts,
    Admin,
    Bookings,
    Currency,
    EmailTemplate,
    PasswordResets,
    PaymentMethods,
    Payouts,
    Rooms,
    Settings,
    User
};
use Session;
use Auth;
use Config;
use DB;
use DateTime;
use DateTimeZone;

class EmailController extends Controller
{
    private $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function welcome_email($user)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $token                       = $this->helper->randomCode(100);
        $password_resets             = new PasswordResets;
        $password_resets->email      = $user->email;
        $password_resets->token      = $token;
        $password_resets->created_at = date('Y-m-d H:i:s');
        $password_resets->save();

        $data['first_name'] = $user->first_name;
        $data['email']      = $user->email;
        $data['token']      = $token;
        $data['type']       = 'register';
        $data['url']        = url('/').'/';
        $data['view']       = resource_path('views/sendmail/email_confirm.blade.php');
        $data['link']       = $data['url'].'users/confirm_email?code='.$data['token'];
        $data['link_text']  = trans('messages.email_template.confirm_email');
        $data['user_name']  = '';

        $englishTemplate = EmailTemplate::where(['temp_id' => 5, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();

        $emailTemplatefromDB = EmailTemplate::where([['temp_id',5],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subject_string = $emailTemplatefromDB->subject;
            $body_string    = $emailTemplatefromDB->body;
        } else {
            $subject_string = $englishTemplate->subject;
            $body_string    = $englishTemplate->body;
        }

        $body_string = str_replace('{first_name}', $user->first_name, $body_string);
        $body_string = str_replace('{site_name}', SITE_NAME, $body_string);

        $data['subject']      = $subject_string;
        $data['content']      = $body_string;
        $data['message_body'] = $body_string;
      
      	$settingsArr = Settings::getAll()->pluck('value', 'name')->toArray();

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.email_confirm_template', $data, function($message) use ($data, $subject_string) {
                    $message->to($data['email'], $data['first_name'])->subject($subject_string);
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }
  
  	// This function is called when a user is directly added by the App Admin
  	public function sendAdminAddUserWelcomeEmail(User $user)
    {
        // build a password change (reset) token
        $token = app('auth.password.broker')->createToken($user);

        $url = url('users/reset_password/' . $token); // or route('password.reset', $token) if you have route

        // Optional: you can create a password reset record (for custom reset blade)
        // Or just use Laravel's built-in password reset links
        $toEmail = $user->email;
        $toName  = trim($user->first_name . ' ' . $user->last_name);
      
      	$settingsArr = Settings::getAll()->pluck('value', 'name')->toArray();

        \Mail::send('emails.admin_add_user_welcome', ['user' => $user, 'url' => $url], function ($message) use ($toEmail, $toName) {
            $message->to($toEmail, $toName)
                ->subject('Your account has been created – set your password to get started');
        });
    }
  
    public function saas_admin_new_user($user)
    {
        $adminDetails = \App\Models\Admin::where('status','active')->first();
        if (!$adminDetails) {
            \Log::error('No active admin found for SaaS approval email!');
            return;
        }

        $toEmail   = $adminDetails->email;
        $toName    = $adminDetails->username;

        $subject = 'New User Registration Approval Needed';

        //\Log::info("[SAAS ADMIN MAIL] About to mail admin: $toEmail name: $toName for user {$user->email}");

        $settingsArr = \App\Models\Settings::getAll()->pluck('value', 'name')->toArray();

        try {
            // The variables below will be available in the blade:
            \Mail::send(
                'sendmail.saas_admin_notify', // <- blade path (no .php, no resource_path)
                [
                    'name'  => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    // Add any other vars for the blade here if desired
                ],
                function($message) use ($toEmail, $toName, $subject) {
                    $message->to($toEmail, $toName)
                            ->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            \Log::error('Failed sending SaaS admin approval email: ' . $e->getMessage());
        }

      ///\Log::info("[SAAS ADMIN MAIL] Sent to $toEmail.");
    }

    // 2FA email
    public function two_factor_code(\App\Models\User $user, string $code): void
    {
        try {
            \Mail::send('emails.two_factor_code', ['user' => $user, 'code' => $code], function ($message) use ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $message->to($user->email, $name)->subject('Your verification code');
            });
        } catch (\Throwable $e) {
            \Log::error('Failed sending 2FA email: ' . $e->getMessage());
        }
    }

  	
  
    // Forgot password (ensured working)
    public function forgot_password($user)
    {
        \Log::info("[ResetPW] Called for user: " . ($user ? $user->email : 'USER IS NULL'));

        if (!$user || !$user->email) {
            \Log::error("[ResetPW] ABORT: \$user is empty or missing email.");
            return false;
        }

        // Generate a unique token
        $token = $this->helper->randomCode(100);
        $loopGuard = 0;
        while (PasswordResets::where('token', $token)->exists()) {
            $token = $this->helper->randomCode(100);
            $loopGuard++;
            if ($loopGuard > 10) {
                \Log::error("[ResetPW] Token generation loop exceeded safety limit!");
                return false;
            }
        }

        // Save to password_resets
        $password_resets             = new PasswordResets;
        $password_resets->email      = $user->email;
        $password_resets->token      = $token;
        $password_resets->created_at = date('Y-m-d H:i:s');
        if (!$password_resets->save()) {
            \Log::error("[ResetPW] Failed to save PasswordResets entry for {$user->email}");
            return false;
        }

        // Dynamically fetch settings
        $settingsArr = Settings::getAll()->pluck('value', 'name')->toArray();
        $resetUrl = rtrim($settingsArr['head_code'] ?? 'https://mochanai.com/', '/') . '/forgot_password?email=' . urlencode($user->email);
        $brand = $settingsArr['name'] ?? 'mochanai platform';

        // Compose email
        if (env('APP_MODE', '') != 'test') {
            try {
                \Mail::send('sendmail.forgot_password', [
                    'settingsArr' => $settingsArr,
                    'name'        => $user->first_name,
                    'email'       => $user->email,
                    'resetUrl'    => $resetUrl,
                    'token'       => $token, // <--- ADD THIS LINE!
                ], function($message) use ($user, $brand) {
                    $message->to($user->email, $user->first_name)
                        ->subject('Set/Reset Your Password | ' . $brand);
                
                });
                \Log::info("[ResetPW] Email send attempt completed for {$user->email}");
            } catch (\Throwable $ex) {
                \Log::error("[ResetPW] EXCEPTION sending email: ".$ex->getMessage());
            }
        } else {
            \Log::info("[ResetPW] In test mode, not sending email.");
        }

        return true;
    }

    public function change_email_confirmation($user)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;
        $token                        = $this->helper->randomCode(100);

        $password_resets = new PasswordResets;
        $password_resets->email      = $user->email;
        $password_resets->token      = $token;
        $password_resets->created_at = date('Y-m-d H:i:s');
        $password_resets->save();

        $data['first_name']  = $user->first_name;
        $data['email']       = $user->email;
        $data['token']       = $token;
        $data['type']        = 'change';
        $data['url']         = url('/').'/';

        $data['subject']     = "Please confirm your e-mail address";
        $data['view']        = resource_path('views/sendmail/email_confirm.blade.php');
        $data['link']        = $data['url'].'users/confirm_email?code='.$data['token'];
        $data['link_text']   = trans('messages.email_template.confirm_email');

        $englishTemplate = EmailTemplate::where(['temp_id' => 5, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',5],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subject_string = $emailTemplatefromDB->subject;
            $body_string    = $emailTemplatefromDB->body;
        } else {
            $subject_string = $englishTemplate->subject;
            $body_string    = $englishTemplate->body;
        }

        $body_string = str_replace('{first_name}', $user->first_name, $body_string);
        $body_string = str_replace('{site_name}', SITE_NAME, $body_string);

        $data['subject']      = $subject_string;
        $data['content']      = $body_string;
        $data['message_body'] = $body_string;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.email_confirm', $data, function($message) use ($user) {
                    $message->to($user->email, $user->first_name)->subject('Please confirm your e-mail address');
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function new_email_confirmation($user)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;
        $token                        = $this->helper->randomCode(100);

        $password_resets = new PasswordResets;
        $password_resets->email      = $user->email;
        $password_resets->token      = $token;
        $password_resets->created_at = date('Y-m-d H:i:s');
        $password_resets->save();

        $data['first_name']   = $user->first_name;
        $data['email']        = $user->email;
        $data['token']        = $token;
        $data['type']         = 'new_confirm';
        $data['url']          = url('/').'/';

        $data['subject']      = "Please confirm your e-mail address";
        $data['view']         = resource_path('views/sendmail/email_confirm.blade.php');
        $data['link']         = $data['url'].'users/confirm_email?code='.$data['token'];
        $data['link_text']    = trans('messages.email_template.confirm_email');

        $englishTemplate = EmailTemplate::where(['temp_id' => 5, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',5],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subject_string = $emailTemplatefromDB->subject;
            $body_string    = $emailTemplatefromDB->body;
        } else {
            $subject_string = $englishTemplate->subject;
            $body_string    = $englishTemplate->body;
        }

        $body_string = str_replace('{first_name}', $user->first_name, $body_string);
        $body_string = str_replace('{site_name}', SITE_NAME, $body_string);

        $data['subject']      = $subject_string;
        $data['content']      = $body_string;
        $data['message_body'] = $body_string;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.email_confirm', $data, function($message) use ($user) {
                    $message->to($user->email, $user->first_name)->subject('Please confirm your e-mail address');
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }

        return true;
    }

    public function account_preferences($account_id, $type = 'update', $updateTime )
    {
        $emailSettings                = Settings::getAll()->where('type', 'email')->toArray();
        $emailConfig                  = $this->helper->key_value('name', 'value', $emailSettings);
        $adminDetails                 = Admin::where('status', 'active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        if ($type != 'delete') {
            $result               = Accounts::find($account_id);
            $user                 = $result->users;
            $data['first_name']   = $user->first_name;
            $data['email']        = $user->email;
            $data['updated_time'] = $result->updated_at_time;
            $data['updated_date'] = $result->updated_at_date;
        } else {
            $user                 = Auth::user();
            $data['first_name']   = $user->first_name;
            $data['email']        = $user->email;
            $now_dt               = new DateTime(date('Y-m-d H:i:s'));
            $data['deleted_time'] = $now_dt->format('d M').' at '.$now_dt->format('H:i');
        }

        $data['type']      = $type;
        $data['url']       = url('/').'/';
        $data['view']      = resource_path('views/sendmail/account_preferences.blade.php');
        $data['link']      = $data['url'].'users/account-preferences';

        if ($type == 'update') {
            $englishTemplate = EmailTemplate::where(['temp_id' => 2, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
            $emailTemplatefromDB = EmailTemplate::where([['temp_id',2],['lang', session()->get('language')],['type','email']])->first();
            if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
                $subjectFromDB = $emailTemplatefromDB->subject;
                $bodyFromDB    = $emailTemplatefromDB->body;
            } else {
                $subjectFromDB = $englishTemplate->subject;
                $bodyFromDB    = $englishTemplate->body;
            }

            $subjectFromDB  = str_replace('{site_name}', SITE_NAME, $subjectFromDB);
            $bodyFromDB     = str_replace('{first_name}', $user->first_name, $bodyFromDB);
            $bodyFromDB     = str_replace('{site_name}', SITE_NAME, $bodyFromDB);
            $bodyFromDB     = str_replace('{date_time}', $updateTime, $bodyFromDB);

            $data['subject']      = $subjectFromDB;
            $data['content']      = $bodyFromDB;
            $data['message_body'] = $bodyFromDB;

        } elseif ($type == 'delete') {
            $englishTemplate = EmailTemplate::where(['temp_id' => 3, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
            $emailTemplatefromDB = EmailTemplate::where([['temp_id',3],['lang', session()->get('language')],['type','email']])->first();
            if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
                $subjectFromDB = $emailTemplatefromDB->subject;
                $bodyFromDB    = $emailTemplatefromDB->body;
            } else {
                $subjectFromDB = $englishTemplate->subject;
                $bodyFromDB    = $englishTemplate->body;
            }

            $subjectFromDB  = str_replace('{site_name}', SITE_NAME, $subjectFromDB);
            $bodyFromDB     = str_replace('{first_name}', $user->first_name, $bodyFromDB);
            $bodyFromDB     = str_replace('{site_name}', SITE_NAME, $bodyFromDB);
            $bodyFromDB     = str_replace('{date_time}', $updateTime, $bodyFromDB);

            $data['subject']      = $subjectFromDB;
            $data['content']      = $bodyFromDB;
            $data['message_body'] = $bodyFromDB;

        } elseif ($type == 'default_update') {
            $englishTemplate = EmailTemplate::where(['temp_id' => 1, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
            $emailTemplatefromDB = EmailTemplate::where([['temp_id',1],['lang', session()->get('language')],['type','email']])->first();
            if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
                $subjectFromDB = $emailTemplatefromDB->subject;
                $bodyFromDB    = $emailTemplatefromDB->body;
            } else {
                $subjectFromDB = $englishTemplate->subject;
                $bodyFromDB    = $englishTemplate->body;
            }

            $subjectFromDB  = str_replace('{site_name}', SITE_NAME, $subjectFromDB);
            $bodyFromDB     = str_replace('{site_name}', SITE_NAME, $bodyFromDB);
            $bodyFromDB     = str_replace('{first_name}', $user->first_name, $bodyFromDB);
            $bodyFromDB     = str_replace('{date_time}', $updateTime, $bodyFromDB);

            $data['subject']      = $subjectFromDB;
            $data['content']      = $bodyFromDB;
            $data['message_body'] = $bodyFromDB;
        }

        $data['user_name'] = '';

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.account_preferences_template', $data, function ($message) use ($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } elseif (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function booking($booking_id, $checkinDate, $bank = false)
    {
        $emailSettings                = Settings::getAll()->where('type', 'email')->toArray();
        $emailConfig                  = $this->helper->key_value('name', 'value', $emailSettings);
        $adminDetails                 = Admin::where('status', 'active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $booking         = Bookings::find($booking_id);
        $user            = $booking->host;
        $data['url']     = url('/').'/';
        $data['result']  = Bookings::where('bookings.id', $booking_id)->with(['users', 'properties', 'host', 'currency', 'messages'])->first()->toArray();
        $data['logo']    = LOGO_URL;

        if ($booking->status == 'Pending') {
            $data['view']       = resource_path('views/sendmail/booking.blade.php');
            $data['link']       = $data['url'].'booking/'.$data['result']['id'];
            $data['link_text']  = trans('messages.email_template.accept/decline');
            $temp_template_id   = 4;
        } else {
            $data['view']       = resource_path('views/sendmail/instant_booking.blade.php');
            $temp_template_id   = 12;
        }

        $data['user_name']   = $data['result']['users']['first_name'];
        $data['first_name']  = $data['result']['host']['first_name'];
        $data['email']       = $user->email;
        $total_night         = $data['result']['total_night']>1?"nights":"night";
        $data["total_night"] = $data['result']['total_night'].' '.$total_night;

        $guest               = $data['result']['guest']>1?"guests":"guest";
        $data["total_guest"] = $data['result']['guest'].' '.$guest;

        $englishTemplate = EmailTemplate::where(['temp_id' => $temp_template_id, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',$temp_template_id],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }

        if ($temp_template_id == 4) {
            $bodyFromDB = str_replace('{user_email}', $data['result']['users']['email'], $bodyFromDB);
            $bodyFromDB = str_replace('{user_contact_number}', $data['result']['users']['formatted_phone'], $bodyFromDB);
        }

        if ($temp_template_id == 12) {
            $bodyFromDB = str_replace('{user_email}', $user->email, $bodyFromDB);
            $bodyFromDB = str_replace('{user_contact_number}', $data['result']['users']['formatted_phone'], $bodyFromDB);
        }

        $subjectFromDB  = str_replace('{user_first_name}', $data['result']['users']['first_name'], $subjectFromDB);
        $subjectFromDB  = str_replace('{property_name}', $data['result']['properties']['name'], $subjectFromDB);
        $bodyFromDB     = str_replace('{owner_first_name}', $user->first_name, $bodyFromDB);
        $bodyFromDB     = str_replace('{user_first_name}', $data['result']['users']['first_name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_night}', $data['result']['total_night'], $bodyFromDB);
        if ($data['result']['total_night']>1) {
            $myStr = 'nights';
        }
        if ($data['result']['total_night']=1) { // keeping original behavior
            $myStr = 'night';
        }
        $bodyFromDB     = str_replace('{night/nights}', $myStr, $bodyFromDB);
        $bodyFromDB     = str_replace('{property_name}', $data['result']['properties']['name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{messages_message}', $data['result']['messages'][0]['message'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_guest}', $data['result']['guest'], $bodyFromDB);
        $bodyFromDB     = str_replace('{start_date}', $checkinDate, $bodyFromDB);
        $bodyFromDB     = str_replace('{payment_method}', PaymentMethods::find($data['result']['payment_method_id'])->name ?? 'Not selected yet.', $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.booking_cancel_template', $data, function ($message) use ($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } elseif (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function booking_user($booking_id, $checkinDate)
    {
        $emailSettings                = Settings::getAll()->where('type', 'email')->toArray();
        $emailConfig                  = $this->helper->key_value('name', 'value', $emailSettings);
        $adminDetails                 = Admin::where('status', 'active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $booking         = Bookings::find($booking_id);
        $user            = $booking->users;
        $data['url']     = url('/').'/';
        $data['result']  = Bookings::where('bookings.id', $booking_id)->with(['users', 'properties', 'host', 'currency', 'messages'])->first()->toArray();
        $data['url']     = url('/').'/';
        $data['logo']    = LOGO_URL;

        if ($booking->status == 'Pending') {
            $data['view']       = resource_path('views/sendmail/booking.blade.php');
            $temp_template_id   = 11;
        } else {
            $data['view']       = resource_path('views/sendmail/instant_booking.blade.php');
            $temp_template_id   = 13;
        }

        $data['user_name']  = $data['result']['host']['first_name'];
        $data['first_name'] = $data['result']['users']['first_name'];
        $data['email']      = $user->email;
        $total_night        = $data['result']['total_night']>1?"nights":"night";
        $data["total_night"]= $data['result']['total_night'].' '.$total_night;

        $guest              = $data['result']['guest']>1?"guests":"guest";
        $data["total_guest"]= $data['result']['guest'].' '.$guest;

        $englishTemplate = EmailTemplate::where(['temp_id' => $temp_template_id, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',$temp_template_id],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }
        $subjectFromDB  = str_replace('{property_name}', $data['result']['properties']['name'], $subjectFromDB);
        $bodyFromDB     = str_replace('{owner_first_name}', $booking->host->first_name, $bodyFromDB);
        $bodyFromDB     = str_replace('{user_first_name}', $data['result']['users']['first_name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_night}', $data['result']['total_night'], $bodyFromDB);
        if ($data['result']['total_night']>1) {
            $myStr = 'nights';
        }
        if ($data['result']['total_night']=1) { // keeping original behavior
            $myStr = 'night';
        }
        $bodyFromDB     = str_replace('{night/nights}', $myStr, $bodyFromDB);
        $bodyFromDB     = str_replace('{property_name}', $data['result']['properties']['name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{messages_message}', $data['result']['messages'][0]['message'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_guest}', $data['result']['guest'], $bodyFromDB);
        $bodyFromDB     = str_replace('{start_date}', $checkinDate, $bodyFromDB);
        $bodyFromDB     = str_replace('{payment_method}', PaymentMethods::find($data['result']['payment_method_id'])->name ?? 'Not selected yet.', $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;
        $data['temp_template_id'] = $temp_template_id;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.booking_user_template', $data, function ($message) use ($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } elseif (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function need_pay_account($booking_id, $type)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $result       = Bookings::find($booking_id);
        $data['type'] = $type;

        if($type == 'guest') {
            $user                  = $result->users;
            $data['email']         = $user->email;
            $data['payout_amount'] = $result->original_admin_guest_payment;
        }
        else {
            $user                  = $result->host;
            $data['email']         = $user->email;
            $data['payout_amount'] = $result->original_admin_host_payment;
        }

        $data['currency_symbol'] = $result->currency->org_symbol;
        $data['first_name']      = $user->first_name;
        $data['user_id']         = $user->id;
        $data['url']             = url('/').'/';
        $data['link']            = $data['url'].'users/account_preferences';
        $data['link_text']       = trans('messages.email_template.add_payment_method');
        $data['view']            = resource_path('views/sendmail/need_pay_account.blade.php');

        $englishTemplate = EmailTemplate::where(['temp_id' => 7, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',7],['lang_id', $this->getDefaultLanguage()],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }
        $bodyFromDB     = str_replace('{first_name}', $data['first_name'],$bodyFromDB);
        $bodyFromDB     = str_replace('{site_name}', SITE_NAME,$bodyFromDB);
        $bodyFromDB     = str_replace('{currency_symbol}', $data['currency_symbol'], $bodyFromDB);
        $bodyFromDB     = str_replace('{payout_amount}', $data['payout_amount'], $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.need_pay_account_template', $data, function($message) use($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function bookingCancellation($booking_id)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $booking         = Bookings::find($booking_id);
        $user            = $booking->host;
        $data['url']     = url('/').'/';
        $data['result']  = Bookings::where('bookings.id', $booking_id)->with(['users', 'properties', 'host', 'currency', 'messages'])->first()->toArray();
        $data['url']     = url('/').'/';
        $data['view']    = resource_path('views/sendmail/booking_cancel.blade.php');
        $data['link']    = $data['url'].'booking/'.$data['result']['id'];
        $data['user_name']  = $data['result']['users']['first_name'];
        $data['first_name'] = $data['result']['host']['first_name'];
        $data['email']      = $user->email;

        $englishTemplate = EmailTemplate::where(['temp_id' => 9, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',9],['lang_id', $this->getDefaultLanguage()],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }

        $subjectFromDB  = str_replace('{property_name}', $data['result']['properties']['name'],$subjectFromDB);
        $bodyFromDB     = str_replace('{owner_first_name}', $user->first_name,$bodyFromDB);
        $bodyFromDB     = str_replace('{user_first_name}', $data['user_name'],$bodyFromDB);
        $bodyFromDB     = str_replace('{property_name}',$data['result']['properties']['name'],$bodyFromDB);
        $bodyFromDB     = str_replace('{link}', $data['link'], $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.booking_cancel_template', $data, function($message) use($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function bookingAcceptedOrDeclined($booking_id, $status)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $booking         = Bookings::find($booking_id);
        $user            = $booking->users;
        $data['url']     = url('/').'/';
        $data['result']  = Bookings::where('bookings.id', $booking_id)->with(['users', 'properties', 'host', 'currency', 'messages'])->first()->toArray();
        $data['url']     = url('/').'/';
        $data['view']    = resource_path('views/sendmail/booking_accept_decline.blade.php');

        $data['user_name']  = $data['result']['users']['first_name'];
        $data['first_name'] = $data['result']['host']['first_name'];
        $data['email']      = $user->email;

        $englishTemplate = EmailTemplate::where(['temp_id' => 10, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',10],['lang_id', $this->getDefaultLanguage()],['type','email']])->first();

        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }

        if ($status == 'Processing') {
            $subjectFromDB  = str_replace('{Accepted/Declined}', 'Accepted', $subjectFromDB);
            $bodyFromDB     = str_replace('{guest_first_name}', $user->first_name,$bodyFromDB);
            $bodyFromDB     = str_replace('{host_first_name}', $data['first_name'],$bodyFromDB);
            $bodyFromDB     = str_replace('{Accepted/Declined}', 'Accepted', $bodyFromDB);
            $bodyFromDB     = str_replace('{property_name}',$data['result']['properties']['name'],$bodyFromDB);

            $data['link']       = $data['url'].'booking_payment/'.$data['result']['id'];
            $data['link_text']  = 'Payment';
        } elseif ($status == 'Declined') {
            $subjectFromDB  = str_replace('{Accepted/Declined}', 'Declined', $subjectFromDB);
            $bodyFromDB     = str_replace('{guest_first_name}', $user->first_name,$bodyFromDB);
            $bodyFromDB     = str_replace('{host_first_name}', $data['first_name'],$bodyFromDB);
            $bodyFromDB     = str_replace('{Accepted/Declined}', 'Declined', $bodyFromDB);
            $bodyFromDB     = str_replace('{property_name}',$data['result']['properties']['name'],$bodyFromDB);
        }

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.booking_cancel_template', $data, function($message) use($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    public function payout_sent($booking_id)
    {
        $emailSettings                = Settings::getAll()->where('type','email')->toArray();
        $emailConfig                  = $this->helper->key_value('name','value',$emailSettings);
        $adminDetails                 = Admin::where('status','active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;

        $result       = Bookings::find($booking_id);

        if ($result->status=="Cancelled" || $result->status=="Declined" || $result->status=="Expired") {
            $user   = $result->users;
            $amount = $result->original_guest_payout;
        } else {
            $user   = $result->host;
            $amount = $result->original_host_payout;
        }
        $data['email']           = $user->email;

        $payout_payment_methods          = $result->payment_methods;
        $data['payout_payment_method']   = $payout_payment_methods->name;
        $data['payout_amount']           = $amount;
        $data['currency_symbol']         = $result->currency->org_symbol;
        $data['first_name']              = $user->first_name;
        $data['user_id']                 = $user->id;
        $data['url']                     = url('/').'/';

        $data['link']       = $data['url'].'users/transaction_history';
        $data['link_text']  = trans('messages.email_template.transaction_history');
        $data['view']       = resource_path('views/sendmail/payout_sent.blade.php');

        $englishTemplate = EmailTemplate::where(['temp_id' => 8, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();

        $emailTemplatefromDB = EmailTemplate::where([['temp_id',8],['lang_id', $this->getDefaultLanguage()],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;

        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }
        $bodyFromDB     = str_replace('{site_name}', SITE_NAME,$bodyFromDB);
        $bodyFromDB     = str_replace('{first_name}', $data['first_name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{currency_symbol}', $data['currency_symbol'], $bodyFromDB);
        $bodyFromDB     = str_replace('{payout_amount}', $data['payout_amount'], $bodyFromDB);
        $bodyFromDB     = str_replace('{payout_payment_method}', $data['payout_payment_method'], $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.payout_sent_template', $data, function($message) use($user, $data) {
                    $message->to($user->email, $user->first_name)->subject($data['subject']);
                });
            } else if (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }

    // Unified PHPMailer sender (supports sendmail or SMTP)
    public function sendPhpEmail($data, $configEmail = null)
    {
        \Log::info('[sendPhpEmail] Entered sendPhpEmail for ' . ($data['email'] ?? 'NO EMAIL'));

        $mail = new PHPMailer(true);

        try {
            // Decide transport
            $driver = $configEmail['driver'] ?? config('mail.mailer'); // 'sendmail' or 'smtp' typically

            if ($driver === 'sendmail') {
                $mail->isSendmail();
                $fromEmail = $configEmail['from_address'] ?? config('mail.from.address');
                $fromName  = $configEmail['from_name'] ?? config('mail.from.name');
            } else {
                // SMTP
                $mail->isSMTP();
                $mail->Host       = config('mail.host');
                $mail->SMTPAuth   = true;
                $mail->Username   = config('mail.username');
                $mail->Password   = config('mail.password');

                $encryption = config('mail.encryption'); // 'tls' | 'ssl' | null
                if ($encryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = ''; // no encryption
                }

                $mail->Port       = config('mail.port');
                $fromEmail        = config('mail.from.address');
                $fromName         = config('mail.from.name');
                \Log::info('[sendPhpEmail] SMTP '.$mail->Host.':'.$mail->Port.' enc='.($encryption ?? 'none'));
            }

            // From / To
            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->addAddress($data['email'], $data['first_name'] ?? '');

            $mail->Subject = $data['subject'] ?? '(no subject)';

            // Template file
            if (!isset($data['view']) || !file_exists($data['view'])) {
                \Log::error('[sendPhpEmail] Template view file missing: ' . ($data['view'] ?? 'NULL'));
                throw new \Exception('Missing template file!');
            }
            $message = file_get_contents($data['view']);

            // Replace placeholders
            $message = str_replace('#message_body#', $data['message_body'] ?? '', $message);
            $message = str_replace('#site_name#', $fromName, $message);
            $message = str_replace('{first_name}', $data['first_name'] ?? '', $message);
            $message = str_replace('#lang#', $data['link_text'] ?? '', $message);
            $message = str_replace('#link#', $data['link'] ?? '', $message);

            // Body
            $mail->msgHTML($message);
            $mail->AltBody = 'This is a plain-text message body';

            // You can enable for debugging if needed
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            $ok = $mail->send();
            if ($ok) {
                \Log::info('[sendPhpEmail] Message sent to ' . $data['email']);
            } else {
                \Log::error('[sendPhpEmail] Mailer Error: ' . $mail->ErrorInfo);
            }
        } catch (\Throwable $ex) {
            \Log::error('[sendPhpEmail] EXCEPTION: ' . $ex->getMessage());
        }
    }

    public function getDefaultLanguage()
    {
        return Settings::getAll()->where('type', 'general')->where('name', 'default_language')
            ->first()
            ->value;
    }

    public function bankAdminNotify($bookingId, $checkInDate) {
        $emailSettings                = Settings::getAll()->where('type', 'email')->toArray();
        $receiver                     = Settings::getAll()->where('type','general')->whereIn('name',['email','name'])->pluck('value','name');
        $emailConfig                  = $this->helper->key_value('name', 'value', $emailSettings);
        $adminDetails                 = Admin::where('status', 'active')->first();
        $emailConfig['email_address'] = $adminDetails->email;
        $emailConfig['username']      = $adminDetails->username;
        $data['url']                  = url('/').'/';
        $data['result']               = Bookings::where('bookings.id', $bookingId)->with(['users', 'properties', 'host', 'currency', 'messages'])->first()->toArray();
        $data['url']                  = url('/').'/';
        $data['logo']                 = LOGO_URL;

        $data['view']       = resource_path('views/sendmail/booking.blade.php');
        $data['link']       = $data['url'].'admin/bookings/detail/'.$data['result']['id'];
        $data['link_text']  = trans('messages.email_template.accept/decline');
        $data['user_name']  = $data['result']['users']['first_name'];
        $data['first_name'] = $receiver['name'];
        $data['email']      = $receiver['email'];
        $total_night        = $data['result']['total_night']>1?"nights":"night";
        $data["total_night"]= $data['result']['total_night'].' '.$total_night;

        $guest              = $data['result']['guest']>1?"guests":"guest";
        $data["total_guest"]= $data['result']['guest'].' '.$guest;

        $englishTemplate = EmailTemplate::where(['temp_id' => 4, 'lang_id' => '1', 'type' => 'email'])->select('subject', 'body')->first();
        $emailTemplatefromDB = EmailTemplate::where([['temp_id',4],['lang', session()->get('language')],['type','email']])->first();
        if (!empty($emailTemplatefromDB->subject) && !empty($emailTemplatefromDB->body)) {
            $subjectFromDB = $emailTemplatefromDB->subject;
            $bodyFromDB    = $emailTemplatefromDB->body;
        } else {
            $subjectFromDB = $englishTemplate->subject;
            $bodyFromDB    = $englishTemplate->body;
        }
        $subjectFromDB  = str_replace('{property_name}', $data['result']['properties']['name'], $subjectFromDB);
        $bodyFromDB     = str_replace('{owner_first_name}', $receiver['name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{user_first_name}', $data['user_name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_night}', $data['result']['total_night'], $bodyFromDB);
        if ($data['result']['total_night']>1) {
            $myStr = 'nights';
        }
        if ($data['result']['total_night']=1) { // keeping original behavior
            $myStr = 'night';
        }
        $bodyFromDB     = str_replace('{night/nights}', $myStr, $bodyFromDB);
        $bodyFromDB     = str_replace('{property_name}', $data['result']['properties']['name'], $bodyFromDB);
        $bodyFromDB     = str_replace('{messages_message}', $data['result']['messages'][0]['message'], $bodyFromDB);
        $bodyFromDB     = str_replace('{total_guest}', $data['result']['guest'], $bodyFromDB);
        $bodyFromDB     = str_replace('{start_date}', $checkInDate, $bodyFromDB);
        $bodyFromDB     = str_replace('{payment_method}', PaymentMethods::find($data['result']['payment_method_id'])->name, $bodyFromDB);

        $data['subject']      = $subjectFromDB;
        $data['content']      = $bodyFromDB;
        $data['message_body'] = $bodyFromDB;

        if (env('APP_MODE', '') != 'test') {
            if (($emailConfig['driver'] ?? '') == 'smtp' && ($emailConfig['email_status'] ?? 0) == 1) {
                \Mail::send('emails.booking_cancel_template', $data, function ($message) use ($receiver, $data) {
                    $message->to($receiver['email'], $receiver['name'])->subject($data['subject']);
                });
            } elseif (($emailConfig['driver'] ?? '') == 'sendmail') {
                $this->sendPhpEmail($data, $emailConfig);
            }
        }
        return true;
    }
}