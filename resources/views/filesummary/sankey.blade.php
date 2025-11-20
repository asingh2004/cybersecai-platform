@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Sankey: Storage to Risk Flows</strong></h2>
            <p class="mb-3">Hover links/nodes for values, click a node or link to list files below.</p>

            @php
              $storageAgg = $storageAgg ?? collect();
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3', 'smb' => 'SMB', 'onedrive' => 'OneDrive', 'sharepoint' => 'SharePoint',
              ];
              $riskOrder = ['High','Medium','Low','None'];
              $nodes = []; $nodeIndex = [];
              $links = [];

              foreach ($storageAgg as $row) {
                $k = $row->storage_type;
                if (!isset($nodeIndex[$k])) {
                  $nodeIndex[$k] = count($nodes);
                  $nodes[] = ['name' => $storageLabels[$k] ?? $k, 'key' => $k, 'type' => 'storage'];
                }
              }
              foreach ($riskOrder as $r) {
                $rk = "risk_".$r;
                if (!isset($nodeIndex[$rk])) {
                  $nodeIndex[$rk] = count($nodes);
                  $nodes[] = ['name' => $r, 'key' => $rk, 'type' => 'risk'];
                }
              }
              foreach ($storageAgg as $row) {
                $src = $nodeIndex[$row->storage_type] ?? null; if ($src === null) continue;
                $map = [
                  'High' => (int)($row->high_count ?? 0),
                  'Medium' => (int)($row->medium_count ?? 0),
                  'Low' => (int)($row->low_count ?? 0),
                  'None' => (int)($row->none_count ?? 0),
                ];
                foreach ($map as $risk => $val) {
                  if ($val <= 0) continue;
                  $tgt = $nodeIndex["risk_".$risk] ?? null;
                  if ($tgt === null) continue;
                  $links[] = ['source' => $src, 'target' => $tgt, 'value' => $val, 'storage' => $row->storage_type, 'risk' => $risk];
                }
              }
            @endphp

            @if(empty($links))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                <div id="sankey" style="width:100%; min-height:520px; position:relative;"></div>
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/d3-sankey@0.12.3/dist/d3-sankey.min.js"></script>
            @if(!empty($links))
            <script>
              (function() {
                const nodes = {!! json_encode($nodes) !!};
                const links = {!! json_encode($links) !!};

                const margin = {top: 10, right: 10, bottom: 10, left: 10};
                const W = (document.getElementById('sankey').clientWidth || 900);
                const width = W - margin.left - margin.right;
                const height = 520 - margin.top - margin.bottom;

                const svg = d3.select('#sankey').append('svg')
                  .attr('width', width + margin.left + margin.right)
                  .attr('height', height + margin.top + margin.bottom)
                  .append('g')
                  .attr('transform', `translate(${margin.left},${margin.top})`);

                const sankey = d3.sankey().nodeWidth(20).nodePadding(16).extent([[1, 1], [width - 1, height - 6]]);
                const graph = { nodes: nodes.map(d => Object.assign({}, d)), links: links.map(d => Object.assign({}, d)) };
                sankey(graph);

                const color = d3.scaleOrdinal(d3.schemeCategory10);

                const link = svg.append('g').attr('fill', 'none')
                  .selectAll('path').data(graph.links).enter().append('path')
                  .attr('d', d3.sankeyLinkHorizontal())
                  .attr('stroke', d => color(d.source.name))
                  .attr('stroke-width', d => Math.max(1, d.width))
                  .attr('opacity', 0.6)
                  .style('cursor','pointer')
                  .on('click', (e, d) => loadDrilldown({ storage: d.storage, risk: d.target.name }));

                link.append('title')
                  .text(d => `${d.source.name} → ${d.target.name}\n${d.value.toLocaleString()}`);

                const node = svg.append('g').selectAll('g').data(graph.nodes).enter().append('g')
                  .style('cursor','pointer')
                  .on('click', (e, d) => {
                    if (d.type === 'storage') loadDrilldown({ storage: d.key });
                    if (d.type === 'risk')    loadDrilldown({ risk: d.name });
                  });

                node.append('rect')
                  .attr('x', d => d.x0).attr('y', d => d.y0)
                  .attr('height', d => Math.max(1, d.y1 - d.y0)).attr('width', d => Math.max(1, d.x1 - d.x0))
                  .attr('fill', d => color(d.name)).attr('stroke', '#000');

                node.append('title').text(d => `${d.name}\n${(d.value || 0).toLocaleString()}`);

                node.append('text')
                  .attr('x', d => d.x0 - 6)
                  .attr('y', d => (d.y1 + d.y0) / 2)
                  .attr('dy', '0.35em')
                  .attr('text-anchor', 'end')
                  .text(d => d.name)
                  .filter(d => d.x0 < width / 2)
                  .attr('x', d => d.x1 + 6)
                  .attr('text-anchor', 'start');

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