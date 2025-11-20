@extends('cybersecaiagents.wizard.chatlayout')
@section('chat-body')
<h2 style="color:#10a37f;margin:0 0 22px 12px;font-weight:800;">Data Classification Visual</h2>
<svg id="d3bar" width="510" height="280"></svg>
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script>
let data = @json($visual ?? []);
if(!Array.isArray(data)) data=[];
let svg = d3.select("#d3bar"), W=510, H=280, margin=45;
let x = d3.scaleBand().domain(data.map(d=>d.type)).range([margin,W-margin]).padding(.25);
let y = d3.scaleLinear().domain([0, d3.max(data, d=>d.count) || 30]).range([H-margin,margin]);
svg.selectAll("rect").data(data).enter().append("rect")
   .attr("x",d=>x(d.type)).attr("width",x.bandwidth())
   .attr("y",d=>y(d.count)).attr("height",d=>H-margin-y(d.count))
   .attr("fill", "#00cca8");
svg.append("g").attr("transform",`translate(0,${H-margin})`).call(d3.axisBottom(x));
svg.append("g").attr("transform",`translate(${margin},0)`).call(d3.axisLeft(y));
</script>
@endsection