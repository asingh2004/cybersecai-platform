@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Bubble Chart: Storage Overview</strong></h2>
            <p class="mb-3">2D view with bubble size as total storage used. X: Avg size per file (MB), Y: Total files. Hover for detail, click to list files below.</p>

            @php
              $storageAgg = $storageAgg ?? collect();
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3', 'smb' => 'SMB', 'onedrive' => 'OneDrive', 'sharepoint' => 'SharePoint',
              ];
              $points = [];
              $maxSize = 0;
              foreach ($storageAgg as $row) {
                $total = (int)($row->total ?? 0);
                $sizeBytes = (int)($row->total_size ?? 0);
                $avgMB = $total > 0 ? ($sizeBytes / $total) / (1024*1024) : 0;
                $maxSize = max($maxSize, $sizeBytes);
                $points[] = [
                  'key' => $row->storage_type,
                  'label' => $storageLabels[$row->storage_type] ?? $row->storage_type,
                  'x' => round($avgMB, 2),
                  'y' => $total,
                  'sizeBytes' => $sizeBytes,
                  'risks' => [
                    'High' => (int)($row->high_count ?? 0),
                    'Medium' => (int)($row->medium_count ?? 0),
                    'Low' => (int)($row->low_count ?? 0),
                    'None' => (int)($row->none_count ?? 0),
                  ],
                ];
              }
            @endphp

            @if(empty($points))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                @if(!empty($points))
                  <canvas id="bubble" style="max-height:520px;"></canvas>
                @else
                  <div class="alert alert-light border mb-0">No storage data available.</div>
                @endif
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            @if(!empty($points))
            <script>
              (function() {
                const raw = {!! json_encode($points) !!};
                const maxSize = Math.max(...raw.map(d => d.sizeBytes), 1);
                const rScale = s => 10 + 30 * Math.sqrt(s / maxSize);

                const datasets = raw.map(d => ({
                  label: d.label,
                  data: [{ x: d.x, y: d.y, r: rScale(d.sizeBytes) }],
                }));

                const ctx = document.getElementById('bubble');
                const chart = new Chart(ctx, {
                  type: 'bubble',
                  data: { datasets },
                  options: {
                    responsive: true,
                    plugins: {
                      legend: { position: 'bottom' },
                      tooltip: {
                        callbacks: {
                          beforeTitle: ctx => raw[ctx[0].datasetIndex]?.label || '',
                          label: ctx => {
                            const d = raw[ctx.datasetIndex];
                            const bytes = d.sizeBytes;
                            const units = ['B','KB','MB','GB','TB'];
                            let i = bytes ? Math.floor(Math.log(bytes)/Math.log(1024)) : 0; i = Math.min(i, units.length-1);
                            const human = bytes ? (bytes/Math.pow(1024,i)).toFixed(2)+' '+units[i] : '0 B';
                            return [
                              `Avg size: ${d.x} MB`,
                              `Total files: ${d.y}`,
                              `Total size: ${human}`,
                              `High: ${d.risks.High}  Medium: ${d.risks.Medium}  Low: ${d.risks.Low}  None: ${d.risks.None}`
                            ];
                          }
                        }
                      }
                    },
                    onClick: (evt, els) => {
                      if (!els.length) return;
                      const idx = els[0].datasetIndex;
                      const storageKey = raw[idx].key;
                      loadDrilldown({ storage: storageKey });
                    },
                    scales: {
                      x: { title: { display: true, text: 'Avg Size per File (MB)' } },
                      y: { title: { display: true, text: 'Total Files' }, beginAtZero: true, ticks: { precision: 0 } }
                    }
                  }
                });

                function loadDrilldown(params) {
                  const base = "{{ route('filesummary.files_list_partial') }}";
                  const q = new URLSearchParams(params).toString();
                  const url = base + (q ? ('?'+q) : '');
                  const target = document.getElementById('drilldown');
                  target.innerHTML = '<div class="text-center p-4 text-muted">Loading…</div>';
                  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                    .then(r => r.text())
                    .then(html => { target.innerHTML = html; hookPagination(); })
                    .catch(() => { target.innerHTML = '<div class="text-danger p-3">Failed to load.</div>'; });
                }

                function hookPagination() {
                  const el = document.getElementById('drilldown');
                  el.querySelectorAll('a').forEach(a => {
                    if (a.getAttribute('href')) {
                      a.addEventListener('click', function(e) {
                        if (this.closest('.pagination')) {
                          e.preventDefault();
                          const target = document.getElementById('drilldown');
                          target.innerHTML = '<div class="text-center p-4 text-muted">Loading…</div>';
                          fetch(this.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                            .then(r => r.text()).then(html => { target.innerHTML = html; hookPagination(); });
                        }
                      });
                    }
                  });
                }
              })();
            </script>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection