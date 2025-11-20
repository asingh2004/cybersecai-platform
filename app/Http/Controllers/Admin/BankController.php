<?php

/**
 * Bank Controller
 *
 * Bank Controller manages bank payment methods in admin panel.
 *
 * @category   BankPayment
 * @package    OzzieAccom
 * @author     A Singh
 * @copyright  2022 Ozzieaccom
 * @license
 * @version    3.3
 * @link       Ozzieaccom.com
 * @email      info@myrespiteaccom.com.au
 * @since      Version 3.3
 * @deprecated None
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Common;
use App\Models\Bank;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    public function addBank(Request $request, Common $helper)
    {
        if (!$request->isMethod('post')) {
            return view('admin.banks.add', ['countries' => Country::getAll()->pluck('name', 'id')]);
        }

        $validate = Validator::make($request->all(),
            $this->getValidateRules(),
            $this->getValidationMessages()
        );

        $validate->setAttributeNames($this->getFieldNames());

        if ($validate->fails()) {
            return back()->withErrors($validate)->withInput();
        }

        $data = $validate->validate();
        $data["logo"] = $helper->uploadSingleFile($_FILES["logo"], 'public/images/bank/');

        Bank::create($data);

        return redirect('admin/settings/payment-methods');
    }

    public function editBank(Bank $bank, Request $request, Common $helper)
    {
        if (!$request->isMethod('POST')) {
            return view('admin.banks.edit', ['countries' => Country::getAll()->pluck('name', 'id'), 'bank' => $bank]);
        }

        $validate = Validator::make($request->all(),
            $this->getValidateRules($bank->id),
            $this->getValidationMessages()
        );

        $validate->setAttributeNames($this->getFieldNames());

        if ($validate->fails()) {
            return back()->withErrors($validate)->withInput();
        }

        if($request->hasFile('logo')) {
            $logo = $helper->uploadSingleFile($_FILES["logo"], 'public/images/bank/');
            $file = public_path('images\bank\\') . $bank->logo_name;
            if ($logo) {
                if($bank->logo_name && file_exists($file)) {
                    unlink($file);
                }
                $bank->logo = $logo;
            }
        }

        $bank->account_name = $request->account_name;
        $bank->iban = $request->iban;
        $bank->bank_name = $request->bank_name;
        $bank->swift_code = $request->swift_code;
        $bank->routing_no = $request->routing_no;
        $bank->branch_name = $request->branch_name;
        $bank->branch_city = $request->branch_city;
        $bank->branch_address = $request->branch_address;
        $bank->country = $request->country;
        $bank->status = $request->status;
        $bank->description = $request->description;
        $bank->save();
        return redirect('admin/settings/payment-methods');
    }

    public function show(Bank $bank)
    {
        return response()->json($bank);
    }

    public function deleteBank(Bank $bank)
    {
        $bank->delete();
        return redirect('admin/settings/payment-methods');
    }

    /**
     * @return array
     */
    private function getFieldNames(): array
    {
        return [
            'account_name' => trans('messages.account_preference.bank_holder'),
            'iban' => trans('messages.account_preference.bank_account_num'),
            'swift_code' => trans('messages.account_preference.swift_code'),
            'routing_no' => trans('messages.account_preference.routing_no'),
            'bank_name' => trans('messages.account_preference.bank_name'),
            'branch_name' => trans('messages.account_preference.branch_name'),
            'branch_city' => trans('messages.account_preference.branch_city'),
            'branch_address' => trans('messages.account_preference.branch_address'),
            'description' => trans('messages.photo_details.description'),
            'country' => trans('messages.account_preference.country'),
            'status' => trans('messages.account_preference.status')
        ];
    }

    /**
     * Regex validation rule
     * @return array
     */
    private function getValidationMessages(): array
    {
        return [
            'not_regex' => 'The :attribute must not contain special characters.'
        ];
    }

    /**
     * @param int | null $id
     * @return array
     */
    private function getValidateRules(int $id = null): array
    {
        $rules = [
            'account_name' => 'required|max:255',
            'iban' => ['required', 'unique:banks,iban', 'max:255', 'not_regex:/[!@#$%^&*\(\)=|\[\{\}\]\/+]/i'],
            'swift_code' => ['sometimes','max:255'],
            'routing_no' => ['sometimes', 'max:255'],
            'bank_name' => ['required', 'max:255', 'not_regex:/[!@#$%^&*\(\)=|\[\{\}\]\/+]/i'],
            'branch_name' => ['max:255'],
            'branch_city' => ['max:255'],
            'branch_address' => ['max:255'],
            'country' => 'required',
            'status' => 'required',
            'logo' => 'file|mimes:jpeg,bmp,png,jpg,JPG,JPEG|max:100'
        ];
        if ($id) {
            $rules['iban'] = ['required', 'unique:banks,iban,' . $id, 'max:255', 'not_regex:/[!@#$%^&*\(\)=|\[\{\}\]\/+]/i'];
        }
        return $rules;
    }
}
