<?php

/**
 * Banners Controller
 *
 * Banners Controller manages banners in home page.
 *
 * @category   Banners
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
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\DataTables\BannersDataTable;
use App\Models\Banners;
use App\Http\Helpers\Common;
use Validator;

class BannersController extends Controller
{
    protected $helper;  

    public function __construct()
    {
        $this->helper = new Common;
    }

    public function index(BannersDataTable $dataTable)
    {
        return $dataTable->render('admin.banners.view');
    }

    public function add(Request $request)
    {
        if (! $request->isMethod('post')) {
            return view('admin.banners.add');
        } elseif ($request->isMethod('post')) {
            $rules = array(
                'heading'    => 'required|max:100',
                'image'      => 'required|dimensions:min_width=1920,min_height=860'
            );

            
            $fieldNames = array(
                'heading'    => 'Heading',
                'image'      => 'Image'
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $image     =   $request->file('image');
                $extension =   $image->getClientOriginalExtension();
                $filename  =   'banner_'.time() . '.' . $extension;

                $success   = $image->move('public/front/images/banners', $filename);
                
                if (!isset($success)) {
                    return back()->withError('Could not upload Image');
                }

                $banners = new Banners;

                $banners->heading  = $request->heading;
                $banners->image    = $filename;
                $banners->status   = $request->status;
                if (isset($request->subheading)) {
                    $banners->subheading = $request->subheading;
                }
                $banners->save();

                $this->helper->one_time_message('success', 'Added Successfully');
                return redirect('admin/settings/banners');
            }
        } else {
            return redirect('admin/settings/banners');
        }
    }
    public function update(Request $request)
    {
        if (! $request->isMethod('post')) {
            $data['result'] = Banners::find($request->id);

            return view('admin.banners.edit', $data);
        } elseif ($request->isMethod('post')) {
            $rules = array(
                    'heading'    => 'required|max:100',
                    'image'      => 'dimensions:min_width=1920,min_height=860'

                    );

            $fieldNames = array(
                        'heading'    => 'Heading',
                        'image'      => 'dimensions:min_width=1920,min_height=860'
                        );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                $banners = Banners::find($request->id);

                $banners->heading  = $request->heading;
                $banners->status   = $request->status;
                if (isset($request->subheading)) {
                    $banners->subheading = $request->subheading;
                }
                $image     =   $request->file('image');

                if ($image) {
                    $extension =   $image->getClientOriginalExtension();
                    $filename  =   'banner_'.time() . '.' . $extension;
    
                    $success = $image->move('public/front/images/banners', $filename);
        
                    if (! isset($success)) {
                         return back()->withError('Could not upload Image');
                    }

                    $banners->image = $filename;
                }

                $banners->save();

                $this->helper->one_time_message('success', 'Updated Successfully');
                return redirect('admin/settings/banners');
            }
        } else {
            return redirect('admin/settings/banners');
        }
    }
    public function delete(Request $request)
    {
        if (env('APP_MODE', '') != 'test') {
            $banners   = Banners::find($request->id);
            $file_path = public_path().'/front/images/banners/'.$banners->image;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            Banners::find($request->id)->delete();
            $this->helper->one_time_message('success', 'Deleted Successfully');
        }
        
        return redirect('admin/settings/banners');
    }
}
