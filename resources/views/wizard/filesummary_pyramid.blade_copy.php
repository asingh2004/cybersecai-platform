@extends('template')
@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')
          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
              <h2>Risk Pyramid - AI File Risk Overview</h2>
              <div class="form-group" style="max-width:400px;margin-top: 1em;">
                  <label><strong>Filter by User/Site/Source:</strong></label>
                  <select class="form-control" id="entitySelect"></select>
              </div>
              <div class="pyramid-container mb-4 mt-4" id="riskPyramid" style="max-width:800px;margin:0 auto;">
                  <!-- Populated by JS -->
              </div>
              <div id="fileListPanel" style="display:none;background:#fff;border:1px solid #bbb;padding:1em;max-width:800px;margin:2em auto;">
                  <button class="btn btn-light btn-sm float-right mb-2" onclick="$('#fileListPanel').hide()">Close</button>
                  <h4 id="filePanelTitle"></h4>
                  <table class="table table-striped">
                      <thead>
                          <tr>
                              <th>File Name</th>
                              <th>Type</th>
                              <th>Source</th>
                              <th>Size KB</th>
                              <th>Risk (JS)</th>
                              <th>Show Analysis</th>
                          </tr>
                      </thead>
                      <tbody id="filePanelTable"></tbody>
                  </table>
                  <div id="filePanelPager" class="mt-2 mb-1 text-center"></div>
              </div>
              <div id="llm-modal" style="z-index:9999;display:none;position:fixed;top:10%;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #aaa;padding:2em;max-width:60vw;max-height:70vh;overflow:auto;">
                  <button class="btn btn-sm btn-secondary" style="float:right;" onclick="$('#llm-modal').hide()">Close</button>
                  <div id="llm-modal-content" style="white-space:pre-line"></div>
              </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.pyramid-level { text-align:center; cursor:pointer; margin:0 auto; transition:box-shadow .15s; position:relative; }
.pyramid-level:hover { box-shadow:0 0 20px rgba(0,0,0,0.07); z-index:1; }
.pyramid-high { background: linear-gradient(90deg, #ee2222, #ff6666);}
.pyramid-medium {background: linear-gradient(90deg,#ffae42,#ffe299);}
.pyramid-low {background: linear-gradient(90deg, #73db21, #e2ffc2);}
.pyramid-none {background: linear-gradient(90deg,#e0e0e0, #ffffff);}
.risk-count-badge {background:#222;color:#fff;border-radius:1em;padding:.15em .7em; font-size:.95em;margin-left:6px;}
.pyramid-level[data-level="high"]{width:45%;}
.pyramid-level[data-level="medium"]{width:60%;}
.pyramid-level[data-level="low"]{width:80%;}
.pyramid-level[data-level="none"]{width:94%;}
.pyramid-level {padding:.9em .2em; margin-bottom:.5em;border-radius:2.3em;}
.risk-btn   { font-weight:bold; border:none; outline:none; padding:7px 18px; border-radius:2em; font-size:1em; cursor:pointer; margin:0 0.4em 0 0; }
.risk-btn-high   { background:#e74c3c !important; color: #fff !important; }
.risk-btn-medium { background:#ffae42 !important; color:#222 !important;}
.risk-btn-low    { background:#76b947 !important; color:#fff !important;}
.risk-btn-none   { background:#dcdcdc !important; color:#888 !important;}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const filterGroups = {!! $filterGroups !!};
const sourceLabels = {!! $sourceLabels !!};
const riskLabels = {
    'high':   { txt: "High Risk",    style: "pyramid-high"   },
    'medium': { txt: "Medium Risk",  style: "pyramid-medium" },
    'low':    { txt: "Low Risk",     style: "pyramid-low"    },
    'none':   { txt: "No Risk",      style: "pyramid-none"   }
};

function extractRiskFromLLM(llmResp) {
    if (!llmResp) return 'none';
    let clean = (''+llmResp).trim();
    try {
        if (clean.startsWith('"') && clean.endsWith('"')) clean = clean.slice(1, -1);
        const parsed = JSON.parse(clean);
        if (parsed && parsed.overall_risk_rating) {
            let r = parsed.overall_risk_rating.toLowerCase();
            if (['high','medium','low','none'].includes(r)) return r;
        }
        if(parsed && Array.isArray(parsed.results) && parsed.results.length) {
            let rank = {high:3, medium:2, low:1, none:0};
            let max = 'none';
            parsed.results.forEach(res => {
                let risk = (res.risk||'').toLowerCase();
                if(rank[risk] > rank[max]) max = risk;
            });
            return max;
        }
    } catch (e) {}
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
    while((match = regex.exec(body)) !== null) lastRisk = match[1].toLowerCase();
    if(lastRisk) return lastRisk;
    return 'none';
}

function renderEntityDropdown() {
    let select = $('#entitySelect');
    select.empty();
    select.append($('<option>', {value: '', text: "All Files"}));
    (filterGroups || []).forEach(group => {
        if(group.values.length === 0) return;
        let optgroup = $('<optgroup>', {label: group.label});
        group.values.forEach(val => {
            let text = val;
            if(val.startsWith('SRC_')) {
                text = (sourceLabels && sourceLabels[val]) ? sourceLabels[val] : val.replace('SRC_','') + ' Files';
            } else if(val === '__UNASSIGNED__') {
                text = "Unassigned Files";
            }
            optgroup.append($('<option>', {value: val, text: text}));
        });
        select.append(optgroup);
    });
}

function buildPyramid(entity) {
    $.get('/api/pyramid-stats', {entity}, function(resp){
        let html = "";
        Object.keys(riskLabels).forEach(level => {
            let badge = `<span class="risk-count-badge">${resp.counts[level] || 0}</span>`;
            html += `<div class="pyramid-level ${riskLabels[level].style}" data-entity="${entity||''}" data-level="${level}" onclick="showFilePanel('${entity}','${level}',1)">
                        <strong>${riskLabels[level].txt}</strong> ${badge}
                     </div>`;
        });
        $('#riskPyramid').html(html);
    });
}

window.showFilePanel = function(entity, level, page = 1) {
    const pageSize = 50;
    $('#filePanelTitle').html(`<span style="text-transform:capitalize;">${riskLabels[level].txt}</span> <small class='text-muted ml-2'>Loading...</small>`);
    $('#fileListPanel').show();
    $('#filePanelTable').html('<tr><td colspan=6 class="text-center text-muted">Loading...</td></tr>');
    $.get('/api/pyramid-stats', {entity, riskLevel:level, page, pageSize}, function(resp){
        $('#filePanelTitle').html(`<span style="text-transform:capitalize;">${riskLabels[level].txt}</span> <small class='text-muted ml-2'>${resp.filesTotal} file(s)</small>`);
        let rows = (resp.files||[]).map(f => {
            let riskLevel = extractRiskFromLLM(f.llm_response || '');
            let riskBtn = `<span class="risk-btn risk-btn-${riskLevel}">${riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1)}</span>`;
            let llmEnc = btoa(unescape(encodeURIComponent(f.llm_response || "")));
            let src = (f._datasource ? f._datasource : 'Unknown');
            let link = f.file_name;
            return `<tr>
                <td>${link}</td>
                <td>${(f.file_type || '').split('/').pop()}</td>
                <td>${src}</td>
                <td>${Math.round((f.size_bytes||0)/1024)}</td>
                <td>${riskBtn}</td>
                <td><button class="btn btn-sm btn-info" onclick="showLlm('${llmEnc}')">Show</button></td>
                </tr>`;
        }).join('');
        $('#filePanelTable').html(rows.length ? rows : '<tr><td colspan=6 class="text-center text-muted">No file(s) in this category.</td></tr>');

        // Paging controls – windowed as before
        let pager = '';
        const pageNum = resp.page || 1;
        const totalPages = Math.ceil((resp.filesTotal || 0) / pageSize);
        if (totalPages > 1) {
            pager += `<nav><ul class="pagination justify-content-center">`;
            if (pageNum > 1) {
                pager += `<li class="page-item"><a class="page-link" href="#" onclick="showFilePanel('${entity}','${level}',1);return false;">First</a></li>`;
                pager += `<li class="page-item"><a class="page-link" href="#" onclick="showFilePanel('${entity}','${level}',${pageNum - 1});return false;">Prev</a></li>`;
            }
            let windowSize = 7;
            let startPage = Math.max(1, pageNum - Math.floor(windowSize / 2));
            let endPage = startPage + windowSize - 1;
            if (endPage > totalPages) {
                endPage = totalPages;
                startPage = Math.max(1, endPage - windowSize + 1);
            }
            for (let p = startPage; p <= endPage; p++) {
                pager += `<li class="page-item ${p == pageNum ? 'active' : ''}"><a class="page-link" href="#" onclick="showFilePanel('${entity}','${level}',${p});return false;">${p}</a></li>`;
            }
            if (pageNum < totalPages) {
                pager += `<li class="page-item"><a class="page-link" href="#" onclick="showFilePanel('${entity}','${level}',${pageNum + 1});return false;">Next</a></li>`;
                pager += `<li class="page-item"><a class="page-link" href="#" onclick="showFilePanel('${entity}','${level}',${totalPages});return false;">Last</a></li>`;
            }
            pager += `</ul></nav>`;
        }
        $('#filePanelPager').html(pager);
    });
};

window.showLlm = function(b64) {
    let text = decodeURIComponent(escape(atob(b64))).trim();
    let content = '', parsed = null;
    try {
        if (text.startsWith('"') && text.endsWith('"')) text = text.slice(1,-1);
        parsed = JSON.parse(text);
    } catch(e){}
    if (parsed && parsed.results) {
        content = `<div style="font-weight:700;margin-bottom:4px;">AI Compliance Findings:</div>`;
        parsed.results.forEach(r => {
            content += `<div style="margin-bottom:12px;padding-bottom:5px;border-bottom:1px solid #efefef;">
                <b>Standard:</b> ${r.standard || '-'}, <b>Jurisdiction:</b> ${r.jurisdiction || '-'}<br>
                <b>Detected Fields:</b> ${(r.detected_fields||[]).join(', ')||'-'}<br>
                <b>Risk:</b> <span style="color:${(r.risk||'').toLowerCase()==='high'?'#ee2222':(r.risk||'').toLowerCase()==='medium'?'#ffae42':(r.risk||'').toLowerCase()==='low'?'#73db21':'#666'}">${r.risk || '-'}</span>
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
            content += `<div style="margin-top:8px;font-weight:700;">Likely Data Subject Area: <span style="color:#1877c2;">${parsed.likely_data_subject_area}</span></div>`;
        if(parsed.data_classification)
            content += `<div style="margin-top:8px;font-weight:700;">Data Classification: <span style="color:#db1919;">${parsed.data_classification}</span></div>`;
        if(parsed.overall_risk_rating)
            content += `<div style="margin-top:10px;font-weight:700;">Overall Risk Rating: <span style="color:${(parsed.overall_risk_rating||'').toLowerCase()==='high'?'#ee2222':(parsed.overall_risk_rating||'').toLowerCase()==='medium'?'#ffae42':(parsed.overall_risk_rating||'').toLowerCase()==='low'?'#73db21':'#36d399'}">${parsed.overall_risk_rating}</span></div>`;
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
    } else {
        $('#llm-modal-content').text(text);
    }
    $('#llm-modal').show();
};

$(function(){
    renderEntityDropdown();
    buildPyramid('');
    $('#entitySelect').on('change', function(){
        buildPyramid($(this).val());
        $('#fileListPanel').hide();
    });
});
</script>
@endsection