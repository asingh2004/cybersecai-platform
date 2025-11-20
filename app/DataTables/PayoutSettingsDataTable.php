<?php


namespace App\DataTables;


use App\Models\User;

use App\Models\PayoutSetting;

use Yajra\DataTables\Services\DataTable;

use Auth;


class PayoutSettingsDataTable extends DataTable

{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($payoutSetting) {
                $edit = $delete = '';
                $edit = '<a href="' . url('users/payout/edit-payout/' . $payoutSetting->id) . '" class=""><i class="fa fa-edit"></i></a>&nbsp;';
                $delete = '<a href="' . url('users/payout/delete-payout/' . $payoutSetting->id) . '" class="delete-warning"><i style="color:red" class="fa fa-trash"></i></a>';
                return $edit . $delete;
            })
            ->addColumn('type', function ($payoutSetting) {
                if ($payoutSetting->type == '4') {
                    return 'Bank';
                } else {
                    return 'PayPal';
                }
            })
            ->rawColumns(['action'])
            ->make(true);
    }


    public function query()
    {
        $query = PayoutSetting::where(['user_id' => Auth::user()->id]);
        return $this->applyScopes($query);
    }


    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'id', 'name' => 'id', 'title' => 'ID', 'visible' => false])
            ->addColumn(['data' => 'type', 'name' => 'type', 'title' => 'Payout Type'])
            ->addColumn(['data' => 'account_name', 'name' => 'account_name', 'title' => 'Account'])
            ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
            ->parameters(dataTableOptions());
    }

    protected function filename()
    {
        return 'payoutsettingsdatatables_' . time();
    }
}

