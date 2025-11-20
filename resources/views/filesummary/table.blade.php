@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2><strong>Explore Table View</strong></h2>

                        @php
                            $storageLabels = $storageLabels ?? [
                                'aws_s3'     => 'AWS S3',
                                'smb'        => 'SMB',
                                'onedrive'   => 'OneDrive',
                                'sharepoint' => 'SharePoint',
                            ];
                            $storage = $storage ?? '';
                            $risk = $risk ?? '';
                            $q = $q ?? '';
                            $ext = $ext ?? '';
                            $sort = $sort ?? 'modified';
                            $dir = $dir ?? 'desc';
                            $perPage = $perPage ?? 50;
                            $files = $files ?? collect();
                            if (!function_exists('hb')) {
                                function hb($bytes) {
                                    $bytes = (int)$bytes;
                                    if ($bytes < 1024) return $bytes.' B';
                                    $units = ['KB','MB','GB','TB','PB'];
                                    $i = floor(log($bytes, 1024));
                                    return round($bytes / pow(1024, $i), 2).' '.$units[$i-1];
                                }
                            }
                        @endphp

                        <form method="get" class="card card-body mb-3 shadow-sm">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">Storage</label>
                                    <select name="storage" class="form-select">
                                        <option value="">All</option>
                                        @foreach($storageLabels as $k => $v)
                                            <option value="{{ $k }}" {{ $storage===$k?'selected':'' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Risk</label>
                                    <select name="risk" class="form-select">
                                        <option value="">All</option>
                                        @foreach(['High','Medium','Low','None'] as $r)
                                            <option value="{{ $r }}" {{ $risk===$r?'selected':'' }}>{{ $r }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Search name</label>
                                    <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="File name...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Extension</label>
                                    <input type="text" name="ext" class="form-control" value="{{ $ext }}" placeholder=".pdf or pdf">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Sort by</label>
                                    <select name="sort" class="form-select">
                                        <option value="modified" {{ $sort==='modified'?'selected':'' }}>Last Modified</option>
                                        <option value="name" {{ $sort==='name'?'selected':'' }}>Name</option>
                                        <option value="size" {{ $sort==='size'?'selected':'' }}>Size</option>
                                        <option value="risk" {{ $sort==='risk'?'selected':'' }}>Risk</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Direction</label>
                                    <select name="dir" class="form-select">
                                        <option value="desc" {{ $dir==='desc'?'selected':'' }}>Desc</option>
                                        <option value="asc" {{ $dir==='asc'?'selected':'' }}>Asc</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Per Page</label>
                                    <select name="per_page" class="form-select">
                                        @foreach([25,50,100,200] as $pp)
                                            <option value="{{ $pp }}" {{ (int)$perPage===$pp?'selected':'' }}>{{ $pp }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-success w-100"><i class="fa fa-search me-2"></i>Apply</button>
                                </div>
                            </div>
                        </form>

                        <div class="card shadow-sm">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Storage</th>
                                            <th>Risk</th>
                                            <th class="text-end">Size</th>
                                            <th>Last Modified</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($files as $f)
                                            <tr>
                                                <td class="text-truncate" style="max-width: 360px;" title="{{ $f->file_name }}">{{ $f->file_name }}</td>
                                                <td>{{ $storageLabels[$f->storage_type] ?? $f->storage_type }}</td>
                                                <td>
                                                    @php $badge = ['High'=>'danger','Medium'=>'warning','Low'=>'info','None'=>'success'][$f->risk] ?? 'secondary'; @endphp
                                                    <span class="badge bg-{{ $badge }}">{{ $f->risk ?? 'None' }}</span>
                                                </td>
                                                <td class="text-end">{{ hb($f->size_bytes) }}</td>
                                                <td>{{ $f->last_modified ? \Carbon\Carbon::parse($f->last_modified)->toDayDateTimeString() : '-' }}</td>
                                                <td class="text-end">
                                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('wizard.file.show', $f->id) }}">
                                                        Details
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center p-4 text-muted">No files found</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-body">
                                @if(method_exists($files,'links'))
                                    {{ $files->links() }}
                                @endif
                            </div>
                        </div>

                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection