@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h2>Data Classification Visual</h2>
    <div id="chart"></div>
</div></div></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script>
let data = @json($visual ?? []);
if(!Array.isArray(data)) data = [];
if(data.length > 0) {
    // D3 bar chart
    const W=500,H=300,margin=50;
    const svg = d3.select("#chart")
        .append("svg")
        .attr("width",W).attr("height",H);

    const x = d3.scaleBand()
        .domain(data.map(d=>d.type))
        .range([margin,W-margin])
        .padding(0.2);
    const y = d3.scaleLinear()
        .domain([0, d3.max(data, d=>d.count)])
        .range([H-margin,margin]);

    svg.selectAll("rect")
        .data(data)
        .enter()
        .append("rect")
        .attr("x",d=>x(d.type))
        .attr("y",d=>y(d.count))
        .attr("width",x.bandwidth())
        .attr("height",d=>H-margin-y(d.count))
        .attr("fill","steelblue");

    svg.append("g")
        .attr("transform",`translate(0,${H-margin})`)
        .call(d3.axisBottom(x));
    svg.append("g")
        .attr("transform",`translate(${margin},0)`)
        .call(d3.axisLeft(y));
}
</script>
@endsection