@extends('template')

@push('styles')
<style>
    .form-section {
        background: #f7fafd;
        border-radius: 14px;
        padding: 32px 32px 20px 32px;
        margin-bottom: 2rem;
        box-shadow: 0px 2px 14px #e2e8f0;
        border: 1px solid #e9ecef;
    }
    .dynamic-field label {
        font-weight: 500;
        margin-bottom: .35rem;
        color: #35507a;
    }
    .dynamic-required { color: #c00; font-size: 1.15em; margin-left: 3px; vertical-align: middle;}
    .help-tip {
        margin-left: 8px;
        color: #17a2b8;
        cursor: pointer;
        font-size: 1em;
        vertical-align: middle;
    }
    .form-title { font-size: 2rem; margin-bottom: 1.5rem; color: #2267a7;}
    .btn-lg-custom {
        font-size: 1.15rem;
        padding: 0.7rem 2.1rem;
    }
    .alert { margin-top: 10px; }
    .tooltip-inner {
        max-width: 350px;
        padding: 8px 10px;
        font-size: 1.05em;
        background: #335;
    }
</style>
@endpush

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                  <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <div class="form-section">
                            <h1 class="form-title">
                                       
                                <strong>World-Class Privacy/Compliance officer AI Agent</strong>
                            </h1>
     
 <div class="alert alert-info mb-4">

                              
    <b>About Data Breach - Privacy/Compliance officer</b> <br>
    <h4> * When you select the jurtisdiction where data breach occurred, the agent will prompt you to enter details relevant to that jurisdiction. Onc eyou provide basic information, the AI Agent Assesor will provide Fine-grained, action-by-action, draft-by-draft compliance outputs—no over- or under-notification risk. <br><br>
    * The Assessor will:<br>
    <ul class="mb-1">
        <li>+ Analyze the event and input data to determine the privacy risk.</li>
        <li>+  Explicitly recommend all next steps </li>
        <li>+ Will generate complete report and letters, including notification for affected data subjects if applicable.</li>
      </ul></h4>
    <small class="text-muted">
        Your inputs remain confidential and cybersecai.io agent’s results help ensure you meet your compliance obligations quickly and accurately.
    </small>
</div>
                          

                            <div id="ai-markdown-output"></div>
                            <div id="form-errors" class="alert alert-danger" style="display:none"></div>

                            <form method="post" id="event-form" action="{{ route('databreach.events.store') }}" autocomplete="off">
                                @csrf
                                <div class="mb-3">
                                    <label class="fw-bold">
                                        Standard
                                        <i class="fa fa-info-circle help-tip"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="right"
                                            title="Choose the jurisdiction/standard type. Selecting a standard will load its specific required fields."></i>
                                    </label>
                                    <select class="form-select" name="standard_id" id="standard_id" required>
                                        <option value="">--Pick--</option>
                                        @foreach ($standards as $std)
                                            <option value="{{ $std->id }}"
                                                data-fields='@json(is_array($std->compliance_fields) ? $std->compliance_fields : json_decode($std->compliance_fields, true))'>
                                                {{ $std->jurisdiction }} - {{ $std->standard }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="fw-bold">Event Type</label>
                                    <select class="form-select" name="event_type" required>
                                        <option value="breach">Breach</option>
                                        <option value="dsar">DSAR</option>
                                    </select>
                                </div>
                                <div id="dynamic-fields"></div>
                                <button type="submit" id="submit-btn" class="btn btn-primary btn-lg btn-lg-custom mt-3">
                                    <i class="fa fa-bolt"></i> Hand-off to Agentic AI 
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
<script>
const md = window.markdownit({ html: true, linkify: true, breaks: true });
function safeValue(v) { return (typeof v !== 'undefined' && v !== null) ? String(v).replace(/"/g, '&quot;') : ''; }

// Dynamic Fields
function renderDynamicFields() {
    let sel = document.getElementById('standard_id');
    if (!sel.value) {
        document.getElementById('dynamic-fields').innerHTML = '';
        return;
    }
    let opts = sel.selectedOptions[0];
    let fields = [];
    try {
        fields = JSON.parse(opts.dataset.fields);
        if (!Array.isArray(fields)) fields = [];
    } catch(e){
        fields = [];
    }
    let html = '';
    if (fields.length === 0) {
        html = '<div class="text-danger">No fields defined for this standard.</div>';
    }
    fields.forEach(function(f){
        const fname = f.name;
        const flabel = f.label || fname;
        const isReq = f.required ? 'required' : '';
        const requiredStar = f.required ? '<span class="dynamic-required" title="Required">*</span>' : '';
        const fieldTip = f.help ? `<i class="fa fa-info-circle help-tip" data-bs-toggle="tooltip" title="${f.help}"></i>` : '';
        let type = 'text';
        if(/date|time/i.test(fname)) type = 'datetime-local';
        else if(/email/i.test(fname)) type = 'email';
        else if(/number|count|affected/i.test(fname)) type = 'number';
        else if(/desc|notes/i.test(fname)) type = 'textarea';

        let val = safeValue('');
        if (type === 'textarea') {
            html += `<div class="mb-2">
                <label>${flabel} ${requiredStar} ${fieldTip}</label>
                <textarea class="form-control" name="data[${fname}]" ${isReq} placeholder="Enter ${flabel}...">${val}</textarea>
            </div>`;
        } else {
            html += `<div class="mb-2">
                <label>${flabel} ${requiredStar} ${fieldTip}</label>
                <input class="form-control" name="data[${fname}]" type="${type}" ${isReq} value="${val}" placeholder="Enter ${flabel}..." />
            </div>`;
        }
    });
    document.getElementById('dynamic-fields').innerHTML = html;
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('#dynamic-fields [data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
}
document.getElementById('standard_id').onchange = renderDynamicFields;
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('standard_id').value) renderDynamicFields();
});

// AJAX Submission
document.getElementById('event-form').onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('submit-btn').disabled = true;
    document.getElementById('ai-markdown-output').innerHTML = '';
    document.getElementById('form-errors').style.display = 'none';

    let formData = new FormData(this), data = {};
    formData.forEach((value, key) => {
        if(key.startsWith('data[')){
            let fname = key.match(/^data\[(.*?)\]$/)[1];
            if(!data['data']) data['data'] = {};
            data['data'][fname] = value;
        } else {
            data[key] = value;
        }
    });

    // Basic guard: ensure standard_id is present
    if (!data.standard_id) {
        document.getElementById('form-errors').style.display='block';
        document.getElementById('form-errors').innerText = "Please select a Standard/Jurisdiction.";
        document.getElementById('submit-btn').disabled = false;
        return;
    }

    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(async r => {
        document.getElementById('submit-btn').disabled = false;
        if (!r.ok) {
            let res = await r.json().catch(()=>({message:'Unknown error'}));
            throw res;
        }
        return r.json();
    })
    .then(resp => {
        if(resp.status !== 'success' || !resp.markdown){
            throw resp.message || 'Unexpected error from server';
        }
        document.getElementById('ai-markdown-output').innerHTML =
            '<div class="alert alert-success mt-4"><h4>AI Output</h4>' + md.render(resp.markdown) + '</div>';
    })
    .catch(async err => {
        document.getElementById('form-errors').style.display='block';
        let errorMsg = "Unknown error.";
        if (err.json) { // error is a Response
            let data = await err.json().catch(()=>null);
            if (data && data.errors) {
                errorMsg = Object.values(data.errors).map(e => `<li>${e}</li>`).join('');
            } else {
                errorMsg = data && data.message ? data.message : JSON.stringify(data);
            }
        } else if (err.message) {
            errorMsg = err.message;
        }
        document.getElementById('form-errors').innerHTML = "<ul>" + errorMsg + "</ul>";
    });
};
</script>
@endpush
@endsection