<?php

namespace App\DataTables;

use App\Models\Language;
use App\Models\Settings;
use Yajra\DataTables\Services\DataTable;

class LanguageDataTable extends DataTable
{

    protected $exportColumns = ['name', 'value', 'status'];

    public function ajax()
    {
        $language = $this->query();

        return datatables()
            ->of($language)
            ->addColumn('action', function ($language) {
                $defaultLanguage = Settings::getAll()->where('name', 'default_language')->first();
                $edit   = '<a href="' . url('admin/settings/edit-language/' . $language->id) . '" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-edit"></span>Edit</a>&nbsp;';
                if ($defaultLanguage->value != $language->id) {
                    $delete = '<a href="' . url('admin/settings/delete-language/' . $language->id) . '" class="btn btn-xs btn-danger delete-warning"><span class="glyphicon glyphicon-trash"></span>Delete</a>';
                } else {
                    $delete = '';
                }
                return $edit . $delete;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function query()
    {
        $language = Language::select();
        return $this->applyScopes($language);
    }

    public function html()
    {
        return $this->builder()
        ->columns([
            'name',
            'short_name',
            'status'
        ])
        ->addColumn(['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false])
        ->parameters(dataTableOptions());
    }
}
