@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Treemap: Storage by Size</strong></h2>
            <p class="mb-3">Area represents total storage used per provider. Hover for detail, click to list files below.</p>

            @php
              $storageAgg = $storageAgg ?? collect();
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3', 'smb' => 'SMB', 'onedrive' => 'OneDrive', 'sharepoint' => 'SharePoint',
              ];
              $children = [];
              foreach ($storageAgg as $row) {
                $children[] = [
                  'name' => $storageLabels[$row->storage_type] ?? $row->storage_type,
                  'key' => $row->storage_type,
                  'value' => (int)($row->total_size ?? 0) ?: (int)($row->total ?? 0),
                  'total' => (int)($row->total ?? 0),
                  'high' => (int)($row->high_count ?? 0),
                  'medium' => (int)($row->medium_count ?? 0),
                  'low' => (int)($row->low_count ?? 0),
                  'none' => (int)($row->none_count ?? 0),
                ];
              }
              $treeData = ['name' => 'root', 'children' => $children];
            @endphp

            @if(empty($children))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                <div id="treemap" style="width:100%; min-height:520px; position:relative;"></div>
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
            @if(!empty($children))
            <script>
              (function () {
                const data = {!! json_encode($treeData) !!};
                const container = document.getElementById('treemap');
                const width = container.clientWidth || 800;
                const height = 520;

                const root = d3.hierarchy(data).sum(d => d.value || 0);
                d3.treemap().size([width, height]).padding(4)(root);

                const svg = d3.select('#treemap').append('svg')
                  .attr('width', width)
                  .attr('height', height);

                const color = d3.scaleOrdinal(d3.schemeCategory10);

                const g = svg.selectAll('g')
                  .data(root.leaves())
                  .enter()
                  .append('g')
                  .attr('transform', d => `translate(${d.x0},${d.y0})`)
                  .style('cursor','pointer')
                  .on('click', (e, d) => { if (d.data.key) loadDrilldown({ storage: d.data.key }); });

                g.append('rect')
                  .attr('width', d => Math.max(0, d.x1 - d.x0))
                  .attr('height', d => Math.max(0, d.y1 - d.y0))
                  .attr('fill', d => color(d.data.key))
                  .append('title')
                  .text(d => `${d.data.name}
Files: ${d.data.total ?? 0}
High: ${d.data.high ?? 0}  Medium: ${d.data.medium ?? 0}  Low: ${d.data.low ?? 0}  None: ${d.data.none ?? 0}`);

                g.append('text')
                  .attr('x', 6).attr('y', 16)
                  .attr('fill', '#fff')
                  .style('font-size', '12px')
                  .text(d => d.data.name);

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