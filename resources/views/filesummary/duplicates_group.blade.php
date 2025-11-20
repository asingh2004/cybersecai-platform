@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">

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
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3',
                'smb' => 'SMB',
                'onedrive' => 'OneDrive',
                'sharepoint' => 'SharePoint',
              ];
              $storages = $storages ?? [];
              $risk = $risk ?? '';
              $dateFrom = $dateFrom ?? '';
              $dateTo = $dateTo ?? '';
              $p = $p ?? '';
              $sort = $sort ?? 'modified_desc';

              $byStorage = [];
              foreach ($files as $f) { $byStorage[$f->storage_type] = 1 + ($byStorage[$f->storage_type] ?? 0); }

              $csvUrl = route('filesummary.duplicates.group.csv', array_merge(
                ['file_name' => $name, 'size_bytes' => $size],
                request()->query()
              ));
            @endphp

            <div class="d-flex align-items-center mb-2">
              <a href="{{ route('filesummary.duplicates') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fa fa-arrow-left me-1"></i>Back
              </a>
              <h2 class="mb-0"><strong>Duplicate Group</strong></h2>
            </div>
            <div class="text-muted mb-3">
              <div>File name: <strong>{{ $name }}</strong></div>
              <div>Size: <strong>{{ humanBytes((int)$size) }}</strong></div>
            </div>

            <div class="card shadow-sm mb-3">
              <div class="card-body">
                <div class="d-flex flex-wrap align-items-center">
                  <div class="me-3 mb-2"><strong>Instances:</strong> {{ count($files) }}</div>
                  <div class="me-3 mb-2"><strong>Distinct storages:</strong> {{ count($byStorage) }}</div>
                  <div class="mb-2">
                    @foreach($byStorage as $k => $cnt)
                      <span class="badge bg-dark me-1">{{ $storageLabels[$k] ?? $k }}: {{ $cnt }}</span>
                    @endforeach
                  </div>
                </div>

                <form method="get" action="{{ route('filesummary.duplicates.group') }}" class="row g-3 mt-2">
                  <input type="hidden" name="file_name" value="{{ e($name) }}">
                  <input type="hidden" name="size_bytes" value="{{ e($size) }}">
                  <div class="col-md-3">
                    <label class="form-label small mb-1">Storages</label>
                    <div class="d-flex flex-wrap gap-2">
                      @foreach($storageLabels as $k=>$lbl)
                        <div class="form-check me-2">
                          <input class="form-check-input" type="checkbox" name="storage[]" value="{{ $k }}" id="st2_{{ $k }}" {{ in_array($k,$storages)?'checked':'' }}>
                          <label class="form-check-label" for="st2_{{ $k }}">{{ $lbl }}</label>
                        </div>
                      @endforeach
                    </div>
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
                    <label class="form-label small mb-1">From date</label>
                    <input type="date" class="form-control" name="date_from" value="{{ e($dateFrom) }}">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">To date</label>
                    <input type="date" class="form-control" name="date_to" value="{{ e($dateTo) }}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Path contains</label>
                    <input type="text" class="form-control" name="q" value="{{ e($p) }}" placeholder="folder, site or path substring">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">Sort</label>
                    <select class="form-select" name="sort">
                      <option value="modified_desc" {{ $sort==='modified_desc'?'selected':'' }}>Modified ↓</option>
                      <option value="modified_asc"  {{ $sort==='modified_asc'?'selected':'' }}>Modified ↑</option>
                      <option value="storage_asc"   {{ $sort==='storage_asc'?'selected':'' }}>Storage A→Z</option>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-filter me-1"></i>Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('filesummary.duplicates.group', ['file_name'=>$name, 'size_bytes'=>$size]) }}"><i class="fa fa-undo me-1"></i>Reset</a>
                    <a class="btn btn-outline-primary" href="{{ $csvUrl }}"><i class="fa fa-file-csv me-1"></i>Export CSV</a>
                  </div>
                </form>
              </div>
            </div>

            <div class="card shadow-sm">
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0" id="instTable">
                    <thead class="thead-light">
                      <tr>
                        <th>Storage</th>
                        <th>Location / Path</th>
                        <th>Risk</th>
                        <th>Modified</th>
                        <th class="text-center">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      @php $riskColor = ['High'=>'#dc3545','Medium'=>'#ffc107','Low'=>'#0dcaf0','None'=>'#198754']; @endphp
                      @forelse($files as $f)
                        @php
                          $lbl = $storageLabels[$f->storage_type] ?? $f->storage_type;
                          $riskVal = $f->risk ?? 'None';
                          $color = $riskColor[$riskVal] ?? '#6c757d';
                        @endphp
                        <tr>
                          <td>{{ $lbl }}</td>
                          <td class="text-truncate" style="max-width: 520px" title="{{ $f->location }}">{{ $f->location ?? '—' }}</td>
                          <td><span class="badge" style="background-color: {{ $color }}; color:#fff">{{ $riskVal }}</span></td>
                          <td>{{ $f->last_modified ? \Carbon\Carbon::parse($f->last_modified)->toDayDateTimeString() : '—' }}</td>
                          <td class="text-center">
                            <div class="btn-group btn-group-sm">
                              <a class="btn btn-outline-primary" href="{{ route('filesummary.file_detail', ['file' => $f->id]) }}" target="_blank">Details</a>
                              @if(!empty($f->web_url))
                                <a class="btn btn-outline-secondary" href="{{ $f->web_url }}" target="_blank" rel="noopener">Open</a>
                              @endif
                              @if(!empty($f->location))
                                <button type="button" class="btn btn-outline-dark" data-copy="{{ $f->location }}">Copy path</button>
                              @endif
                            </div>
                          </td>
                        </tr>
                      @empty
                        <tr><td colspan="5" class="text-center p-4 text-muted">No instances match these filters.</td></tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <script>
              (function(){
                // Copy to clipboard
                document.getElementById('instTable')?.addEventListener('click', function(e){
                  const btn = e.target.closest('button[data-copy]');
                  if (!btn) return;
                  const val = btn.getAttribute('data-copy');
                  navigator.clipboard?.writeText(val).then(() => {
                    btn.textContent = 'Copied';
                    setTimeout(()=> btn.textContent='Copy path', 1200);
                  }).catch(()=> alert('Copy failed'));
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