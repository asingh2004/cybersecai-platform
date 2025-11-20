<?php
namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Http\Controllers\Controller;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Cache;


use View, Auth, App, Session, Route;

use App\Models\{
    Currency,
    Properties,
    Page,
    Settings,
    RespiteType,
    StartingCities,
    Testimonials,
    language,
    Admin,
    User,
    Wallet
};


require base_path() . '/vendor/autoload.php';

class HomeController extends Controller
{
    private $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index()
    {
        $data['starting_cities'] = StartingCities::getAll();
        $data['respite_type'] = RespiteType::getAll();
        $data['properties']          = Properties::recommendedHome();
        $data['testimonials']        = Testimonials::getAll();
        $sessionLanguage             = Session::get('language');
        $language                    = Settings::getAll()->where('name', 'default_language')->where('type', 'general')->first();

        $languageDetails             = language::where(['id' => $language->value])->first();

        if (!($sessionLanguage)) {
            Session::pull('language');
            Session::put('language', $languageDetails->short_name);
            App::setLocale($languageDetails->short_name);
        }

        $pref = Settings::getAll();

        $prefer = [];

        if (!empty($pref)) {
            foreach ($pref as $value) {
                $prefer[$value->name] = $value->value;
            }
            Session::put($prefer);
        }
        $data['date_format'] = Settings::getAll()->firstWhere('name', 'date_format_type')->value;
      
      
      
      
        // Hard-coded AI Features
	$data['aiFeatures'] = [
    (object)[
        'name' => 'Summarise Large PDFs',
        'image' => asset('public/front/images/starting_cities/starting_city_1723450435.png'), // Generate proper URL
        'description' => 'Unlike most, this assistant will summarise very large PDFs.',
        'href' => url('/login') // Fixed: Use the route helper directly without Blade syntax
    ],
    (object)[
        'name' => 'Custom Built AI Assistants',
        'image' => asset('public/front/images/starting_cities/starting_city_1723451234.png'), // Generate proper URL
        'description' => 'Built AI Assistants for your business.',
        'href' => url('/login') // Fixed: Use the URL helper directly without Blade syntax
    ],
    (object)[
        'name' => 'Discover PII Data',
        'image' => asset('public/front/images/starting_cities/starting_city_1723452120.png'), // Generate proper URL
        'description' => 'Discover & act on PII data in your files.',
        'href' => url('/login') // Fixed: Use the URL helper directly without Blade syntax
    ],
    (object)[
        'name' => 'Summarise Multiple Web Blogs',
        'image' => asset('public/front/images/starting_cities/starting_city_1723450435.png'), // Generate proper URL
        'description' => 'Save time by summarising multiple web blogs.',
        'href' => url('/login') // Fixed: Use the URL helper directly without Blade syntax
    ],
	];
      

        return view('home.home', $data);
    }

    public function phpinfo()
    {
        echo phpinfo();
    }

    public function login()
    {
        return view('home.login');
    }

    public function setSession(Request $request)
    {
        if ($request->currency) {
            Session::put('currency', $request->currency);
            $symbol = Currency::code_to_symbol($request->currency);
            Session::put('symbol', $symbol);
        } elseif ($request->language) {
            Session::put('language', $request->language);
            $name = language::name($request->language);
            Session::put('language_name', $name);
            App::setLocale($request->language);
        }
    }

    public function cancellation_policies()
    {
        return view('home.cancellation_policies');
    }

    public function staticPages(Request $request)
    {
        $pages          = Page::where(['url'=>$request->name, 'status'=>'Active']);
        if (!$pages->count()) {
            abort('404');
        }
        $pages           = $pages->first();
        $data['content'] = str_replace(['SITE_NAME', 'SITE_URL'], [SITE_NAME, url('/')], $pages->content);
        $data['title']   = $pages->url;
        $data['url']     = url('/').'/';
        $data['img']     = $data['url'].'public/images/2222hotel_room2.jpg';

        return view('home.static_pages', $data);
    }


    public function activateDebugger()
    {
      setcookie('debugger', 0);
    }

    public function walletUser(Request $request){

        $users = User::all();
        $wallet = Wallet::all();


        if (!$users->isEmpty() && $wallet->isEmpty() ) {
            foreach ($users as $key => $user) {

                Wallet::create([
                    'user_id' => $user->id,
                    'currency_id' => 1,
                    'balance' => 0,
                    'is_active' => 0
                ]);
            }
        }

        return redirect('/');

    }

}
