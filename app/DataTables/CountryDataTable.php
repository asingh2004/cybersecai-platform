<?php

namespace App\DataTables;

use App\Models\Country;
use Yajra\DataTables\Services\DataTable;

class CountryDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($country) {

                $edit = '<a href="' . url('admin/settings/edit-country/' . $country->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;';
                $delete = '<a href="' . url('admin/settings/delete-country/' . $country->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';

                return $edit . ' ' . $delete;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function query()
    {
        $query = Country::query();

        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'short_name', 'name' => 'country.short_name', 'title' => 'Short Name'])
            ->addColumn(['data' => 'name', 'name' => 'country.name', 'title' => 'Long Name'])
            ->addColumn(['data' => 'iso3', 'name' => 'country.iso3', 'title' => 'Iso3'])
            ->addColumn(['data' => 'number_code', 'name' => 'country.number_code', 'title' => 'Num Code'])
            ->addColumn(['data' => 'phone_code', 'name' => 'country.phone_code', 'title' => 'Phone Code'])
            ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
            ->parameters(dataTableOptions());
    }


    protected function filename()
    {
        return 'countrydatatables_' . time();
    }
}
