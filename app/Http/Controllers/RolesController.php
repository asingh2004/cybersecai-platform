<?php

/**
 * Role Controller
 *
 * Role Controller manages Role by admin.
 *
 * @category   Role
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

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\DataTables\RoleDataTable;
use App\Models\Roles;
use App\Models\RoleAdmin;
use App\Models\Permissions;
use App\Models\PermissionRole;
use Illuminate\Support\Facades\Cache;
use Validator;
use DB;
use App\Http\Helpers\Common;

class RolesController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(RoleDataTable $dataTable)
    {
        return $dataTable->render('admin.roles.view');
    }

    public function add(Request $request)
    {
        if (! $request->isMethod('post')) {
            //$data['permissions'] = $this->helper->key_value('id', 'display_name', Permissions::getAll()->toArray());

            $data['permissions']  = DB::table('permissions')
                        ->select('id', 'display_name')
                        ->get();
                        //->get()->toArray();

          echo json_encode($data);

            //$data['permissions'] =  $this->helper->key_value('id', 'display_name', $data['permissions']::getAll()->toArray());
           
            
            return view('admin.roles.add', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'name'         => 'required|unique:roles|max:255',
                    'display_name' => 'required|max:255',
                    'description'  => 'required|max:255',
                    );

            $fieldNames = array(
                        'name'         => 'Name',
                        'display_name' => 'Display Name',
                        'description'  => 'Description',
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    $role = new Roles;

                    $role->name = $request->name;
                    $role->display_name = $request->display_name;
                    $role->description = $request->description;

                    $role->save();

                    foreach ($request->permission as $key => $value) {
                        PermissionRole::firstOrCreate(['permission_id' => $value, 'role_id' => $role->id]);
                    }
                }

                $this->helper->one_time_message('success', 'Added Successfully');

                return redirect('admin/settings/roles');
            }
        } else {
            return redirect('admin/settings/roles');
        }
    }


    public function update(Request $request)
    {
        if (! $request->isMethod('post')) {
            $data['result'] = Roles::find($request->id);
            $data['stored_permissions'] = Roles::permission_role($request->id)->toArray();
            $data['permissions'] = $this->helper->key_value('id', 'display_name', Permissions::getAll()->toArray());

            return view('admin.roles.edit', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'name'         => 'required|max:255|unique:roles,name,'.$request->id,
                    'display_name' => 'required|max:255',
                    'description'  => 'required|max:255'
                    );


            $fieldNames = array(
                        'name'         => 'Name',
                        'display_name' => 'Display Name',
                        'description'  => 'Description'
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                if (env('APP_MODE', '') != 'test') {
                    $role = Roles::find($request->id);
                    $role->name         = $request->name;
                    $role->display_name = $request->display_name;
                    $role->description  = $request->description;
                    $role->save();

                    $stored_permissions = Roles::permission_role($request->id);
                    foreach ($stored_permissions as $key => $value) {
                        if (! in_array($value, $request->permission)) {
                            PermissionRole::where(['permission_id' => $value, 'role_id' => $request->id])->delete();
                        }
                    }

                    foreach ($request->permission as $key => $value) {
                        PermissionRole::firstOrCreate(['permission_id' => $value, 'role_id' => $request->id]);
                    }
                }

                clearCache('.permissions');

                $this->helper->one_time_message('success', 'Updated Successfully');

                return redirect('admin/settings/roles');
            }
        } else {
            return redirect('admin/settings/roles');
        }
    }

    public function delete(Request $request)
    {
        if (env('APP_MODE', '') != 'test') {

            $role_count = RoleAdmin::where('role_id', $request->id)->count();

            if ($role_count > 0) {
                $this->helper->one_time_message('error', 'User belongs to this Role');
                return redirect('admin/settings/roles');
            }

            Roles::where('id', $request->id)->delete();
            Cache::forget(config('cache.prefix') . '.role_admin');
            PermissionRole::where('role_id', $request->id)->delete();
            $this->helper->one_time_message('success', 'Deleted Successfully');
        }
        return redirect('admin/settings/roles');
    }
}
