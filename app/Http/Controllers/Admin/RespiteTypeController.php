<?php

/**
 * RespiteType Controller
 *
 * RespiteType Controller manages Respite Types by admin.
 *
 * @category   RespiteType
 * @package    OzzieAccom
 * @author     Abhishek Singh
 * @copyright  OzzieAccom 2022
 * @license
 * @version    2.7
 * @link       Ozzieaccom.com
 * @email      info@ozzieaccom.com
 * @since      Version 1.3
 * @deprecated None
 */

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\DataTables\RespiteTypeDataTable;
use App\Models\RespiteType;
use Illuminate\Support\Facades\Cache;
use Validator;
use App\Http\Helpers\Common;

class RespiteTypeController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(RespiteTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.respiteTypes.view');
    }

    public function add(Request $request)
    {
        $info = $request->isMethod('post');

        if (! $info) {
            return view('admin.respiteTypes.add');
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
                $respiteType               = new RespiteType;
                $respiteType->name         = $request->name;
                $respiteType->save();

                Cache::forget(config('cache.prefix') . '.property.types.respite');
                $this->helper->one_time_message('success', 'Added Successfully');
                return redirect('admin/settings/respite-type');
            }
        }
    }

    public function update(Request $request)
    {
        $info = $request->isMethod('post');
        if (! $info) {
            $data['result'] = RespiteType::find($request->id);

            return view('admin.respiteTypes.edit', $data);
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
                    $respiteType = RespiteType::find($request->id);
                    $respiteType->name   = $request->name;
                    $respiteType->save();
                    Cache::forget(config('cache.prefix') . '.property.types.respite');
                }
                $this->helper->one_time_message('success', 'Updated Successfully');

                return redirect('admin/settings/respite-type');
            }
        }
    }

    public function delete(Request $request)
    {
        if (env('APP_MODE', '') != 'test') {
            RespiteType::find($request->id)->delete();
            Cache::forget(config('cache.prefix') . '.property.types.respite');
        }

        $this->helper->one_time_message('success', 'Deleted Successfully');

        return redirect('admin/settings/respite-type');
    }
}
