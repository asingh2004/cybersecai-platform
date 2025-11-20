@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <h2><strong>Files</strong></h2>

                        @php
                            $files = $files ?? collect();
                            $storage = $storage ?? '';
                            $risk = $risk ?? '';
                            $q = $q ?? '';
                            $sort = $sort ?? 'modified';
                            $dir = $dir ?? 'desc';
                            $storageLabels = $storageLabels ?? [
                                'aws_s3'     => 'AWS S3',
                                'smb'        => 'SMB',
                                'onedrive'   => 'OneDrive',
                                'sharepoint' => 'SharePoint',
                            ];
                            if (!function_exists('hbf')) {
                                function hbf($bytes) {
                                    $bytes = (int)$bytes;
                                    if ($bytes < 1024) return $bytes.' B';
                                    $units = ['KB','MB','GB','TB','PB'];
                                    $i = floor(log($bytes, 1024));
                                    return round($bytes / pow(1024, $i), 2).' '.$units[$i-1];
                                }
                            }
                        @endphp

                        <form method="get" class="d-flex gap-2 align-items-end mb-3">
                            <input type="hidden" name="risk" value="{{ $risk }}">
                            <input type="hidden" name="storage" value="{{ $storage }}">
                            <div class="flex-grow-1">
                                <label class="form-label">Search</label>
                                <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Search by name...">
                            </div>
                            <div>
                                <label class="form-label">Sort</label>
                                <select name="sort" class="form-select">
                                    <option value="modified" {{ $sort==='modified'?'selected':'' }}>Modified</option>
                                    <option value="name" {{ $sort==='name'?'selected':'' }}>Name</option>
                                    <option value="size" {{ $sort==='size'?'selected':'' }}>Size</option>
                                    <option value="risk" {{ $sort==='risk'?'selected':'' }}>Risk</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Dir</label>
                                <select name="dir" class="form-select">
                                    <option value="desc" {{ $dir==='desc'?'selected':'' }}>Desc</option>
                                    <option value="asc" {{ $dir==='asc'?'selected':'' }}>Asc</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label d-block">&nbsp;</label>
                                <button class="btn btn-primary"><i class="fa fa-search me-2"></i>Apply</button>
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
                                            <th>Modified</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($files as $f)
                                            <tr>
                                                <td class="text-truncate" style="max-width:360px;" title="{{ $f->file_name }}">{{ $f->file_name }}</td>
                                                <td>{{ $storageLabels[$f->storage_type] ?? $f->storage_type }}</td>
                                                <td>
                                                    @php $b = ['High'=>'danger','Medium'=>'warning','Low'=>'info','None'=>'success'][$f->risk] ?? 'secondary'; @endphp
                                                    <span class="badge bg-{{ $b }}">{{ $f->risk ?? 'None' }}</span>
                                                </td>
                                                <td class="text-end">{{ hbf($f->size_bytes) }}</td>
                                                <td>{{ $f->last_modified ? \Carbon\Carbon::parse($f->last_modified)->toDayDateTimeString() : '-' }}</td>
                                                <td class="text-end">
                                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('wizard.file.show', $f->id) }}">Details</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center p-4 text-muted">No matching files</td></tr>
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