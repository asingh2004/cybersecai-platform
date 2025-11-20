<?php

namespace App\DataTables;

use App\Models\User;
use Yajra\DataTables\Services\DataTable;
use Auth;

class CustomerDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($users) {

                $edit = \Helpers::has_permission(Auth::guard('admin')->user()->id, 'edit_customer') ?'<a href="' . url('admin/edit-customer/' . $users->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;' : '';

                return $edit;
            })
                ->addColumn('first_name', function ($users) {
                return '<a href="' . url('admin/edit-customer/' . $users->id) . '">' . ucfirst($users->first_name) . '</a>';
            })
                ->addColumn('last_name', function ($users) {
                return '<a href="' . url('admin/edit-customer/' . $users->id) . '">' . ucfirst($users->last_name) . '</a>';
            })
                ->addColumn('formatted_phone', function ($users) {
                if ($users->formatted_phone == '') return '-';
                return '<a href="' . url('admin/edit-customer/' . $users->id) . '">' .$users->formatted_phone .'</a>';
            })
                ->addColumn('created_at', function ($users) {
                return dateFormat($users->created_at);
            })
            ->rawColumns(['first_name', 'last_name', 'formatted_phone','action'])
            ->make(true);
    }

    public function query()
    {
        $status   = isset(request()->status) ? request()->status : null;
        $from     = isset(request()->from) ? setDateForDb(request()->from) : null;
        $to       = isset(request()->to) ? setDateForDb(request()->to) : null;
        $customer = isset(request()->customer) ? request()->customer : null;

        $query    = User::select();

        if (!empty($from)) {
             $query->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
             $query->whereDate('created_at', '<=', $to);
        }
        if (!empty($status)) {
            $query->where('status', '=', $status);
        }
        if (!empty($customer)) {
            $query->where('id', '=', $customer);
        }

        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'id', 'name' => 'id', 'title' => 'ID'])

            ->addColumn(['data' => 'first_name', 'name' => 'first_name', 'title' => 'First Name'])
            ->addColumn(['data' => 'last_name', 'name' => 'last_name', 'title' => 'Last Name'])
            ->addColumn(['data' => 'formatted_phone', 'name' => 'formatted_phone', 'title' => 'Phone'])
            ->addColumn(['data' => 'MAC_facilities', 'name' => 'MAC_facilities', 'title' => 'MAC Facility'])
            ->addColumn(['data' => 'subscription_status', 'name' => 'subscription_status', 'title' => 'Subscription'])
            ->addColumn(['data' => 'facilities_subscribed', 'name' => 'facilities_subscribed', 'title' => 'Sub Facs'])
            ->addColumn(['data' => 'email', 'name' => 'email', 'title' => 'Email'])
            ->addColumn(['data' => 'status', 'name' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false])
            ->addColumn(['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'])
            ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
             ->parameters(dataTableOptions());
    }


    protected function filename()
    {
        return 'customersdatatables_' . time();
    }
}
