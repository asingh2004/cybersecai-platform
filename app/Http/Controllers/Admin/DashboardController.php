<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\{
    User,
    Properties,
    Bookings
};

class DashboardController extends Controller
{
    public function index()
    {
        $data['facilities_count']        = DB::table('property_address')
            ->count(DB::raw('DISTINCT property_address.address_line_1'));

        $data['ABN_holder_count']        = DB::table('users')
            ->whereNotNull('ABN')
            ->count(DB::raw('DISTINCT ABN'));

        $data['subscribed_facility_count'] = DB::table('users')
            ->where('subscription_status', '=', 'active')
            ->sum('facilities_subscribed');

        $data['total_users_count']        = User::count();
        $data['total_property_count']     = Properties::count();
        $data['total_reservations_count'] = Bookings::count();

        $data['today_users_count']        = User::whereDate('created_at', DB::raw('CURDATE()'))->count();
        $data['today_property_count']     = Properties::whereDate('created_at', DB::raw('CURDATE()'))->count();
        $data['today_reservations_count'] = Bookings::whereDate('created_at', DB::raw('CURDATE()'))->count();

        $properties = new Properties;
        $data['propertiesList'] = $properties->getLatestProperties();

        $bookings = new Bookings;
        $data['bookingList'] = $bookings->getBookingLists();

        // 🟢 Add pending users for approval blade/table
        $data['pendingUsers'] = User::where('status', 'Inactive')
                                ->orderBy('created_at', 'asc')
                                ->get();

        return view('admin.dashboard', $data);
    }
}