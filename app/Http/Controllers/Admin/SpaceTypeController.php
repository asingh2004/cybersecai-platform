<?php

/**
 * SpaceType Controller
 *
 * SpaceType Controller manages SpaceType by admin.
 *
 * @category   SpaceType
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
use App\DataTables\SpaceTypeDataTable;
use App\Models\SpaceType;
use Illuminate\Support\Facades\Cache;
use Validator;
use App\Http\Helpers\Common;

class SpaceTypeController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(SpaceTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.spaceTypes.view');
    }

    public function add(Request $request)
    {
        if (! $request->isMethod('post')) {
             return view('admin.spaceTypes.add');
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'name'           => 'required|max:25',
                    'description'    => 'required',
                    'status'         => 'required'
                    );

            $fieldNames = array(
                        'name'              => 'Name',
                        'description'       => 'Description',
                        'status'            => 'Status'
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $spaceType                = new SpaceType;
                $spaceType->name          = $request->name;
                $spaceType->description   = $request->description;
                $spaceType->status        = $request->status;
                $spaceType->save();
                Cache::forget(config('cache.prefix') . '.property.types.space');
                $this->helper->one_time_message('success', 'Added Successfully');
                return redirect('admin/settings/space-type');
            }
        }
    }

    public function update(Request $request)
    {
        if (! $request->isMethod('post')) {
             $data['result'] = SpaceType::find($request->id);

             return view('admin.spaceTypes.edit', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'name'           => 'required|max:25',
                    'description'    => 'required',
                    'status'         => 'required'
                    );

            $fieldNames = array(
                        'name'              => 'Name',
                        'description'       => 'Description',
                        'status'            => 'Status'
                        );
            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $spaceType  = SpaceType::find($request->id);
                $spaceType->name          = $request->name;
                $spaceType->description   = $request->description;
                $spaceType->status        = $request->status;
                $spaceType->save();

                Cache::forget(config('cache.prefix') . '.property.types.space');
                $this->helper->one_time_message('success', 'Updated Successfully');

                return redirect('admin/settings/space-type');
            }
        }
    }

    public function delete(Request $request)
    {
        SpaceType::find($request->id)->delete();
        Cache::forget(config('cache.prefix') . '.property.types.space');
        $this->helper->one_time_message('success', 'Deleted Successfully');

        return redirect('admin/settings/space-type');
    }
}
