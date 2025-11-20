@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">

            <h2><strong>Duplicates: Same Name & Size</strong></h2>
            <p class="mb-3">Groups of files with identical name and size. Apply filters to focus by risk, storage, dates or extension. Export results to CSV.</p>

            @php
              if (!function_exists('humanBytes')) {
                function humanBytes($bytes) {
                  $bytes = (int)$bytes;
                  if ($bytes < 1024) return $bytes.' B';
                  $units = ['KB','MB','GB','TB','PB'];
                  $i = floor(log($bytes, 1024));
                  return round($bytes/pow(1024,$i),2).' '.$units[$i-1];
                }
              }
              $storageLabels = $storageLabels ?? ['aws_s3'=>'AWS S3','smb'=>'SMB','onedrive'=>'OneDrive','sharepoint'=>'SharePoint'];
              $storages = $storages ?? [];
              $risk = $risk ?? '';
              $q = $q ?? '';
              $ext = $ext ?? '';
              $dateFrom = $dateFrom ?? '';
              $dateTo = $dateTo ?? '';
              $minCopies = $minCopies ?? 2;
              $minStorages = $minStorages ?? '';
              $sort = $sort ?? 'copies_desc';
              $perPage = $perPage ?? 50;
              $csvUrl = route('filesummary.duplicates.csv', request()->query());
            @endphp

            <div class="card shadow-sm mb-3">
              <div class="card-body">
                <form method="get" action="{{ route('filesummary.duplicates') }}" class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small mb-1">File name contains</label>
                    <input type="text" class="form-control" name="q" value="{{ e($q) }}" placeholder="e.g. report, invoice">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Extension</label>
                    <input type="text" class="form-control" name="ext" value="{{ e($ext) }}" placeholder="e.g. pdf">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">Risk</label>
                    <select class="form-select" name="risk">
                      <option value="">Any</option>
                      @foreach(['High','Medium','Low','None'] as $r)
                        <option value="{{ $r }}" {{ $risk===$r?'selected':'' }}>{{ $r }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">Storages</label>
                    <div class="d-flex flex-wrap gap-2">
                      @foreach($storageLabels as $k=>$lbl)
                        <div class="form-check me-2">
                          <input class="form-check-input" type="checkbox" name="storage[]" value="{{ $k }}" id="st_{{ $k }}" {{ in_array($k,$storages)?'checked':'' }}>
                          <label class="form-check-label" for="st_{{ $k }}">{{ $lbl }}</label>
                        </div>
                      @endforeach
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">From date</label>
                    <input type="date" class="form-control" name="date_from" value="{{ e($dateFrom) }}">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">To date</label>
                    <input type="date" class="form-control" name="date_to" value="{{ e($dateTo) }}">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Min copies</label>
                    <input type="number" min="2" class="form-control" name="min_copies" value="{{ e($minCopies) }}">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Min distinct storages</label>
                    <input type="number" min="1" class="form-control" name="min_storages" value="{{ e($minStorages) }}">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Sort</label>
                    <select class="form-select" name="sort">
                      <option value="copies_desc" {{ $sort==='copies_desc'?'selected':'' }}>Copies ↓</option>
                      <option value="storages_desc" {{ $sort==='storages_desc'?'selected':'' }}>Storages ↓</option>
                      <option value="size_desc" {{ $sort==='size_desc'?'selected':'' }}>Size ↓</option>
                      <option value="size_asc" {{ $sort==='size_asc'?'selected':'' }}>Size ↑</option>
                      <option value="name_asc" {{ $sort==='name_asc'?'selected':'' }}>Name A→Z</option>
                      <option value="name_desc" {{ $sort==='name_desc'?'selected':'' }}>Name Z→A</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Per page</label>
                    <select class="form-select" name="per_page">
                      @foreach([25,50,100,200] as $pp)
                        <option value="{{ $pp }}" {{ (int)$perPage===$pp?'selected':'' }}>{{ $pp }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-filter me-1"></i>Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('filesummary.duplicates') }}"><i class="fa fa-undo me-1"></i>Reset</a>
                    <a class="btn btn-outline-primary" href="{{ $csvUrl }}"><i class="fa fa-file-csv me-1"></i>Export CSV</a>
                  </div>
                </form>
              </div>
            </div>

            <div class="card shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
                  <div class="small text-muted">Showing {{ $groups->firstItem() ?? 0 }}–{{ $groups->lastItem() ?? 0 }} of {{ $groups->total() }} groups</div>
                  <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                    <input type="search" id="dupFilter" class="form-control" placeholder="Quick filter on page (name)…">
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0" id="dupTable">
                    <thead class="thead-light">
                      <tr>
                        <th>File name</th>
                        <th class="text-end">Size</th>
                        <th class="text-end">Copies</th>
                        <th class="text-end">Distinct storages</th>
                        <th class="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($groups as $g)
                        @php
                          $groupUrl = route('filesummary.duplicates.group', array_merge(
                            ['file_name'=>$g->file_name, 'size_bytes'=>$g->size_bytes],
                            request()->only(['storage','risk','date_from','date_to'])
                          ));
                        @endphp
                        <tr data-name="{{ strtolower($g->file_name) }}">
                          <td class="text-truncate" style="max-width:420px" title="{{ $g->file_name }}">{{ $g->file_name }}</td>
                          <td class="text-end">{{ humanBytes((int)$g->size_bytes) }}</td>
                          <td class="text-end"><span class="badge bg-primary">{{ (int)$g->cnt }}</span></td>
                          <td class="text-end"><span class="badge bg-dark">{{ (int)$g->storages }}</span></td>
                          <td class="text-center">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $groupUrl }}">View instances</a>
                          </td>
                        </tr>
                      @empty
                        <tr><td colspan="5" class="text-center p-4 text-muted">No duplicates match the filters.</td></tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <div class="mt-3">
                  {{ $groups->onEachSide(1)->links() }}
                </div>
              </div>
            </div>

            <script>
              (function(){
                const input = document.getElementById('dupFilter');
                const rows = () => Array.from(document.querySelectorAll('#dupTable tbody tr'));
                input?.addEventListener('input', function(){
                  const q = (this.value || '').trim().toLowerCase();
                  rows().forEach(tr => {
                    const name = tr.getAttribute('data-name') || '';
                    tr.style.display = q === '' || name.includes(q) ? '' : 'none';
                  });
                });
              })();
            </script>

            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection