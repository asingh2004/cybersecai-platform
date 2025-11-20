@php
  if (!function_exists('humanBytes')) {
    function humanBytes($bytes) {
      $units = ['B','KB','MB','GB','TB','PB'];
      $i = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
      $i = min($i, count($units)-1);
      return $bytes > 0 ? round($bytes / pow(1024, $i), 2).' '.$units[$i] : '0 B';
    }
  }
  $storageLabels = $storageLabels ?? ['aws_s3'=>'AWS S3','smb'=>'SMB','onedrive'=>'OneDrive','sharepoint'=>'SharePoint'];
@endphp

<div class="card mt-4 shadow-sm">
  <div class="card-header">
    <strong>Drilldown results</strong>
    <span class="text-muted small ml-2">(showing {{ $files->firstItem() ?? 0 }}–{{ $files->lastItem() ?? 0 }} of {{ $files->total() }})</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>Name</th>
            <th>Storage</th>
            <th>Location / Folder / Site</th>
            <th>Owner</th>
            <th>Ext</th>
            <th>Size</th>
            <th>Modified</th>
            <th>Risk</th>
            <th>Perms</th>
            <th>Open</th>
          </tr>
        </thead>
        <tbody>
          @forelse($files as $f)
            <tr>
              <td>
                <a href="{{ url('/filesummary/file/'.$f->id) }}" target="_blank" rel="noopener">
                  {{ $f->file_name }}
                </a>
              </td>
              <td>{{ $storageLabels[$f->storage_type] ?? $f->storage_type }}</td>
              <td>
                @if($f->storage_type === 'sharepoint' && $f->site_id)
                  <div class="small text-muted">Site: {{ $f->site_id }}</div>
                @endif
                <div class="text-truncate" style="max-width: 360px" title="{{ $f->location }}">{{ $f->location }}</div>
              </td>
              <td>
                @if($f->owner_name || $f->owner_email)
                  <div>{{ $f->owner_name }}</div>
                  <div class="small text-muted">{{ $f->owner_email }}</div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ $f->file_extension ?: '—' }}</td>
              <td>{{ humanBytes((int)$f->size_bytes) }}</td>
              <td>{{ $f->last_modified ? \Carbon\Carbon::parse($f->last_modified)->toDayDateTimeString() : '—' }}</td>
              <td>
                @php $color = ['High'=>'#dc3545','Medium'=>'#ffc107','Low'=>'#0dcaf0','None'=>'#198754'][$f->risk] ?? '#6c757d'; @endphp
                <span class="badge" style="background-color: {{ $color }}; color:#fff">{{ $f->risk }}</span>
              </td>
              <td>{{ (int)$f->perm_count }}</td>
              <td>
                @if($f->web_url)
                  <a href="{{ $f->web_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center p-4 text-muted">No files match this selection.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer py-2">
    {{-- Intercepted by JS to stay inline --}}
    {{ $files->onEachSide(1)->links() }}
  </div>
</div>