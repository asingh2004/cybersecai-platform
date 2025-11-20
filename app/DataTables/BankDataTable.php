<?php

/**
 * Bank Data Table
 *
 * Bank Data Table handles Bank datas.
 *
 * @category   BankDataTable
 * @package    OzzieAccom
 * @author     Abhishek Singh
 * @copyright  OzzieAccom 2022
 * @license
 * @version    3.3
 * @link       Ozzieaccom.com
 * @since      Version 3.3
 * @deprecated None
 */

namespace App\DataTables;

use App\Models\Bank;
use Yajra\DataTables\Services\DataTable;

class BankDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('iban', function ($bank) {
                return '*****'. substr($bank->iban,-7);
            })
            ->addColumn('status', function ($bank) {
                $status = $bank->status == 'Active' ? 'success' : 'danger' ;
                return $bank->status;
            })
            ->addColumn('action', function ($bank) {

                $edit   = '<button data-url="' . url('admin/settings/bank/' . $bank->id) . '" data-edit="'.url('admin/settings/bank/edit/' . $bank->id).'" class="btn btn-xs btn-primary edit_bank"><span class="glyphicon glyphicon-edit edit-icon"></span>Edit</button>&nbsp;';
                $delete = '<a href="' . url('admin/settings/bank/delete/' . $bank->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';

                return $edit . ' ' . $delete;
            })
            ->rawColumns(['action', 'status'])
            ->make(true);
    }

    public function query()
    {
        $query = Bank::query();

        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'account_name', 'name' =>'banks.account_name', 'title' => 'Name'])
            ->addColumn(['data' => 'iban', 'name' =>'banks.iban', 'title' => 'Iban'])
            ->addColumn(['data' => 'bank_name', 'name' =>'banks.bank_name', 'title' => 'Bank'])
            ->addColumn(['data' => 'status', 'name' =>'banks.status', 'title' => 'Status'])
            ->addColumn(['data' => 'action', 'name' =>'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
            ->parameters(dataTableOptions());
    }


    protected function filename()
    {
        return 'banksdatatables_' . time();
    }
}
