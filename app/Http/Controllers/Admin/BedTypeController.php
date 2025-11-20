<?php

/**
 * BedType Controller
 *
 * BedType Controller manages Bed Types by admin.
 *
 * @category   BedType
 * @package    OzzieAccom
 * @author     Abhishek Singh
 * @copyright  OzzieAccom 2022
 * @license
 * @version    2.7
 * @link       Ozzieaccom.com
 * @email      support@techvill.net
 * @since      Version 1.3
 * @deprecated None
 */

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\DataTables\BedTypeDataTable;
use App\Models\BedType;
use App\Models\RespiteType;
use App\Models\Permissions;
use Illuminate\Support\Facades\Cache;
use Validator;
use App\Http\Helpers\Common;

class BedTypeController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(BedTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.bedTypes.view');
    }

    public function add(Request $request)
    {
        $info = $request->isMethod('post');

        //Forecefully clearing RespiteType & Permissions Data

        //RespiteType::find($request->id)->delete();
        Cache::forget(config('cache.prefix') . '.property.types.respite');
        Cache::forget(config('cache.prefix') . '.permissions');
        Cache::forget(config('cache.prefix') . '.banks');
        Cache::forget(config('cache.prefix') . '.users');

        if (! $info) {
            return view('admin.bedTypes.add');
        } elseif ($info) {
            $rules = array(
                    'name'    => 'required|max:50'
                    );

            $fieldNames = array(
                        'name'  => 'Name'
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $bedType               = new BedType;
                $bedType->name         = $request->name;
                $bedType->save();

                Cache::forget(config('cache.prefix') . '.property.types.bed');
                $this->helper->one_time_message('success', 'Added Successfully');
                return redirect('admin/settings/bed-type');
            }
        }
    }

    public function update(Request $request)
    {
        $info = $request->isMethod('post');
        if (! $info) {
            $data['result'] = BedType::find($request->id);

            return view('admin.bedTypes.edit', $data);
        } elseif ($info) {
            $rules = array(
                    'name'  => 'required|max:50'
                    );

            $fieldNames = array(
                        'name'    => 'Name'
                        );
            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    $bedType = BedType::find($request->id);
                    $bedType->name   = $request->name;
                    $bedType->save();
                    Cache::forget(config('cache.prefix') . '.property.types.bed');
                }
                $this->helper->one_time_message('success', 'Updated Successfully');

                return redirect('admin/settings/bed-type');
            }
        }
    }

    public function delete(Request $request)
    {
            

            //Permissions::find($request->id)->delete();
            //Cache::forget(config('cache.prefix') . '.permissions');




        if (env('APP_MODE', '') != 'test') {
            BedType::find($request->id)->delete();
            Cache::forget(config('cache.prefix') . '.property.types.bed');
        }

        $this->helper->one_time_message('success', 'Deleted Successfully');

        return redirect('admin/settings/bed-type');
    }
}
