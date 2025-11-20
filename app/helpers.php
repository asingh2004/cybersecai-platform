<?php

use App\Models\Settings;
use App\Models\Change;
use App\Models\Currency;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\Common;
use App\Models\Messages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Twilio\Http\CurlClient;
use Illuminate\Support\Facades\Http;



/**
 * [dateFormat description for database date]
 * @param  [type] $value    [any number]
 * @return [type] [formates date according to preferences setting in Admin Panel]
 */
if(!function_exists('setDateForDb')) {
    function setDateForDb($value = null)
    {
        if (empty($value)) {
            return null;
        }
        $separator   = Settings::getAll()->firstWhere('name', 'date_separator')->value;
        $date_format = Settings::getAll()->firstWhere('name', 'date_format_type')->value;;
        if (str_replace($separator, '', $date_format) == "mmddyyyy") {
            $value = str_replace($separator, '/', $value);
            $date  = date('Y-m-d', strtotime($value));
        } else {
            $date = date('Y-m-d', strtotime(strtr($value, $separator, '-')));
        }
        return $date;
    }
}


/**
 * [Default timezones]
 * @return [timezonesArray]
 */
function phpDefaultTimeZones()
{
    $zonesArray  = array();
    $timestamp   = time();
    foreach (timezone_identifiers_list() as $key => $zone) {
        date_default_timezone_set($zone);
        $zonesArray[$key]['zone']          = $zone;
        $zonesArray[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
    }
    return $zonesArray;
}


/**
 * [dateFormat description]
 * @param  [type] $value    [any number]
 * @return [type] [formates date according to preferences setting in Admin Panel]
 */
function dateFormat($value, $type = null)
{
    $timezone       = '';
    $timezone       = Settings::getAll()->where('type', 'preferences')->where('name', 'dflt_timezone')->first()->value;
    $today          = new DateTime($value, new DateTimeZone(config('app.timezone')));
    $today->setTimezone(new DateTimeZone($timezone));
    $value          = $today->format('Y-m-d H:i:s');


    $preferenceData = Settings::getAll()->where('type','preferences')->whereIn('name', ['date_format_type', 'date_separator'])->toArray();
    $preferenceData = Common::key_value('name', 'value', $preferenceData);
    $preference     = $preferenceData['date_format_type'];
    $separator      = $preferenceData['date_separator'];

    $data           = str_replace(['/', '.', ' ', '-'], $separator, $preference);
    $data           = explode($separator, $data);
    $first          = $data[0];
    $second         = $data[1];
    $third          = $data[2];

    $dateInfo       = str_replace(['/', '.', ' ', '-'], $separator, $value);
    $datas          = explode($separator, $dateInfo);
    $year           = $datas[0];
    $month          = $datas[1];
    $day            = $datas[2];

    $dateObj        = DateTime::createFromFormat('!m', $month);
    $monthName      = $dateObj->format('F');

    $toHoursMin     = \Carbon\Carbon::createFromTimeStamp(strtotime($value))->format(' g:i A');

    if ($first == 'yyyy' && $second == 'mm' && $third == 'dd') {
        $value = $year . $separator . $month . $separator . $day. $toHoursMin;
    } else if ($first == 'dd' && $second == 'mm' && $third == 'yyyy') {
        $value = $day . $separator . $month . $separator . $year. $toHoursMin;
    } else if ($first == 'mm' && $second == 'dd' && $third == 'yyyy') {
        $value = $month . $separator . $day . $separator . $year. $toHoursMin;
    } else if ($first == 'dd' && $second == 'M' && $third == 'yyyy') {
        $value = $day . $separator . $monthName . $separator . $year. $toHoursMin;
    } else if ($first == 'yyyy' && $second == 'M' && $third == 'dd') {
        $value = $year . $separator . $monthName . $separator . $day. $toHoursMin;
    }
    return $value;

}


/**
* Process of sending twilio message - DEPRECATED NOWWWWWWWWWWWWWWWWWWWW
*
* @param string $request
*
* @return mixed
*/
function twilioSendSms_OLD_FUNCTION_DEPRECATED($toNumber,$messages)
{

    try {

        $client          = new CurlClient();
        $response        = $client->request('GET', 'https://api.twilio.com:8443');
        $phoneSms        = Settings::getAll()->where('type','twilio')->whereIn('name', ['twilio_sid', 'twilio_token','formatted_phone'])->pluck('value', 'name')->toArray();
        $sid             = !empty($phoneSms['twilio_sid']) ? $phoneSms['twilio_sid'] : 'ACf4fd1e';
        $token           = !empty($phoneSms['twilio_token']) ? $phoneSms['twilio_token'] : 'da9580307';

        ///////$url             = "https://api.twilio.com/2010-04-01/Accounts/$sid/SMS/Messages";
        $url             = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages";
        $trimmedMsg      = trim(preg_replace('/\s\s+/', ' ', $messages));

        if (!empty($phoneSms['formatted_phone'])) {
            $data = array (
                'From' => $phoneSms['formatted_phone'],
                'To' => $toNumber,
                'Body' => strip_tags($trimmedMsg),
            );
            $post = http_build_query($data);
            $x    = curl_init($url );
            curl_setopt($x, CURLOPT_POST, true);
            curl_setopt($x, CURLOPT_RETURNTRANSFER, true);
            if ($response->getStatusCode() <= 200 || $response->getStatusCode() >= 300) {
                curl_setopt($x, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($x, CURLOPT_SSL_VERIFYPEER, false);
            }
            curl_setopt($x, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($x, CURLOPT_USERPWD, "$sid:$token");
            curl_setopt($x, CURLOPT_POSTFIELDS, $post);
            $y = curl_exec($x);
            curl_close($x);
        }
        return redirect()->back();

    } catch (Exception $e) {

        return redirect()->back();
    }

}


/**
* Process of sending twilio message 
*
* @param string $request
*
* @return mixed
*/

function twilioSendSms($toNumber, $messages)
{
    try {
        $phoneSms = Settings::getAll()
            ->where('type', 'twilio')
            ->whereIn('name', ['twilio_sid', 'twilio_token', 'formatted_phone'])
            ->pluck('value', 'name')
            ->toArray();

        $sid = !empty($phoneSms['twilio_sid']) ? $phoneSms['twilio_sid'] : 'ACf4fd1e';
        $token = !empty($phoneSms['twilio_token']) ? $phoneSms['twilio_token'] : 'da9580307';
        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages";

        $trimmedMsg = trim(preg_replace('/\s\s+/', ' ', $messages));

        if (!empty($phoneSms['formatted_phone'])) {
            $response = Http::withBasicAuth($sid, $token)
                ->post($url, [
                    'From' => $phoneSms['formatted_phone'],
                    'To' => $toNumber,
                    'Body' => strip_tags($trimmedMsg),
                ]);

            if ($response->successful()) {
                return redirect()->back();
            }
        }

        return redirect()->back();
    } catch (Exception $e) {
        return redirect()->back();
    }
}


/**
 * [onlyFormat description]
 * @param  [type] $value    [any number]
 * @return [type] [formates date according to preferences setting in Admin Panel]
 */
function onlyFormat($value)
{
    $preferenceData = Settings::getAll()->whereIn('name', ['date_format_type', 'date_separator'])->where('type','preferences')
        ->map(function($d) {
            return [
                'name'=>$d->name,
                'value'=>$d->value
            ];
        })->toArray();
    $preferenceData = Common::key_value('name', 'value', $preferenceData);
    $separator      = $preferenceData['date_separator'];
    $preference     = str_replace(['/', '.', ' ', '-'], '', $preferenceData['date_format_type']);
    switch ($preference) {
        case 'yyyymmdd':
            $value = date('Y'. $separator . 'm' . $separator . 'd', strtotime($value));
            break;
        case 'ddmmyyyy':
            $value = date('d' . $separator .'m' . $separator . 'Y', strtotime($value));
            break;
        case 'mmddyyyy':
            $value = date('m' . $separator . 'd' . $separator . 'Y', strtotime($value));
            break;
        case 'ddMyyyy':
            $value = date('d' . $separator .'M' . $separator . 'Y', strtotime($value));
            break;
        case 'yyyyMdd':
            $value = date('Y' . $separator . 'M' . $separator . 'd', strtotime($value));
            break;
        default:
            $value = date('Y-m-d', strtotime($value));
            break;
    }
    return $value;

}




/**
 * [roundFormat description]
 * @param  [type] $value     [any number]
 * @return [type] [placement of money symbol according to preferences setting in Admin Panel]
 */
function moneyFormat($symbol, $value)
{
    $symbolPosition = currencySymbolPosition();
    if ($symbolPosition == "before") {
         $value = $symbol . ' ' . $value;
    } else {
        $value = $value . ' ' . $symbol;
    }
    return $value;
}

/**
 * [currencySymbolPosition description]
 * @return [position type of symbol after or before]
 */
function currencySymbolPosition()
{
    $position = Settings::getAll()->where('type','preferences')->where('name','money_format')->first()->value;
    return !empty($position) ? $position : 'after';
}


 function codeToSymbol($code)
{
    $symbol = Currency::getAll()->firstWhere('code', $code)->symbol;
    return $symbol;
}


function SymbolToCode($symbol)
{
    $code = Currency::getAll()->firstWhere('symbol', $symbol)->code;
    return $code;
}


function changeEnvironmentVariable($key, $value)
{
    $path = base_path('.env');

    if (is_bool(env($key)))
    {
        $old = env($key) ? 'true' : 'false';
    }
    elseif (env($key) === null)
    {
        $old = 'null';
    }
    else
    {
        $old = env($key);
    }

    if (file_exists($path))
    {
        if ($old == 'null')
        {

            file_put_contents($path, "\n$key=" . $value, FILE_APPEND);
        }
        else
        {
            file_put_contents($path, str_replace(
                "$key=" . $old, "$key=" . $value, file_get_contents($path)
            ));
        }
    }
}


function currency_fix($field, $code)
{
    $default_currency = Currency::getAll()->firstWhere('default', 1)->code;
    $rate = Currency::getAll()->firstWhere('code',$code)->rate;


    $base_amount = $field / $rate;


    $session_rate = Currency::getAll()->firstWhere('code', (Session::get('currency')) ? Session::get('currency') : $default_currency)->rate;

    return round($base_amount * $session_rate, 2);
}

function xss_clean($data)
{
    // Fix &entity\n;
    $data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
    $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
    $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
    $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

    // Remove any attribute starting with "on" or xmlns
    $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

    // Remove javascript: and vbscript: protocols
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

    // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

    // Remove namespaced elements (we do not need them)
    $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

    do
    {
        // Remove really unwanted tags
        $old_data = $data;
        $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
    }
    while ($old_data !== $data);

    return $data;
}

/**
 * stripBeforeSave method
 * This function strips or skips HTML tags
 *
 * @param string $string [The text that will be stripped]
 * @param array $options
 *
 * @return string
 */
function stripBeforeSave($string = null, $options = ['skipAllTags' => true, 'mergeTags' => false])
{
    $finalString = [];
    if ($options['skipAllTags'] === false) {
        $allow = '<h1><h2><h3><h4><h5><h6><p><b><br><hr><i><pre><small><strike><strong><sub><sup><time><u><form><input><textarea><button><select><option><label><frame><iframe><img><audio><video><a><link><nav><ul><ol><li><table><th><tr><td><thead><tbody><div><span><header><footer><main><section><article>';
        if (isset($options['mergeTags']) && $options['mergeTags'] === true && isset($options['allowedTags'])) {
            $allow .= is_array($options['allowedTags']) ? implode('', $options['allowedTags']) : trim($options['allowedTags']);
        } else {
            $allow = isset($options['allowedTags']) && is_array($options['allowedTags']) ? implode('', $options['allowedTags']) : trim($options['allowedTags']);
        }
        if (is_array($string)) {
            foreach ($string as $key => $value) {
                $finalString[$key] = strip_tags($value, $allow);
            }
        } else {
            $finalString = strip_tags($string, $allow);
        }
    } else {
        if (is_array($string)) {
            foreach ($string as $key => $value) {
                $finalString[$key] = strip_tags($value);
            }
        } else {
            $finalString = strip_tags($string);
        }
    }
    return !empty($finalString) ? $finalString : null;
}

function dataTableOptions(array $options = [])
{
    $default = [
        'dom' => 'lBfrtip',
        'buttons' => [],
        'order' => [0, 'desc'],
        'pageLength' => \Session::get('row_per_page'),
    ];

    return array_merge($default, $options);
}

function numberFormat($number, $decimal) {
    return number_format($number, $number == intval($number) ? 0 : $decimal);
}

function clearCache($name) {
    Cache::forget(config('cache.prefix') . $name);
}

function getInboxUnreadCount() {
    return DB::table(DB::raw("(SELECT * from messages where receiver_id=".Auth::id()." and `read`=0 ORDER by id DESC) as msg"))
        ->groupBy('booking_id')
        ->get()->count();
}

function g_e_v() {
    return env(a_k());
}

function a_k() {
    return base64_decode('SU5TVEFMTF9BUFBfU0VDUkVU');
}

function g_d() {
    return request()->getHost();
}

function g_c_v() {
    return cache('a_s_k');
}

function p_c_v() {
    return cache(['a_s_k' => g_e_v()], 2629746);
}

function convert_currency($from = '', $to = '', $price) {

    $from = currentCurrency($from);
    $to = currentCurrency($to);
    $price       = str_replace(']','',$price);//For Php Version 7.2
    $base_amount = (float) $price / $from->rate;
    return round($base_amount * $to->rate, $to->rate > 1000 ? 0 : 2);
}

function defaultCurrency() {
    return Currency::getAll()->firstWhere('default', 1);
}

function currentCurrency($code = null) {
    if($code && $code <> '') {
        return Currency::getAll()->firstWhere('code', $code);
    } elseif(\Session::get('currency')) {
        return Currency::getAll()->firstWhere('code', \Session::get('currency'));
    }
    return Currency::getAll()->firstWhere('default', 1);
}
