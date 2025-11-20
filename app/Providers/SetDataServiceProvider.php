<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\{
    Currency,
    Language,
    Settings,
    StartingCities,
    JoinUs,
    Banners,
    Country,
    Page
};

use View, Config, Schema, Auth, App, Session, Validator, Cache;


class SetDataServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (env('DB_DATABASE') && env('APP_INSTALL')) {
            if (Schema::hasTable('currency')) {
                $this->currency();
            }

            if (Schema::hasTable('language')) {
                $this->language();
            }

            if (Schema::hasTable('settings')) {
                $this->settings();
                $this->api_info_set();
            }
            if (Schema::hasTable('pages')) {
                $this->pages();
            }

            if (Schema::hasTable('starting_cities')) {
                $this->destination();
            }

            $this->creditcard_validation();

        }
    }

    public function creditcard_validation()
    {

        Validator::extend('expires', function ($attribute, $value, $parameters, $validator) {
            $input      = $validator->getData();
            $expiryDate = gmdate('Ym', gmmktime(0, 0, 0, (int) array_get($input, $parameters[0]), 1, (int) array_get($input, $parameters[1])));
            return ($expiryDate > gmdate('Ym')) ? true : false;
        });

        Validator::extend('validate_cc', function ($attribute, $value, $parameters) {
            $str = '';
            foreach (array_reverse(str_split($value)) as $i => $c) {
                $str .= $i % 2 ? $c * 2 : $c;
            }
            return array_sum(str_split($str)) % 10 === 0;
        });
    }

    public function register()
    {
        //
    }

    public function currency()
    {
        ini_set('max_execution_time', 300);

        $currencies = Currency::getAll()->where('status', '=', 'Active');
        View::share('currencies', $currencies);
        View::share('currency', $currencies->pluck('code', 'code'));

        if(!\Session::get('currency')) {
            try {
                if($_SERVER["REMOTE_ADDR"]){
                    $remoteData = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=sadf' . $_SERVER["REMOTE_ADDR"]));
                    $default_currency = Currency::getAll()->where('status', '=', 'Active')->where('code', '=', $remoteData['geoplugin_currencyCode'])->first();
                    $default_country = $remoteData['geoplugin_countryCode'];
                }
            } catch(\Exception $e) {
                $default_currency = Currency::getAll()->firstWhere('default', '=', '1');
            }
        } else {
            $default_currency = Currency::getAll()->firstWhere('code', \Session::get('currency'));
        }

        if(!$default_currency) {
            $default_currency = Currency::getAll()->firstWhere('default', '=', '1');
        }

        if(!isset($default_country)) {
            $default_country = Country::getAll()->first()->short_name;
        }

        View::share('default_country', $default_country);
        View::share('default_currency', $default_currency);
        Session::put('currency', $default_currency->code);
        Session::put('symbol', $default_currency->symbol);
    }

    public function language()
    {
        $language = Language::where('status', '=', 'Active')->pluck('name', 'short_name');
        View::share('language', $language);

        $default_language = Language::where('status', '=', 'Active')->where('default', '=', '1')->limit(1)->get();
        View::share('default_language', $default_language);
        if ($default_language->count() > 0) {
            Session::put('language', $default_language[0]->value);
            App::setLocale($default_language[0]->value);
        }
    }

    public function pages()
    {
        $footer_first  = Page::where('position', 'first')->where('status', 'Active')->get();
        $footer_second = Page::where('position', 'second')->where('status', 'Active')->get();
        $footer_third  = Page::where('position', 'third')->where('status', 'Active')->get();
        $footer_fourth = Page::where('position', 'fourth')->where('status', 'Active')->get();
        View::share('footer_first', $footer_first);
        View::share('footer_second', $footer_second);
        View::share('footer_third', $footer_third);
        View::share('footer_fourth', $footer_fourth);
    }

    public function destination()
    {
        $popular_cities  = StartingCities::where('status', 'Active')->get();
        View::share('popular_cities', $popular_cities);
    }

    public function api_info_set()
    {
        $google   = Settings::where('type', 'google')->pluck('value', 'name')->toArray();
        $facebook = Settings::where('type', 'facebook')->pluck('value', 'name')->toArray();
        if (isset($google['client_id'])) {
            \Config::set(['services.google' => [
                    'client_id' => $google['client_id'],
                    'client_secret' => $google['client_secret'],
                    'redirect' => url('/googleAuthenticate'),
                    ]
                ]);
        }

        if (isset($facebook['client_id'])) {
             \Config::set(['services.facebook' => [
                        'client_id' => $facebook['client_id'],
                        'client_secret' => $facebook['client_secret'],
                        'redirect' => url('/facebookAuthenticate'),
                        ]
                        ]);
        }
    }


    public function settings()
    {
        $settings = Settings::getAll();
        if (!empty($settings)) {

            // General settings
            $general = $settings->where('type', 'general')->pluck('value', 'name')->toArray();

            $name = $general['name'] ?? env('APP_NAME', 'Respite Care Booking');

            if (!defined('SITE_NAME')) {
                define('SITE_NAME', $name);
            }
            View::share('site_name', $name);
            Config::set('site_name', $name);


            //App logo
            if (!empty($general['logo']) && file_exists(public_path('front/images/logos/'. $general['logo']))) {
                $logo = url('public/front/images/logos/'. $general['logo']);
            } else {
                $logo = env('APP_LOGO_URL') != '' ? env('APP_LOGO_URL') : url('public/front/images/logos/logo.png');
            }
            if (!defined('LOGO_URL')) {
                define('LOGO_URL', $logo);
            }
            View::share('logo', $logo);



            //App email logo
            if (!empty($general['email_logo']) && file_exists(public_path('front/images/logos/'. $general['email_logo']))) {
                $emailLogo = url('public/front/images/logos/'. $general['email_logo']);
            } else {
                $emailLogo = env('APP_EMAIL_LOGO_URL') != '' ? env('APP_EMAIL_LOGO_URL') : url('public/front/images/logos/email_logo.png');
            }
            if (!defined('EMAIL_LOGO_URL')) {
                define('EMAIL_LOGO_URL', $emailLogo);
            }

            //App head code/Analytics code
            $headCode = !empty($general['head_code']) ? $general['head_code'] : env('APP_HEAD_CODE', '');
            View::share('head_code', $headCode);

            //App favicon
            if (!empty($general['favicon']) && file_exists(public_path('front/images/logos/'. $general['favicon']))) {

                $favicon = url('public/front/images/logos/'. $general['favicon']);
            } else {
                $favicon = env('APP_FAVICON_URL') != '' ? env('APP_FAVICON_URL') : url('public/front/images/logos/favicon.png');
            }
            View::share('favicon', $favicon);

            // Google Map Key
            $map     = $settings->where('type', 'googleMap')->pluck('value', 'name')->toArray();
            if (!empty($map['key'])) {
                    View::share('map_key', $map['key']);
                    define('MAP_KEY', $map['key']);
            }

            // Join us
            $join_us = Settings::where('type', 'join_us')->get();
            View::share('join_us', $join_us);

            View::share('settings', $settings);
        }

        //App Banner
        $banner = Banners::where('status', 'Active')->first();

        if ( !empty($banner) && file_exists(public_path('front/images/logos/'. $general['logo'])) )
        {
            $banner_image = url('public/front/images/banners/'.$banner->image);
        } else {
            $banner_image = url('public/images/default-banner.jpg');
        }

        if ( !defined('BANNER_URL') ) {
            define('BANNER_URL', $banner_image);
        }

    }
}
