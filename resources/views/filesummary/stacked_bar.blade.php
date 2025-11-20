@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Stacked Bar: Risk by Storage</strong></h2>
            <p class="mb-3">Compare risks across storage providers. Hover for values, click bar segment to list files below.</p>

            @php
              $storageAgg = $storageAgg ?? collect();
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3', 'smb' => 'SMB', 'onedrive' => 'OneDrive', 'sharepoint' => 'SharePoint',
              ];
              $storages = [];
              $matrix = [];
              foreach ($storageAgg as $row) {
                $k = $row->storage_type;
                $storages[] = $k;
                $matrix[$k] = [
                  'High' => (int)($row->high_count ?? 0),
                  'Medium' => (int)($row->medium_count ?? 0),
                  'Low' => (int)($row->low_count ?? 0),
                  'None' => (int)($row->none_count ?? 0),
                ];
              }
              $storages = array_values(array_unique($storages));
            @endphp

            @if(empty($storages))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                @if(!empty($storages))
                  <canvas id="stacked" style="max-height:480px;"></canvas>
                @else
                  <div class="alert alert-light border mb-0">No storage data available.</div>
                @endif
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            @if(!empty($storages))
            <script>
              (function() {
                const storageKeys = {!! json_encode(array_values($storages)) !!};
                const storageLabels = {!! json_encode($storageLabels) !!};
                const matrix = {!! json_encode($matrix) !!};
                const risks = ['High','Medium','Low','None'];
                const color = { High:'#dc3545', Medium:'#ffc107', Low:'#0dcaf0', None:'#198754' };

                const datasets = risks.map(r => ({
                  label: r,
                  backgroundColor: color[r],
                  data: storageKeys.map(s => matrix[s]?.[r] ?? 0)
                }));

                const ctx = document.getElementById('stacked');
                const chart = new Chart(ctx, {
                  type: 'bar',
                  data: {
                    labels: storageKeys.map(s => storageLabels[s] ?? s),
                    datasets: datasets
                  },
                  options: {
                    responsive: true,
                    plugins: {
                      legend: { position: 'bottom' },
                      tooltip: {
                        callbacks: {
                          title: (items) => items[0]?.label || '',
                          label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue}`
                        }
                      }
                    },
                    onClick: (evt, els) => {
                      if (!els.length) return;
                      const {datasetIndex, index} = els[0];
                      const risk = datasets[datasetIndex].label;
                      const storageKey = storageKeys[index];
                      loadDrilldown({ storage: storageKey, risk });
                    },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
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