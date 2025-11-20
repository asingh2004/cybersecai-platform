@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Sunburst: Storage Hierarchies</strong></h2>
            <p class="mb-3">Concentric view by storage type. Hover for detail, click a segment to list files below.</p>

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
              $sunburstData = ['name' => 'root', 'children' => $children];
            @endphp

            @if(empty($children))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                <div id="sunburst" style="width:100%; min-height:520px; position:relative;"></div>
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
            @if(!empty($children))
            <script>
              (function () {
                const data = {!! json_encode($sunburstData) !!};
                const W = (document.getElementById('sunburst').clientWidth || 800);
                const radius = Math.min(W, 520) / 2;

                const partition = data => {
                  const root = d3.hierarchy(data)
                    .sum(d => d.value || 0)
                    .sort((a, b) => (b.value || 0) - (a.value || 0));
                  return d3.partition().size([2 * Math.PI, root.height + 1])(root);
                };

                const root = partition(data);
                root.each(d => d.current = d);

                const color = d3.scaleOrdinal(d3.schemeCategory10);
                const arc = d3.arc()
                  .startAngle(d => d.x0)
                  .endAngle(d => d.x1)
                  .padAngle(d => Math.min((d.x1 - d.x0) / 2, 0.005))
                  .padRadius(radius * 1.5)
                  .innerRadius(d => d.y0 * radius)
                  .outerRadius(d => Math.max(d.y0 * radius, d.y1 * radius - 1));

                const svg = d3.select('#sunburst').append('svg')
                  .attr('width', W)
                  .attr('height', 520)
                  .attr('viewBox', [-W / 2, -260, W, 520])
                  .style('font', '12px sans-serif');

                const g = svg.append('g');

                const path = g.selectAll('path')
                  .data(root.descendants().filter(d => d.depth))
                  .enter().append('path')
                  .attr('fill', d => color(d.data.key || d.data.name))
                  .attr('d', d => arc(d.current))
                  .style('cursor','pointer')
                  .on('click', (e, d) => { if (d.data.key) loadDrilldown({ storage: d.data.key }); });

                path.append('title').text(d => {
                  const z = d.data;
                  if (!z || !z.name) return '';
                  return `${z.name}
Files: ${z.total ?? 0}
High: ${z.high ?? 0}  Medium: ${z.medium ?? 0}  Low: ${z.low ?? 0}  None: ${z.none ?? 0}`;
                });

                g.append('g')
                  .attr('pointer-events', 'none')
                  .attr('text-anchor', 'middle')
                  .selectAll('text')
                  .data(root.descendants().filter(d => d.depth))
                  .enter().append('text')
                  .attr('transform', d => {
                    const x = (d.x0 + d.x1) / 2 * 180 / Math.PI;
                    const y = (d.y0 + d.y1) / 2 * radius;
                    return `rotate(${x - 90}) translate(${y},0) rotate(${x < 180 ? 0 : 180})`;
                  })
                  .attr('dy', '0.35em')
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