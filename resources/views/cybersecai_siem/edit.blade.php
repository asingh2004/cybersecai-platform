@extends('template')

@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">

                        <!-- User Guide -->
                        <div class="alert alert-secondary py-2 px-3 mb-4">
                            <b>User Guide</b><br>
                            Select your SIEM, review/adjust how CyberSecAI fields map to SIEM event fields, and enter destination info. “Download Test Sample” or “Test Connection” will use your current mapping. Save when finished.
                        </div>

                        <h2>SIEM Export Profile for "{{ implode(', ', $sources) }}"</h2>

                        @if($siemDataMapping && count($siemDataMapping))
                        <div class="alert alert-info">
                            <strong>Available source fields for SIEM mapping:</strong>
                            <table class="table table-sm mt-2">
                                <thead>
                                    <tr><th>CyberSecAI Field</th><th>Source JSON Field</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($siemDataMapping as $asField => $fromSource)
                                    <tr>
                                        <td><code>{{ $asField }}</code></td>
                                        <td><code>{{ $fromSource }}</code></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif

                        <!-- Main SIEM Config Form -->
                        <form method="POST" action="{{ route('cybersecai_siem.update', $dataConfig->id) }}" id="mainSiemForm">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label><strong>SIEM Type</strong></label>
                                <select class="form-control" name="siem[siem_ref_id]" id="siemTypeSelect" required>
                                    @foreach($siemTypes as $sid => $label)
                                        <option value="{{ $sid }}" {{ ($currentSiemRefId == $sid) ? 'selected' : ''}}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label><strong>Export Format</strong></label>
                                <input type="text" class="form-control" name="siem[format]" id="siemExportFormat"
                                       value="{{ $siemProfiles[$currentSiemRefId]['format'] ?? $currentProfile['format'] ?? ''}}" readonly>
                            </div>

                            <div class="mb-3">
                                <label><strong>Field Mapping</strong></label>
                                <div class="table-responsive">
                                    <table class="table table-sm" id="fieldMappingTable">
                                        <thead>
                                            <tr>
                                                <th>CyberSecAI Field</th>
                                                <th>Your SIEM Event Field</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($siemDataMapping as $yourField => $fromSource)
                                            <tr>
                                                <td><code>{{ $yourField }}</code></td>
                                                <td>
                                                    <input type="text" class="form-control"
                                                        name="siem[field_map][{{ $yourField }}]"
                                                        value="{{ $currentProfile['field_map'][$yourField]
                                                            ?? $siemProfiles[$currentSiemRefId]['template_field_map'][$yourField]
                                                            ?? $yourField }}">
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Edit SIEM field names as needed. This mapping controls which SIEM fields receive your file attributes.</small>
                            </div>

                            <div class="mb-3">
                                <label><strong>Destination Settings</strong></label>
                                <div class="row">
                                    <div class="col">
                                        <input class="form-control" placeholder="Endpoint/Host/URL" name="siem[dest][url]"
                                            value="{{ $currentProfile['dest']['url'] ?? '' }}">
                                    </div>
                                    <div class="col">
                                        <input class="form-control" placeholder="Port" name="siem[dest][port]"
                                            value="{{ $currentProfile['dest']['port'] ?? '' }}">
                                    </div>
                                    <div class="col">
                                        <input class="form-control" placeholder="Auth/Token" name="siem[dest][token]"
                                            value="{{ $currentProfile['dest']['token'] ?? '' }}">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button class="btn btn-primary" type="submit">Save Config</button>
                                <a href="{{ route('wizard.dashboard') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>

                        <!-- Download Test Sample as its own POST form -->
                                         
                      
                      	<form method="POST" action="{{ route('cybersecai_siem.sample', $dataConfig->id) }}">
    					@csrf
    						<button class="btn btn-info" type="submit">Download Test Sample</button>
						</form>

                        <!-- Test Connection as its own POST form -->
                        <form method="POST" action="{{ route('cybersecai_siem.test', $dataConfig->id) }}" id="siemTestForm" style="display:inline;">
                            @csrf

                            <button class="btn btn-success" type="submit">Test Connection</button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.BASE_FIELD_MAP = @json($siemDataMapping);
window.CURRENT_FIELD_MAP = @json($currentProfile['field_map'] ?? []);
window.SIEM_PROFILES = @json($siemProfiles);

function refreshFieldMapping(siemId) {
    let baseMap = window.BASE_FIELD_MAP || {};
    let tplMap = (window.SIEM_PROFILES[siemId] && window.SIEM_PROFILES[siemId]['template_field_map']) || {};
    let currMap = window.CURRENT_FIELD_MAP || {};
    let table = document.getElementById('fieldMappingTable').getElementsByTagName('tbody')[0];
    table.innerHTML = '';
    for (const [yourField, fromSource] of Object.entries(baseMap)) {
        let siemField =
            (currMap[yourField] !== undefined && currMap[yourField] !== null && currMap[yourField] !== '')
                ? currMap[yourField]
                : (tplMap[yourField] ?? yourField);
        let row = table.insertRow();
        let c1 = row.insertCell(0); let c2 = row.insertCell(1);
        c1.innerHTML = `<code>${yourField}</code>`;
        c2.innerHTML = `<input type="text" class="form-control" name="siem[field_map][${yourField}]" value="${siemField}">`;
    }
}

// This will copy the current mapping table into one hidden input per field.
// Thus you get a real associative array in $_POST, not a stringified JSON of mapping.
function copyFieldMapToForm(formId) {
    const form = document.getElementById(formId);

    // remove any previous dynamically-added mapping/format fields
    [...form.querySelectorAll('input.siempush-hidden')].forEach(e => e.parentNode.removeChild(e));
    // field map
    document.querySelectorAll('#fieldMappingTable input[name^="siem[field_map]"]').forEach(function(input){
        let field = input.name.match(/\[([^\]]+)\]/)[1];
        let hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'siem[field_map]['+field+']';
        hidden.value = input.value;
        hidden.classList.add('siempush-hidden');
        form.appendChild(hidden);
    });
    // format
    let formatHidden = document.createElement('input');
    formatHidden.type = 'hidden';
    formatHidden.name = 'siem[format]';
    formatHidden.value = document.getElementById('siemExportFormat').value;
    formatHidden.classList.add('siempush-hidden');
    form.appendChild(formatHidden);
}

document.addEventListener("DOMContentLoaded", function() {
    let sel = document.getElementById('siemTypeSelect');
    let formatBox = document.getElementById('siemExportFormat');
    sel.addEventListener('change', function() {
        let siemId = this.value;
        fetch('/api/siemref/' + siemId)
        .then(resp => resp.json())
        .then(ref => {
            if(formatBox && ref.format) formatBox.value = ref.format;
            if(ref.template_field_map) window.SIEM_PROFILES[siemId]['template_field_map'] = ref.template_field_map;
            refreshFieldMapping(siemId);
        });
    });
    refreshFieldMapping(sel.value);

    document.getElementById('siemSampleForm').addEventListener('submit', function(e){
        copyFieldMapToForm('siemSampleForm');
    });
    document.getElementById('siemTestForm').addEventListener('submit', function(e){
        copyFieldMapToForm('siemTestForm');
    });
});
</script>
@endpush