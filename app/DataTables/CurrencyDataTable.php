<?php

namespace App\DataTables;

use App\Models\Currency;
use App\Models\Settings;
use Yajra\DataTables\Services\DataTable;

class CurrencyDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($currency) {
                $homeCurrency = Settings::getAll()->where('name', 'default_currency')->first();
                $edit = '<a href="' . url('admin/settings/edit-currency/' . $currency->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;';
                if ($currency->id != $homeCurrency->value) {
                    $delete = '<a href="' . url('admin/settings/delete-currency/' . $currency->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';
                } else {
                    $delete = '';
                }

                return $edit . ' ' . $delete;
            })
            ->addColumn('org_symbol', function ($transaction) {
                return  $transaction->org_symbol ;

            })

            ->rawColumns(['action', 'org_symbol'])
            ->make(true);
    }

    public function query()
    {
        $query = Currency::query();
        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'name', 'name' => 'currency.name', 'title' => 'Name'])
            ->addColumn(['data' => 'code', 'name' => 'currency.code', 'title' => 'Code'])
            ->addColumn(['data' => 'org_symbol', 'name' => 'currency.symbol', 'title' => 'Symbol'])
            ->addColumn(['data' => 'rate', 'name' => 'currency.rate', 'title' => 'Rate'])
            ->addColumn(['data' => 'status', 'name' => 'currency.status', 'title' => 'Status'])
            ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
            ->parameters(dataTableOptions());
    }


    protected function filename()
    {
        return 'currencydatatables_' . time();
    }
}
