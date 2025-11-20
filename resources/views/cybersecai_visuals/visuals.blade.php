@extends('template')
@section('title', 'CybersecAI Visuals - File Analytics')

@section('main')

  <div class="col-md-10">
    <div class="main-panel min-height mt-4">
      <div class="row">
        <div class="margin-top-85">
          <div class="row m-0">
            @include('users.sidebar')
            <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
          <h2>CybersecAI File Analytics Visuals</h2>
          <div style="margin-bottom:15px;">
            <select id="entity-select" class="form-select" style="width:300px;display:inline;"></select>
            <button class="btn btn-info" id="reloadBtn">Reload</button>
          </div>

          {{-- Mini charts --}}
          <div class="mini-charts-wrap" style="display:flex;flex-wrap:wrap;gap:15px;justify-content:space-between;">
            <div class="mini-chart" id="miniPie"></div>
            <div class="mini-chart" id="miniBar"></div>
            <div class="mini-chart" id="miniRadar"></div>
            <div class="mini-chart" id="miniScatter"></div>
            <div class="mini-chart" id="miniTreemap"></div>
            <div class="mini-chart" id="miniDonut"></div>
            <div class="mini-chart" id="miniSunburst"></div>
            <div class="mini-chart" id="miniBox"></div>
          </div>

          {{-- Modal for big chart --}}
          <div id="chartModal" style="position:fixed;display:none;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,.7);">
            <div id="bigChart" style="width:78vw;height:78vh;margin:4vw auto;background:#fff;box-shadow:0 5px 70px #222"></div>
            <button id="closeChartModal" class="btn btn-danger" style="position:absolute;top:2vw;right:5vw;font-size:2em;">&times;</button>
          </div>

          <div style="margin-top:38px;">
            <div id="chartFilesTable"></div>
          </div>
        </div>
      </div>
    </div></div></div></div>

<style>
.mini-chart {width:260px; height:200px; min-width:180px; min-height:150px; cursor:pointer; border-radius:0.5em; background:#f9fafd; border:1.5px solid #eee;}
@media (max-width: 1100px) {.mini-chart {width:48vw !important; min-width:160px;} .mini-charts-wrap{gap:8px;}}
</style>

<script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/clusterize.js@0.18.1"></script>

<script>
const filterGroups = @json($filterGroups);
const sourceLabels = @json($sourceLabels);
const apiEndpoint = '{{ url('/cybersecai-visuals/api/filedata') }}';
const riskColorMap = {high:'#ee2222',medium:'#ffae42',low:'#73db21',none:'#cfcfcf'};
const riskLabelMap = {high:'High',medium:'Medium',low:'Low',none:'None'};

function populateFilterDropdown() {
    const sel = document.getElementById('entity-select');
    sel.innerHTML = '';
    filterGroups.forEach(group => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = group.label;
        group.values.forEach(val => {
            const option = document.createElement('option');
            option.value = val;
            option.innerText = sourceLabels[val] ? sourceLabels[val] : val;
            optgroup.appendChild(option);
        });
        sel.appendChild(optgroup);
    });
}


  
function extractFileRisk(llmResp) {
    if (!llmResp) return 'none';
    let riskPattern = /overall\s+(?:risk\s+rating|rating)\s*(?:is)?\s*:?[\s*_`]*\**\s*(high|medium|low|none)\s*\**/i;
    let lines = (llmResp+"").split(/\r?\n/);
    for(let i = lines.length-1; i >= 0; i--) {
        let line = lines[i];
        let m = line.match(riskPattern);
        if(m && m[1]) {
            let val = m[1].toLowerCase();
            if(['high','medium','low','none'].includes(val))
                return val;
        }
    }
    let lastRisk=null, body = (llmResp+"");
    let regex = new RegExp(riskPattern.source, "ig");
    let match;
    while((match = regex.exec(body)) !== null) {
        lastRisk = match[1].toLowerCase();
    }
    if(lastRisk) return lastRisk;
    return 'none';
}

async function fetchData(entity, limit=10000, offset=0) {
    let params = new URLSearchParams();
    if(entity) params.append('entity', entity);
    params.append('limit', limit);
    params.append('offset', offset);
    let r = await fetch(apiEndpoint + '?' + params.toString());
    let json = await r.json();
    return json.data || [];
}

// ------ Chart utils ---------
function getFieldHistogram(data, field, byRisk=false) {
    // If byRisk, returns object of {risk:{value:count}}
    if (!byRisk) {
        let map = {};
        (data || []).forEach(item => {
            let val = item[field] || 'Unknown';
            map[val] = (map[val]||0) + 1;
        });
        let x = Object.keys(map);
        let y = x.map(k => map[k]);
        return { x, y, pairs: x.map((v,i)=>({name:v,value:y[i]})) };
    }
    // byRisk: group field totals by each risk
    let risks = ['high','medium','low','none'];
    let types = {};
    (data||[]).forEach(item => {
        let t = item[field] || 'Unknown';
        let r = item._risk || 'none';
        if(!types[t]) types[t]={};
        types[t][r] = (types[t][r]||0)+1;
    });
    let allTypes = Object.keys(types);
    let series = risks.map(risk => ({
        name: riskLabelMap[risk],
        stack: 'risk',
        type: 'bar',
        itemStyle: {color:riskColorMap[risk]},
        data: allTypes.map(t => types[t][risk]||0)
    }));
    return { x: allTypes, series };
}

function getRiskPieData(data) {
    return ['high','medium','low','none'].map(rk=>({
        name:riskLabelMap[rk],
        value:data.filter(f=>f._risk==rk).length,
        itemStyle: {color: riskColorMap[rk]}
    }));
}

function getTopN(data, field, n=10) {
    let arr = data.filter(x=>x[field]).sort((a,b)=>(b[field]-a[field]));
    return arr.slice(0,n);
}

function getBoxplotByCategory(data, catField, numField) {
    let groups = {};
    data.forEach(row => {
        let cat = row[catField] || 'Other';
        let num = parseInt(row[numField]||0);
        if(isNaN(num))return;
        if(!groups[cat]) groups[cat]=[];
        groups[cat].push(num);
    });
    let cats = Object.keys(groups);
    let vals = cats.map(cat=>{
        let arr = groups[cat].sort((a,b)=>a-b);
        let len = arr.length;
        return [
            arr[0],                                     // min
            arr[Math.floor(len*0.25)],                  // Q1
            arr[Math.floor(len*0.5)],                   // median
            arr[Math.floor(len*0.75)],                  // Q3
            arr[len-1]                                  // max
        ];
    });
    return {cats,vals};
}

function buildSunburst(data) {
    let tree = {};
    data.forEach(row=>{
        let parts = (row.full_path||'/nofolder/'+row.file_name).split('/');
        let cur = tree;
        parts.forEach((p,i)=> {
            if(!cur[p]) cur[p]={_children:{}};
            if(i === parts.length-1) cur[p]._row = row;
            cur = cur[p]._children;
        });
    });
    function walk(node) {
        return Object.entries(node).map(([name,val])=>{
            let res = {name};
            if(val._row) res.value=val._row.size_bytes||1;
            let children = walk(val._children);
            if(children.length) res.children=children;
            return res;
        });
    }
    return walk(tree);
}

function getRiskAssessmentRadar(data) {
    let categories = ['High','Medium','Low','None'];
    let map = {'High':0,'Medium':0,'Low':0,'None':0};
    data.forEach(f => {
        let resp = (f.llm_response||'') + '';
        let match = resp.match(/Overall\s+Risk\s+Rating.*?(High|Medium|Low|None)/i);
        if(match) { let level=match[1]; map[level.charAt(0).toUpperCase()+level.slice(1).toLowerCase()]++; }
        else map['None']++;
    });
    return {categories, values:categories.map(k=>map[k])};
}

function getScatterByRisk(data) {
    // Array of {value: [date, size], name:..., risk:..., file: full obj}
    return data.filter(f=>f.size_bytes && f.last_modified).map(f=>({
        value: [new Date(f.last_modified).getTime(), parseInt(f.size_bytes)],
        name: f.file_name,
        risk: f._risk,
        file: f,
    }));
}

function getTreemapByRisk(data) {
    // treemap: file_type → risk → count
    let groups={};
    data.forEach(f=>{
        let t = f.file_type||'Other', r = f._risk||'none';
        if(!groups[t]) groups[t]={};
        groups[t][r] = (groups[t][r]||0)+1;
    });
    return Object.keys(groups).map(type=>({
        name:type,
        children:Object.keys(groups[type]).map(risk=>({
            name: riskLabelMap[risk],
            value: groups[type][risk],
            itemStyle: { color: riskColorMap[risk] }
        }))
    }));
}

// ------------ Charts -----------
let lastBigChart, lastChartType, lastChartData;
function showBigChart(option, type=null, chartData=[]) {
    document.getElementById('chartModal').style.display = "block";
    let bigChartBox = document.getElementById('bigChart');
    if(lastBigChart) lastBigChart.dispose();
    let bigChart = echarts.init(bigChartBox);
    // Enable zoom/legend on big chart
    option.toolbox = { feature:{ dataZoom:{}, saveAsImage:{}} };
    option.dataZoom = [{ type:'slider', xAxisIndex:0, filterMode:'filter' }];
    // Customize tooltip if type/data supplied
    if(type==='bar' || type==='scatter') {
      option.tooltip = {
        trigger: 'item',
        formatter: params => {
          let f = chartData[params.dataIndex].file || chartData[params.dataIndex];
          return `<b>${f.file_name || f.name}</b><br>
                      Size: ${(f.size_bytes/1024).toFixed(1)} KB<br>
                      Date: ${f.last_modified ? new Date(f.last_modified).toLocaleString() : 'n/a'}<br>
                      Risk: <span style="color:${riskColorMap[f._risk||f.risk]}">${riskLabelMap[f._risk||f.risk]}</span><br>
                      ${f.llm_response ? `<button onclick="showLlmModal('${btoa(unescape(encodeURIComponent(f.llm_response)))}')" style="margin-top:3px;" class="btn btn-sm btn-primary">Show Analysis</button>`:""}`;
        }
      }
    }
    if(type==='pie') {
      option.tooltip = { trigger:'item', formatter: p=>`${p.name}: ${p.value}<br>Percent: ${p.percent}%` }
    }
    bigChart.setOption(option, true);
    lastBigChart = bigChart;
    lastChartType = type;
    lastChartData = chartData;
}
document.getElementById('closeChartModal').onclick = ()=>{
    document.getElementById('chartModal').style.display='none';
    if(lastBigChart) lastBigChart.dispose();
};
document.addEventListener('keydown', (e)=> {
    if(e.key === "Escape") document.getElementById('closeChartModal').click();
});

// ------------ Render Mini and Big charts ------------
function renderMiniCharts(data) {
    // PIE: segment by individual risks
    let pieOpt = {
      title:{text:'File Risk Types',left:'center',textStyle:{fontSize:13}},
      tooltip:{trigger:'item',formatter: p=>`${p.name}: ${p.value}`},
      series:[{type:'pie',data:getRiskPieData(data),radius:['20%','70%']}]
    };
    let pieChart=echarts.init(document.getElementById('miniPie'));
    pieChart.setOption(pieOpt);
    pieChart.on('click',()=>showBigChart({...pieOpt,title:{text:"File Risk Types (Pie)"},legend:{orient:'vertical',left:'right'}},'pie',data));

    // BAR: stacked by risk for each file_type
    let {x,series} = getFieldHistogram(data,'file_type',true);
    let barOpt =
    {
      title:{text:'Files by Type & Risk',left:'center',textStyle:{fontSize:13}},
      legend: { data: series.map(s=>s.name), top:24 },
      tooltip:{trigger:'axis'},
      xAxis:{data:x},
      yAxis:{},
      series:series
    };
    let barChart=echarts.init(document.getElementById('miniBar'));
    barChart.setOption(barOpt);
    barChart.on('click',()=>showBigChart({...barOpt,title:{text:"Files by Type & Risk (Bar)"},legend:{},dataZoom:[{type:'slider',xAxisIndex:0}]}, 'bar', data));

    // RADAR: count by risk
    let radarData = getRiskAssessmentRadar(data);
    let radarOpt={
      title:{text:"File Count by Risk",left:'center',textStyle:{fontSize:13}},
      tooltip:{},
      radar:{indicator:radarData.categories.map(t=>({name:t,max:300}))},
      series:[{type:'radar',data:[{value:radarData.values,name:'Files'}]}]
    };
    let radarChart=echarts.init(document.getElementById('miniRadar'));
    radarChart.setOption(radarOpt);
    radarChart.on('click',()=>showBigChart({...radarOpt,title:{text:"Risk Radar (Counts)"}},'radar',data));

    // SCATTER: each point colored by risk, shows drill
    let scatterPoints = getScatterByRisk(data);
    let scatterOpt={
      title:{text:"Size by Date, colored by Risk",left:'center',textStyle:{fontSize:13}},
      legend: {},
      tooltip:{formatter: p => {
        let f = scatterPoints[p.dataIndex].file;
        return `<b>${f.file_name}</b><br>Size: ${(f.size_bytes/1024/1024).toFixed(2)} MB<br>Risk: <span style="color:${riskColorMap[f._risk]}">${riskLabelMap[f._risk]}</span><br>Date: ${new Date(f.last_modified).toLocaleString()}`
      }},
      xAxis:{type:'time'}, yAxis:{},
      series:[{
        type:'scatter',
        symbolSize:12,
        data:scatterPoints,
        itemStyle:{color: params => riskColorMap[params.data.risk]}
      }]
    };
    let scatterChart=echarts.init(document.getElementById('miniScatter'));
    scatterChart.setOption(scatterOpt);
    scatterChart.on('click',()=>showBigChart({...scatterOpt,title:{text:"Files: Size/Date/Risk"}}, 'scatter', scatterPoints));

    // TREEMAP: segment child nodes by risk
    let treemapOpt={
     title:{text:"Treemap: Type/Risk",left:'center',textStyle:{fontSize:13}},
     tooltip:{},
     series:[{type:'treemap',data:getTreemapByRisk(data),leafDepth:2}]
    };
    let treemapChart=echarts.init(document.getElementById('miniTreemap'));
    treemapChart.setOption(treemapOpt);
    treemapChart.on('click',()=>showBigChart({...treemapOpt,title:{text:"Type/Risk Treemap"}},'treemap',data));

    // DONUT: risk as segments
    let donutOpt={...pieOpt,series:[{...pieOpt.series[0],radius:['55%','70%']}]};
    let donutChart=echarts.init(document.getElementById('miniDonut'));
    donutChart.setOption(donutOpt);
    donutChart.on('click',()=>showBigChart({...donutOpt,title:{text:"Donut: Risk Segments"}},'pie',data));

    // SUNBURST—show file count by directory; highlight segment color by known risk where data present.
    let sunburstData = buildSunburst(data);
    function colorByRisk(o){
        if(o.children && o.children.length) o.children.forEach(colorByRisk);
        if(o.value) { // try to infer a risk (optional, fallback gray if unknown)
            let f = data.find(f=>f.file_name===o.name);
            if(f&&f._risk) o.itemStyle={color:riskColorMap[f._risk]};
        }
    }
    sunburstData.forEach(colorByRisk);
    let sunburstOpt={
      title:{text:"Folder Sunburst (risk)",left:'center',textStyle:{fontSize:13}},
      tooltip:{trigger:'item',formatter:p=>`${p.name}`},
      series:[{type:'sunburst',data:sunburstData,radius:[0,'90%']}]
    };
    let sunburstChart=echarts.init(document.getElementById('miniSunburst'));
    sunburstChart.setOption(sunburstOpt);
    sunburstChart.on('click',()=>showBigChart({...sunburstOpt,title:{text:"Folder-Level Risk Sunburst"}},'sunburst',data));

    // BOXPLOT
    let boxData=getBoxplotByCategory(data,'file_type','size_bytes');
    let boxOpt={
      title:{text:'Boxplot Size/Type',left:'center',textStyle:{fontSize:13}},
      tooltip:{},
      xAxis:{data:boxData.cats.map(x=>x.slice(0,8))},yAxis:{},
      series:[{type:'boxplot',data:boxData.vals}]
    };
    let boxChart=echarts.init(document.getElementById('miniBox'));
    boxChart.setOption(boxOpt);
    boxChart.on('click',()=>showBigChart({...boxOpt,title:{text:"Boxplot: Size by Type"}},'boxplot',data));
}

// Table with risk badges and analysis modal
function renderTable(data) {
    if (!document.getElementById('scrollArea')) {
        let html = `<div id="scrollArea" style="height:250px;overflow:auto;">
            <table class="table table-sm"><thead id="contentHeader"></thead><tbody id="contentArea"></tbody></table>
        </div>
        <div id="llmModal" style="display:none;position:fixed;z-index:9000;left:50%;top:12vh;transform:translateX(-50%);background:#fff;padding:2em;max-width:60vw;max-height:70vh;overflow:auto;border-radius:8px;box-shadow:0 9px 99px #444">
            <button class="btn btn-secondary mb-3 float-right" onclick="document.getElementById('llmModal').style.display='none'">&times; Close</button>
            <div id="llmModalContent" style="white-space:pre-line"></div>
        </div>`;
        document.getElementById('chartFilesTable').innerHTML = html;
    }
    let first = data[0]||{};
    let ths = Object.keys(first).slice(0,25).map(k=>`<th>${k}</th>`).join('');
    document.getElementById('contentHeader').innerHTML = `<tr>${ths}</tr>`;
    // Body, with button for llm_response and risk badge
    let rows = (data||[]).map(row => {
        let tds = Object.keys(first).slice(0,25).map(k => {
            if (k === 'llm_response' && row[k]) {
                let b64 = btoa(unescape(encodeURIComponent(row[k])));
                return `<td><button class="btn btn-sm btn-info" onclick="showLlmModal('${b64}')">Show</button></td>`;
            } else if (k === '_risk') {
                let c = riskColorMap[row[k]]||'#999';
                return `<td><span style="background:${c};color:#fff;padding:2px 12px;border-radius:1em;text-transform:capitalize">${riskLabelMap[row[k]]||row[k]}</span></td>`;
            } else {
                return `<td>${row[k]||''}</td>`;
            }
        }).join('');
        return `<tr>${tds}</tr>`;
    });
    document.getElementById('contentArea').innerHTML = rows.join('');
    if(window.Clusterize) new Clusterize({
        rows: rows,
        scrollId: 'scrollArea', contentId: 'contentArea'
    });
}

window.showLlmModal = function(b64) {
    let txt = decodeURIComponent(escape(atob(b64)));
    document.getElementById('llmModalContent').textContent = txt;
    document.getElementById('llmModal').style.display = '';
}

// Main loader: when dropdown or reload clicked
async function reloadAllVisuals() {
    let entitySel = document.getElementById('entity-select');
    let entity = entitySel && entitySel.value ? entitySel.value : 'ALL';
    let data = await fetchData(entity);
    data.forEach(f=>{
        f._risk = extractFileRisk(f.llm_response); // annotate risk!
    });
    window.fullData = data; // for debug!
    renderMiniCharts(data);
    renderTable(data.slice(0,10000));
}

document.addEventListener("DOMContentLoaded", ()=>{
    populateFilterDropdown();
    document.getElementById('reloadBtn').onclick = reloadAllVisuals;
    reloadAllVisuals();
});
</script>
@endsection