@extends('template')

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <h2><strong>Heatmap: Risk vs Storage</strong></h2>
            <p class="mb-3">Identify hotspots across storage and risk categories. Hover for details, click to list files below.</p>

            @php
              $storageAgg = $storageAgg ?? collect();
              $storageLabels = $storageLabels ?? [
                'aws_s3' => 'AWS S3', 'smb' => 'SMB', 'onedrive' => 'OneDrive', 'sharepoint' => 'SharePoint',
              ];
              $storages = [];
              $cells = [];
              foreach ($storageAgg as $row) {
                $k = $row->storage_type;
                $storages[] = $k;
                $cells[] = ['storage'=>$k, 'risk'=>'High', 'value'=>(int)($row->high_count ?? 0)];
                $cells[] = ['storage'=>$k, 'risk'=>'Medium', 'value'=>(int)($row->medium_count ?? 0)];
                $cells[] = ['storage'=>$k, 'risk'=>'Low', 'value'=>(int)($row->low_count ?? 0)];
                $cells[] = ['storage'=>$k, 'risk'=>'None', 'value'=>(int)($row->none_count ?? 0)];
              }
              $storages = array_values(array_unique($storages));
              $risks = ['High','Medium','Low','None'];
            @endphp

            @if(empty($storages))
              <div class="alert alert-info">No data to display.</div>
            @endif

            <div class="card shadow-sm">
              <div class="card-body">
                <div id="heatmap" style="width:100%; min-height:520px; position:relative;"></div>
                <div class="small text-muted mt-2">Darker cells indicate higher counts.</div>
              </div>
            </div>

            <div id="drilldown" class="mt-4"></div>

            <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
            @if(!empty($storages))
            <script>
              (function() {
                const cells = {!! json_encode($cells) !!};
                const storages = {!! json_encode($storages) !!};
                const risks = {!! json_encode($risks) !!};
                const storageLabels = {!! json_encode($storageLabels) !!};

                const margin = {top: 30, right: 10, bottom: 80, left: 90};
                const W = document.getElementById('heatmap').clientWidth || 800;
                const width = W - margin.left - margin.right;
                const height = 520 - margin.top - margin.bottom;

                const svg = d3.select('#heatmap').append('svg')
                  .attr('width', width + margin.left + margin.right)
                  .attr('height', height + margin.top + margin.bottom)
                  .append('g')
                  .attr('transform', `translate(${margin.left},${margin.top})`);

                const x = d3.scaleBand().range([0, width]).domain(storages).padding(0.05);
                const y = d3.scaleBand().range([0, height]).domain(risks).padding(0.05);

                const maxVal = d3.max(cells, d => d.value) || 1;
                const color = d3.scaleSequential(d3.interpolateYlOrRd).domain([0, maxVal]);

                svg.append('g')
                  .attr('transform', `translate(0, ${height})`)
                  .call(d3.axisBottom(x).tickFormat(k => storageLabels[k] ?? k))
                  .selectAll('text')
                  .attr('transform', 'rotate(30)')
                  .style('text-anchor', 'start');

                svg.append('g').call(d3.axisLeft(y));

                const tooltip = d3.select('#heatmap').append('div')
                  .style('position','absolute')
                  .style('pointer-events','none')
                  .style('background','#000')
                  .style('color','#fff')
                  .style('padding','6px 8px')
                  .style('border-radius','4px')
                  .style('font-size','12px')
                  .style('opacity',0);

                svg.selectAll()
                  .data(cells, d => d.storage + ':' + d.risk)
                  .enter()
                  .append('rect')
                  .attr('x', d => x(d.storage))
                  .attr('y', d => y(d.risk))
                  .attr('width', x.bandwidth())
                  .attr('height', y.bandwidth())
                  .style('fill', d => color(d.value))
                  .style('cursor', 'pointer')
                  .on('mousemove', function(event, d){
                    tooltip.style('opacity',1)
                      .html(`<strong>${storageLabels[d.storage] ?? d.storage}</strong><br/>Risk: ${d.risk}<br/>Files: ${d.value}`)
                      .style('left', (event.offsetX + 15)+'px')
                      .style('top', (event.offsetY - 10)+'px');
                  })
                  .on('mouseout', () => tooltip.style('opacity',0))
                  .on('click', (event, d) => loadDrilldown({ storage: d.storage, risk: d.risk }));

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