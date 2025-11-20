@extends('template')
@section('main')
<div class="col-md-10">
<div class="main-panel min-height mt-4">
<div class="row">
<div class="margin-top-85">
<div class="row m-0">
@include('users.sidebar')
<div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
  <h2>File Inventory &amp; AI Analysis Table</h2>
  <hr style="border-top:2px solid #333; margin-bottom:1em;"/>
  <div class="row mb-3" style="max-width:800px;">
    <div class="col-md-5">
      <label><strong>Filter by User/Site/Source:</strong></label>
      <select class="form-control" id="entitySelect"></select>
    </div>
    <div class="col-md-4">
      <label><strong>Filter by Risk Rating:</strong></label>
      <select class="form-control" id="riskSelect">
        <option value="">All</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
        <option value="none">None</option>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <div id="fileCountDisplay" style="font-weight:bold; font-size:2.1em; color:#222; margin-bottom:5px;">0 Files</div>
    </div>
  </div>
  <table id="fileTable" class="display nowrap" style="width:100%">
      <thead>
          <tr>
              <th style="border-bottom:2px solid #666; border-right:2px solid #ddd;">Compliance Risk</th>
              <th style="border-bottom:2px solid #666; border-right:2px solid #ddd;">File Name</th>
              <th style="border-bottom:2px solid #666; border-right:2px solid #ddd;">Folder</th>
              <th style="border-bottom:2px solid #666; border-right:2px solid #ddd;">Size (KB)</th>
              <th style="border-bottom:2px solid #666;">Risk Analysis</th>
          </tr>
      </thead>
  </table>
  <div id="llm-modal" style="z-index:9999;display:none;position:fixed;top:10%;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #aaa;padding:2em;max-width:60vw;max-height:70vh;overflow:auto;">
      <button class="btn btn-sm btn-secondary" style="float:right;" onclick="$('#llm-modal').hide()">Close</button>
      <div id="llm-modal-content" style="white-space:pre-line"></div>
  </div>
</div>
</div></div></div></div></div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
.risk-btn   { font-weight:bold; border:none; outline:none; padding:7px 18px; border-radius:2em; font-size:1em; cursor:pointer; margin:0 0.4em 0 0; }
.risk-btn-high   { background:#e74c3c !important; color: #fff !important; }
.risk-btn-medium { background:#ffae42 !important; color:#222 !important;}
.risk-btn-low    { background:#76b947 !important; color:#fff !important;}
.risk-btn-none   { background:#dcdcdc !important; color:#888 !important;}
#fileTable thead th { vertical-align: middle; text-align: center; font-size:1.09em; border-bottom:2px solid #666 !important; }
#fileTable th, #fileTable td { border-right:2px solid #eee !important; }
#fileTable th:last-child, #fileTable td:last-child { border-right:none !important; }
#fileTable td { vertical-align:middle; }
hr { border-top:2px solid #333;}
</style>
<script>
// Raw data from backend
const filterGroups = {!! json_encode($filterGroups) !!};
const sourceLabels = {!! json_encode($sourceLabels) !!};
const riskTitles = {high: "High", medium: "Medium", low: "Low", none: "None"};

// Helper: normalize entity entries (string or object)
function normEntity(val) {
    if (typeof val === 'string') return { id: val, label: null };
    if (val && typeof val === 'object') {
        return {
            id: val.id || val.value || val.code || '',
            label: val.label || val.text || null
        };
    }
    return { id: '', label: null };
}

// Build entity dropdown robustly (handles objects and strings, AWS/SRC_* safe)
function renderEntityDropdown(selected) {
    let select = $('#entitySelect');
    select.empty();
    // All Files = send no entity parameter
    select.append($('<option>',{value: '', text: 'All Files'}));
    (filterGroups || []).forEach(group=>{
        const values = Array.isArray(group.values) ? group.values : [];
        if(values.length === 0) return;
        let optgroup = $('<optgroup>',{label: group.label || 'Group'});
        values.forEach(v=>{
            const { id, label } = normEntity(v);
            if (!id) return;
            let text = label || id;
            if(id.startsWith('SRC_')) {
                text = (sourceLabels && sourceLabels[id]) ? sourceLabels[id] : id.replace('SRC_','') + ' Files';
            } else if(id === '__UNASSIGNED__') {
                text = "Unassigned Files";
            }
            optgroup.append($('<option>',{value: id, text: text}));
        });
        select.append(optgroup);
    });
    if (selected !== undefined) select.val(selected);
}

function updateFileCount(resp, fallbackLen = 0) {
    // Use the first available count-like field
    const total =
        (resp && (resp.recordsTotal ?? resp.recordsFiltered ?? resp.total ?? resp.count)) ??
        fallbackLen;
    const num = Number(total) || 0;
    $("#fileCountDisplay").html(`<span>${num}</span> File${num===1?'':'s'}`);
}

// Robust risk extraction
function extractRiskFromLLM(llmResp) {
    if (!llmResp) return 'none';
    let clean = typeof llmResp === 'string' ? llmResp.trim() : (function(){
        try { return JSON.stringify(llmResp); } catch(e){ return ''; }
    })();
    try {
        // Remove any outer quotes (from double-escaped string JSON)
        if (clean.startsWith('"') && clean.endsWith('"')) clean = clean.slice(1, -1);
        const parsed = JSON.parse(clean);
        if (parsed && parsed.overall_risk_rating) {
            let r = (parsed.overall_risk_rating+'').toLowerCase();
            if (['high','medium','low','none'].includes(r)) return r;
        }
        if(parsed && Array.isArray(parsed.results) && parsed.results.length) {
            let rank = {high:3, medium:2, low:1, none:0};
            let max = 'none';
            parsed.results.forEach(res => {
                let risk = ((res && res.risk) ? (res.risk+'') : '').toLowerCase();
                if(rank[risk] > rank[max]) max = risk;
            });
            return max;
        }
    } catch (e) {}
    // fallback to regex for legacy/plaintext output:
    let riskPattern = /overall\s+(?:risk\s+rating|rating)\s*(?:is)?\s*:?[\s*_`]*\**\s*(high|medium|low|none)\s*\**/i;
    let lines = (clean+"").split(/\r?\n/);
    for(let i = lines.length-1; i >= 0; i--) {
        let line = lines[i];
        let m = line.match(riskPattern);
        if(m && m[1]) {
            let val = (m[1]+'').toLowerCase();
            if(['high','medium','low','none'].includes(val))
                return val;
        }
    }
    let lastRisk=null, body = (clean+"");
    let regex = new RegExp(riskPattern.source, "ig");
    let match;
    while((match = regex.exec(body)) !== null) lastRisk = (match[1]+'').toLowerCase();
    if(lastRisk) return lastRisk;
    return 'none';
}

// Safe encoder for LLM response
function encodeLLM(llm) {
    let s = '';
    try {
        if (typeof llm === 'string') s = llm;
        else s = JSON.stringify(llm ?? '');
    } catch(e){ s = String(llm ?? ''); }
    return btoa(unescape(encodeURIComponent(s)));
}

// Derive folder robustly from multiple possible fields
function deriveFolder(item) {
    const full = item.full_path || item.path || item.key || '';
    const folderOnly = full.replace(/[/\\][^/\\]+$/, '');
    if (folderOnly && folderOnly !== full) return folderOnly || '/';
    if (item.folder) return item.folder;
    if (item.directory) return item.directory;
    return '/';
}

let dt = null;
function reloadTable() {
    if (dt) dt.ajax.reload(null, false);
}

$(function(){
    renderEntityDropdown();

    dt = $('#fileTable').DataTable({
        processing:true,
        serverSide:true,
        deferRender:true,
        pageLength:50,
        scrollX: true,
        searching: false,
        order: [[1, 'asc']],
        ajax: function(data, cb) {
            let entity = $('#entitySelect').val();
            let risk = $('#riskSelect').val();
            const params = {
                page: Math.floor(data.start / data.length) + 1,
                pageSize: data.length
            };
            // Only send filters if specified to avoid backend "empty string" filter bugs
            if (entity) params.entity = entity;
            if (risk) params.riskLevel = risk;

            $.ajax({
                url: '/api/files-table',
                data: params,
                method: 'GET',
                dataType: 'json'
            }).done(function(resp){
                const rawItems = resp && (resp.data || resp.items || resp.files) || [];
                const rows = rawItems.map(item => {
                    const folder = deriveFolder(item);
                    const riskLevel = extractRiskFromLLM(item.llm_response || item.llm || '');
                    const riskText = riskTitles[riskLevel] || 'None';
                    const rbclass = "risk-btn risk-btn-" + riskLevel;
                    const llmEncoded = encodeLLM(item.llm_response || item.llm || '');
                    const riskBtn = `<button type="button" class="${rbclass}" onclick="showLlm('${llmEncoded}')">${riskText}</button>`;
                    const sizeKb = Math.round((item.size_bytes || item.size || 0) / 1024);
                    const fileName = item.file_name || item.name || (function(){
                        const p = item.full_path || item.path || item.key || '';
                        const seg = p.split(/[\\/]/).filter(Boolean);
                        return seg.length ? seg[seg.length-1] : '';
                    })();
                    return {
                        risk: riskBtn,
                        file_name: fileName || '-',
                        folder: folder || '/',
                        size: isFinite(sizeKb) ? sizeKb : 0,
                        analysis: `<button class="btn btn-sm btn-outline-info" onclick="showLlm('${llmEncoded}')">Risk Analysis</button>`
                    };
                });

                // Update counter using best available field
                updateFileCount(resp, rows.length);

                cb({
                    draw: data.draw, // ensure DataTables accepts the response
                    data: rows,
                    recordsTotal: (resp && (resp.recordsTotal ?? resp.total ?? rows.length)) || rows.length,
                    recordsFiltered: (resp && (resp.recordsFiltered ?? resp.recordsTotal ?? resp.total ?? rows.length)) || rows.length
                });
            }).fail(function(xhr){
                // On failure, show 0 rows but keep table functional
                updateFileCount({recordsTotal:0});
                cb({
                    draw: data.draw,
                    data: [],
                    recordsTotal: 0,
                    recordsFiltered: 0
                });
            });
        },
        columns:[
            {data:'risk', orderable:false},
            {data:'file_name'},
            {data:'folder'},
            {data:'size'},
            {data:'analysis', orderable:false}
        ]
    });

    $("#entitySelect, #riskSelect").on('change', function(){ reloadTable(); });
});

window.showLlm = function(b64){
    let text = '';
    try { text = decodeURIComponent(escape(atob(b64))).trim(); } catch(e){ text = ''; }
    let content = '', parsed = null;
    try { if (text.startsWith('"') && text.endsWith('"')) text = text.slice(1,-1); parsed = JSON.parse(text); } catch(e){}
    if(parsed && parsed.results){
        content = `<div style="font-weight:700;margin-bottom:4px;">AI Compliance Findings:</div>`;
        parsed.results.forEach(r => {
            const riskVal = ((r && r.risk) ? (r.risk+'') : '').toLowerCase();
            content += `<div style="margin-bottom:12px;padding-bottom:5px;border-bottom:1px solid #efefef;">
                <b>Standard:</b> ${r.standard || '-'}, <b>Jurisdiction:</b> ${r.jurisdiction || '-'}<br>
                <b>Detected Fields:</b> ${(r.detected_fields||[]).join(', ')||'-'}<br>
                <b>Risk:</b> <span style="color:${riskVal==='high'?'#ee2222':riskVal==='medium'?'#ffae42':riskVal==='low'?'#73db21':'#666'}">${r.risk || '-'}</span>
                ${r.auditor_agent_view ? `<br><b>Agent Reasoning:</b> <span style="color:#1a8562;">${r.auditor_agent_view}</span>` : ""}
            </div>`;
        });
        if(parsed.auditor_agent_view){
            content += `<div style="margin:15px 0 7px 0;font-weight:700;color:#1a8562;">AI Auditor Evidence:</div>
                <div style="background:#f5fefa;padding:9px 16px 7px 16px;border-radius:7px;">
                ${parsed.auditor_agent_view}
            </div>`;
        }
        if(parsed.likely_data_subject_area)
            content += `<div style="margin-top:10px;font-weight:700;">Likely Data Subject Area: <span style="color:#1877c2;">${parsed.likely_data_subject_area}</span></div>`;
        if(parsed.data_classification)
            content += `<div style="margin-top:10px;font-weight:700;">Data Classification: <span style="color:#db1919;">${parsed.data_classification}</span></div>`;
        if(parsed.overall_risk_rating){
            const ov = (parsed.overall_risk_rating+'').toLowerCase();
            content += `<div style="margin-top:10px;font-weight:700;">Overall Risk Rating: <span style="color:${ov==='high'?'#ee2222':ov==='medium'?'#ffae42':ov==='low'?'#73db21':'#36d399'}">${parsed.overall_risk_rating}</span></div>`;
        }
        if(parsed.cyber_proposed_controls && parsed.cyber_proposed_controls.length) {
            content += `<div style="margin-top:8px;font-weight:700;">Cybersecurity Controls Proposed:</div><ul style="margin:0 0 6px 24px;color:#333;">`;
            (Array.isArray(parsed.cyber_proposed_controls) ? parsed.cyber_proposed_controls : [parsed.cyber_proposed_controls]).forEach(ctrl=>{
                content += `<li>${ctrl}</li>`;
            });
            content += `</ul>`;
        }
        if(parsed.auditor_proposed_action)
            content += `<div style="font-weight:700;">Auditor Proposed Action: <span style="color:#1877c2;">${parsed.auditor_proposed_action}</span></div>`;
        $('#llm-modal-content').html(content);
    }else{
        $('#llm-modal-content').text(text || 'No analysis available.');
    }
    $('#llm-modal').show();
}
</script>
@endsection