<?php

namespace App\DataTables;

use App\Models\Page;
use Yajra\DataTables\Services\DataTable;

class PagesDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($pages) {

                $edit = '<a href="' . url('admin/edit-page/' . $pages->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;';
                $delete = '<a href="' . url('admin/delete-page/' . $pages->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';

                return $edit . ' ' . $delete;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function query()
    {
        $query = Page::select();
        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'name',   'name' => 'pages.name',   'title' => 'Name'])
            ->addColumn(['data' => 'url',    'name' => 'pages.url',    'title' => 'Url'])
            ->addColumn(['data' => 'status', 'name' => 'pages.status', 'title' => 'Status'])
            ->addColumn(['data' => 'action', 'name' => 'action',       'title' => 'Action', 'orderable' => false, 'searchable' => false])
             ->parameters(dataTableOptions());
    }


    protected function filename()
    {
        return 'membersdatatables_' . time();
    }
}
