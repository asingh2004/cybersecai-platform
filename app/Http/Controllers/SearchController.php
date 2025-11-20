<?php

namespace App\Http\Controllers;

use Session;

use App\Http\Helpers\Common;

use Illuminate\Http\Request;

use App\Models\{Properties,
    Settings,
    SpaceType,
    PropertyType,
    Amenities,
    AmenityType,
    Currency,
    PropertyDates,
    RespiteType,
    PropertyAddress
};

class SearchController extends Controller
{
    private $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(Request $request)
    {


        $location = $request->input('location');
        $address = str_replace(" ", "+", "$location");
        $map_where = 'https://maps.google.com/maps/api/geocode/json?key=' . MAP_KEY . '&address=' . $address . '&sensor=false';
        $geocode = $this->content_read($map_where);
        $json = json_decode($geocode);

        /*$test1 = "Debug";
        var_dump($test1);
        $full_address = $request->input('location');
        $address = str_replace([" ", "%2C"], ["+", ","], "$full_address");
        $map_where2 = 'https://maps.google.com/maps/api/geocode/json?key=' . MAP_KEY . '&address=' . $address . '&sensor=false&libraries=places';
        $geocode2 = $this->content_read($map_where2);
        $json2 = json_decode($geocode2);
        var_dump($map_where2);
        var_dump($json2);*/

        if ($json->{'results'}) {
            $data['lat'] = isset($json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'}) ? $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'} : 0;
            $data['long'] = isset($json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'}) ? $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'} : 0;
        } else {
            $data['lat'] = 0;
            $data['long'] = 0;
        }

        $data['location'] = $request->input('location');
        $data['checkin'] = $request->input('checkin');
        $data['checkout'] = $request->input('checkout');

        $data['respite_type'] = $request->input('respite_type');


        $data['respite_service_type'] = RespiteType::pluck('name');


        /*if (($vrespiteType == 'lowcare') || ($vrespiteType == 'LOW Care')){
            $data['respite_service_type'] = 'LOW Care';
        } elseif (($vrespiteType == 'highcare') || ($vrespiteType == 'HIGH Care')){
            $data['respite_service_type'] = 'HIGH Care';
        } else {
            $data['respite_service_type'] = 'All';
        }*/


        //$data['guest'] = $request->input('guest');
        
        
        $data['bedrooms'] = $request->input('bedrooms');
        $data['beds'] = $request->input('beds');
        $data['bathrooms'] = $request->input('bathrooms');
        $data['min_price'] = $request->input('min_price');
        $data['max_price'] = $request->input('max_price');

        $data['space_type'] = SpaceType::getAll()->where('status', 'Active')->pluck('name', 'id');
        $data['property_type'] = PropertyType::getAll()->where('status', 'Active')->pluck('name', 'id');
        $data['amenities'] = Amenities::where('status', 'Active')->get();
        $data['amenities_type'] = AmenityType::pluck('name', 'id');

        $data['property_type_selected'] = explode(',', $request->input('property_type'));
        $data['space_type_selected'] = explode(',', $request->input('space_type'));
        $data['amenities_selected'] = explode(',', $request->input('amenities'));
        $currency = Currency::getAll();
        if (Session::get('currency')) $data['currency_symbol'] = $currency->firstWhere('code', Session::get('currency'))->symbol;
        else $data['currency_symbol'] = $currency->firstWhere('default', 1)->symbol;
        $minPrice = Settings::getAll()->where('name', 'min_search_price')->first()->value;
        $maxPrice = Settings::getAll()->where('name', 'max_search_price')->first()->value;
        $data['default_min_price'] = $this->helper->convert_currency(Currency::getAll()->firstWhere('default')->code, '', $minPrice);
        $data['default_max_price'] = $this->helper->convert_currency(Currency::getAll()->firstWhere('default')->code, '', $maxPrice);
        if (!$data['min_price']) {
            $data['min_price'] = $data['default_min_price'];
            $data['max_price'] = $data['default_max_price'];
        }
        $data['date_format'] = Settings::getAll()->firstWhere('name', 'date_format_type')->value;

        
        return view('search.view', $data);


    }

    function searchResult(Request $request)
    {

        $full_address = $request->input('location');
        $checkin = $request->input('checkin');
        $checkout = $request->input('checkout');
        $guest = $request->input('guest');
    
        $vrespite_service_type = $request->input('respite_service_type');
        //$vrespite_service_type = $request->input('respite_type');

        $bedrooms = $request->input('bedrooms');
        $beds = $request->input('beds');
        $bathrooms = $request->input('bathrooms');
        $property_type = $request->input('property_type');
        $space_type = $request->input('space_type');
        $amenities = $request->input('amenities');
        $book_type = $request->input('book_type');
        $map_details = $request->input('map_details');
        $min_price = $request->input('min_price');
        $max_price = $request->input('max_price');


        $respite_service_type = RespiteType::where('name', $vrespite_service_type)->pluck('name')->first();

        if (!$respite_service_type) {

            $respite_service_type = $request->input('respite_type');
        }


        if (!is_array($property_type)) {
            if ($property_type != '') {
                $property_type = explode(',', $property_type);
            } else {
                $property_type = [];
            }
        }

        if (!is_array($space_type)) {
            if ($space_type != '') {
                $space_type = explode(',', $space_type);
            } else {
                $space_type = [];
            }
        }

        if (!is_array($book_type)) {
            if ($book_type != '') {
                $book_type = explode(',', $book_type);
            } else {
                $book_type = [];
            }
        }
        if (!is_array($amenities)) {
            if ($amenities != '') {
                $amenities = explode(',', $amenities);
            } else {
                $amenities = [];
            }
        }
        

        $property_type_val = [];
        $properties_whereIn = [];
        $space_type_val = [];
       

        $address = str_replace([" ", "%2C"], ["+", ","], "$full_address");
        $map_where = 'https://maps.google.com/maps/api/geocode/json?key=' . MAP_KEY . '&address=' . $address . '&sensor=false&libraries=places';
        $geocode = $this->content_read($map_where);
        $json = json_decode($geocode);

        if ($map_details != '') {
            $map_data = explode('~', $map_details);
            $minLat = $map_data[2];
            $minLong = $map_data[3];
            $maxLat = $map_data[4];
            $maxLong = $map_data[5];

            /*$minLat = $map_data[2] - 0.01;
            $maxLat = $map_data[2] + 0.01;
            $minLong = $map_data[3] - 0.01;
            $maxLong = $map_data[3] + 0.01;*/



        } else {
            if ($json->{'results'}) {
                $data['lat'] = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'};
                $data['long'] = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'};

                /*$minLat = $data['lat'] - 0.35;
                $maxLat = $data['lat'] + 0.35;
                $minLong = $data['long'] - 0.35;
                $maxLong = $data['long'] + 0.35;
                */

                // Create a bounding box with sides ~1km away from the center point, which means it will pick up 1 km on all sides
                $minLat = $data['lat'] - 0.01;
                $maxLat = $data['lat'] + 0.01;
                $minLong = $data['long'] - 0.01;
                $maxLong = $data['long'] + 0.01;
            } else {
                $data['lat'] = 0;
                $data['long'] = 0;

                $minLat = -1100;
                $maxLat = 1100;
                $minLong = -1100;
                $maxLong = 1100;
            }
        }

        $users_where['users.status'] = 'Active';
        $users_subs_status_where['users.subscription_status'] = 'active';
        $users_inactive_subs_status_where['users.subscription_status'] = 'inactive';


        $checkin = date('Y-m-d', strtotime($checkin));
        $checkout = date('Y-m-d', strtotime($checkout));

        $days = $this->helper->get_days($checkin, $checkout);
        unset($days[count($days) - 1]);

        $calendar_where['date'] = $days;

        $not_available_property_ids = PropertyDates::whereIn('date', $days)->where('status', 'Not available')->distinct()->pluck('property_id');
        $properties_where['properties.accommodates'] = $guest;
    

        $properties_where['properties.status'] = 'Listed';

        $properties_unlisted_where['properties.status'] = 'Unlisted';

        $properties_where['properties.respite_service_type'] = $respite_service_type; 
  

        if ($bedrooms) {
            $properties_where['properties.bedrooms'] = $bedrooms;
        }

        if ($bathrooms) {
            $properties_where['properties.bathrooms'] = $bathrooms;
        }

        if ($beds) {
            $properties_where['properties.beds'] = $beds;
        }

        if (count($space_type)) {
            foreach ($space_type as $space_value) {
                array_push($space_type_val, $space_value);
            }
            $properties_whereIn['properties.space_type'] = $space_type_val;
        }

        if (count($property_type)) {
            foreach ($property_type as $property_value) {
                array_push($property_type_val, $property_value);
            }

            $properties_whereIn['properties.property_type'] = $property_type_val;
        }

        $currency_rate = Currency::getAll()
            ->firstWhere('code', \Session::get('currency'))
            ->rate;


//var_dump($respite_service_type);

if ($respite_service_type) {

$properties_inactive_subs = Properties::with([
        'property_address',
        'users'
    ])
    ->where('respite_service_type', $respite_service_type)
    ->whereHas('property_address', function ($query) use ($minLat, $maxLat, $minLong, $maxLong) {
        $query->whereRaw("latitude between $minLat and $maxLat and longitude between $minLong and $maxLong");
    })
    ->whereHas('users', function ($query) use ($users_where, $users_inactive_subs_status_where) {
        $query->where($users_where)
            ->where(function ($query) use ($users_inactive_subs_status_where) {
                $query->where($users_inactive_subs_status_where);
            });
    })
    ->whereIn('status', $properties_where)
    ->whereNotIn('id', $not_available_property_ids)
    ->whereNotIn('status', $properties_unlisted_where);


$properties_active_subs = Properties::with([
        'property_address',
        'users'
    ])
    ->where('respite_service_type', $respite_service_type)
    ->whereHas('property_address', function ($query) use ($minLat, $maxLat, $minLong, $maxLong) {
        $query->whereRaw("latitude between $minLat and $maxLat and longitude between $minLong and $maxLong");
    })
    ->whereHas('users', function ($query) use ($users_where, $users_subs_status_where) {
        $query->where($users_where)
            ->where(function ($query) use ($users_subs_status_where) {
                $query->where($users_subs_status_where);
            });
    })
    ->whereIn('status', $properties_where)
    ->whereNotIn('id', $not_available_property_ids)
    ->whereNotIn('status', $properties_unlisted_where)
    ->union($properties_inactive_subs)
    ->limit(25)
    ->get();
} else{
    $properties_inactive_subs = Properties::with([
        'property_address',
        'users'
    ])
    ->whereHas('property_address', function ($query) use ($minLat, $maxLat, $minLong, $maxLong) {
        $query->whereRaw("latitude between $minLat and $maxLat and longitude between $minLong and $maxLong");
    })
    ->whereHas('users', function ($query) use ($users_where, $users_inactive_subs_status_where) {
        $query->where($users_where)
            ->where(function ($query) use ($users_inactive_subs_status_where) {
                $query->where($users_inactive_subs_status_where);
            });
    })
    ->whereIn('status', $properties_where)
    ->whereNotIn('id', $not_available_property_ids)
    ->whereNotIn('status', $properties_unlisted_where);


$properties_active_subs = Properties::with([
        'property_address',
        'users'
    ])
    ->whereHas('property_address', function ($query) use ($minLat, $maxLat, $minLong, $maxLong) {
        $query->whereRaw("latitude between $minLat and $maxLat and longitude between $minLong and $maxLong");
    })
    ->whereHas('users', function ($query) use ($users_where, $users_subs_status_where) {
        $query->where($users_where)
            ->where(function ($query) use ($users_subs_status_where) {
                $query->where($users_subs_status_where);
            });
    })
    ->whereIn('status', $properties_where)
    ->whereNotIn('id', $not_available_property_ids)
    ->whereNotIn('status', $properties_unlisted_where)
    ->union($properties_inactive_subs)
    ->limit(25)
    ->get();
}

//Active and Inactive Subscribers are merged into one
    $properties = $properties_active_subs;


$properties = $properties->paginate(Session::get('row_per_page'))->toJson();
echo $properties;

    }




function searchResult3(Request $request)
    {
        $full_address = $request->input('location');
        $checkin = $request->input('checkin');
        $checkout = $request->input('checkout');
        $guest = $request->input('guest');
        $bedrooms = $request->input('bedrooms');
        $beds = $request->input('beds');
        $bathrooms = $request->input('bathrooms');
        $property_type = $request->input('property_type');
        $space_type = $request->input('space_type');
        $amenities = $request->input('amenities');
        $book_type = $request->input('book_type');
        $map_details = $request->input('map_details');
        $min_price = $request->input('min_price');
        $max_price = $request->input('max_price');



        if (!is_array($property_type)) {
            if ($property_type != '') {
                $property_type = explode(',', $property_type);
            } else {
                $property_type = [];
            }
        }

        if (!is_array($space_type)) {
            if ($space_type != '') {
                $space_type = explode(',', $space_type);
            } else {
                $space_type = [];
            }
        }

        if (!is_array($book_type)) {
            if ($book_type != '') {
                $book_type = explode(',', $book_type);
            } else {
                $book_type = [];
            }
        }
        if (!is_array($amenities)) {
            if ($amenities != '') {
                $amenities = explode(',', $amenities);
            } else {
                $amenities = [];
            }
        }

        $property_type_val = [];
        $properties_whereIn = [];
        $space_type_val = [];

        $address = str_replace([" ", "%2C"], ["+", ","], "$full_address");
        $map_where = 'https://maps.google.com/maps/api/geocode/json?key=' . MAP_KEY . '&address=' . $address . '&sensor=false&libraries=places';
        $geocode = $this->content_read($map_where);
        $json = json_decode($geocode);

        if ($map_details != '') {
            $map_data = explode('~', $map_details);
            $minLat = $map_data[2];
            $minLong = $map_data[3];
            $maxLat = $map_data[4];
            $maxLong = $map_data[5];
        } else {
            if ($json->{'results'}) {
                $data['lat'] = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'};
                $data['long'] = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'};

                $minLat = $data['lat'] - 0.35;
                $maxLat = $data['lat'] + 0.35;
                $minLong = $data['long'] - 0.35;
                $maxLong = $data['long'] + 0.35;
            } else {
                $data['lat'] = 0;
                $data['long'] = 0;

                $minLat = -1100;
                $maxLat = 1100;
                $minLong = -1100;
                $maxLong = 1100;
            }
        }

        $users_where['users.status'] = 'Active';

        $checkin = date('Y-m-d', strtotime($checkin));
        $checkout = date('Y-m-d', strtotime($checkout));

        $days = $this->helper->get_days($checkin, $checkout);
        unset($days[count($days) - 1]);

        $calendar_where['date'] = $days;

        $not_available_property_ids = PropertyDates::whereIn('date', $days)->where('status', 'Not available')->distinct()->pluck('property_id');
        $properties_where['properties.accommodates'] = $guest;

        $properties_where['properties.status'] = 'Listed';

        if ($bedrooms) {
            $properties_where['properties.bedrooms'] = $bedrooms;
        }

        if ($bathrooms) {
            $properties_where['properties.bathrooms'] = $bathrooms;
        }

        if ($beds) {
            $properties_where['properties.beds'] = $beds;
        }

        if (count($space_type)) {
            foreach ($space_type as $space_value) {
                array_push($space_type_val, $space_value);
            }
            $properties_whereIn['properties.space_type'] = $space_type_val;
        }

        if (count($property_type)) {
            foreach ($property_type as $property_value) {
                array_push($property_type_val, $property_value);
            }

            $properties_whereIn['properties.property_type'] = $property_type_val;
        }

        $currency_rate = Currency::getAll()
            ->firstWhere('code', \Session::get('currency'))
            ->rate;

        $properties = Properties::with([
            'property_address',
            'property_price',
            'users'
        ])
            ->whereHas('property_address', function ($query) use ($minLat, $maxLat, $minLong, $maxLong) {
                $query->whereRaw("latitude between $minLat and $maxLat and longitude between $minLong and $maxLong");
            })
            ->whereHas('property_price', function ($query) use ($min_price, $max_price, $currency_rate) {
                $query->join('currency', 'currency.code', '=', 'property_price.currency_code');
                $query->whereRaw('((price / currency.rate) * ' . $currency_rate . ') >= ' . $min_price . ' and ((price / currency.rate) * ' . $currency_rate . ') <= ' . $max_price);
            })
            ->whereHas('users', function ($query) use ($users_where) {
                $query->where($users_where);
            })
            ->whereNotIn('id', $not_available_property_ids);

        if ($properties_where) {
            foreach ($properties_where as $row => $value) {
                if ($row == 'properties.accommodates' || $row == 'properties.bathrooms' || $row == 'properties.bedrooms' || $row == 'properties.beds') {
                    $operator = '>=';
                } else {
                    $operator = '=';
                }

                if ($value == '') {
                    $value = 0;
                }

                $properties = $properties->where($row, $operator, $value);
            }
        }

        if ($properties_whereIn) {
            foreach ($properties_whereIn as $row_properties_whereIn => $value_properties_whereIn) {
                $properties = $properties->whereIn($row_properties_whereIn, array_values($value_properties_whereIn));
            }
        }

        if (count($amenities)) {
            foreach ($amenities as $amenities_value) {
                $properties = $properties->whereRaw('find_in_set(' . $amenities_value . ', amenities)');
            }
        }

        if (count($book_type) && count($book_type) != 2) {
            foreach ($book_type as $book_value) {
                $properties = $properties->where('booking_type', $book_value);
            }
        }

        $properties = $properties->paginate(Session::get('row_per_page'))->toJson();
        echo $properties;
    }





    public function content_read($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}