<?php

namespace App\DataTables;

use App\Models\BedType;
use Yajra\DataTables\Services\DataTable;

class BedTypeDataTable extends DataTable
{
    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('action', function ($bedType) {

                $edit = '<a href="' . url('admin/settings/edit-bed-type/' . $bedType->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;';
                $delete = '<a href="' . url('admin/settings/delete-bed-type/' . $bedType->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';

                return $edit . ' ' . $delete;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function query()
    {
        $query = BedType::query();

        return $this->applyScopes($query);
    }

    public function html()
    {
        return $this->builder()
            ->addColumn(['data' => 'name', 'name' => 'bed_type.name', 'title' => 'Name'])
            ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
            ->parameters(dataTableOptions());
    }

    protected function getColumns()
    {
        return [
            'id',
            'created_at',
            'updated_at',
        ];
    }

    protected function filename()
    {
        return 'spacetypedatatables_' . time();
    }
}
