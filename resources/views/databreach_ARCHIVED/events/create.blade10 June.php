@extends('template')

@push('styles')
<style>
    .form-section {
        background: #f7fafd;
        border-radius: 12px;
        padding: 32px 32px 16px 32px;
        margin-bottom: 2rem;
        box-shadow: 0px 2px 8px #e2e8f0;
    }
    .dynamic-field label {
        font-weight: 500;
        margin-bottom: 0.3rem;
        display: block;
    }
    .dynamic-field input,
    .dynamic-field textarea,
    .dynamic-field select {
        display: block;
        margin-bottom: 18px;
        min-width: 240px;
    }
    .dynamic-field textarea { min-height: 68px; }
    .dynamic-required { color: #c00; }
    .form-title { font-size: 2rem; margin-bottom: 1.5rem; }
    .btn-lg-custom {
        font-size: 1.25rem;
        padding: 0.7rem 2.1rem;
    }
    .alert { margin-top: 10px; }
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
                            <h1 class="form-title text-primary">
                                <strong><i class="fa fa-clipboard-list"></i> Log Compliance Event (Breach, DSAR etc)</strong>
                            </h1>

                            @if(session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="post" action="{{ route('gdpr.events.store') }}" autocomplete="off">
                                @csrf

                                <div class="mb-3">
                                    <label class="fw-bold">Standard:</label>
                                    <select class="form-select" name="standard_id" id="standard_id" required>
                                        <option value="">--Pick--</option>
                                        @foreach ($standards as $std)
                                            <option value="{{ $std->id }}"
                                                data-fields='{!! json_encode(is_array($std->compliance_fields) ? $std->compliance_fields : json_decode($std->compliance_fields, true)) !!}'
                                                @if(old('standard_id') == $std->id) selected @endif
                                            >
                                                {{ $std->jurisdiction }} - {{ $std->standard }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Type:</label>
                                    <select class="form-select" name="event_type" required>
                                        <option value="breach" {{ old('event_type') == 'breach' ? 'selected' : '' }}>Breach</option>
                                        <option value="dsar" {{ old('event_type') == 'dsar' ? 'selected' : '' }}>DSAR</option>
                                    </select>
                                </div>

                                <div id="dynamic-fields" class="dynamic-field"></div>

                                <button type="submit" class="btn btn-primary btn-lg btn-lg-custom mt-3">
                                    <i class="fa fa-magic"></i> Log Event & Trigger AI
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Robust old input capture
const oldInput = {!! json_encode(old('data') ?? (object)[]) !!};

// Helper for safe value
function safeValue(v) {
    if(typeof v!=='undefined' && v!==null) {
        return String(v).replace(/"/g, '&quot;');
    }
    return '';
}

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
    // Render dynamic input fields
    fields.forEach(function(f){
        const fname = f.name;
        const flabel = f.label || fname;
        const isReq = f.required ? 'required' : '';
        const requiredStar = f.required ? '<span class="dynamic-required">*</span>' : '';
        let type = 'text';
        if(/date|time/i.test(fname)) type = 'datetime-local';
        else if(/email/i.test(fname)) type = 'email';
        else if(/number|count|affected/i.test(fname)) type = 'number';
        else if(/desc|notes/i.test(fname)) type = 'textarea';

        let val = safeValue(oldInput[fname]);
        if(type === 'textarea') {
            html += `<div class="mb-2">
                <label>${flabel} ${requiredStar}</label>
                <textarea class="form-control" name="data[${fname}]" ${isReq}>${val}</textarea>
            </div>`;
        } else {
            html += `<div class="mb-2">
                <label>${flabel} ${requiredStar}</label>
                <input class="form-control" name="data[${fname}]" type="${type}" ${isReq} value="${val}" />
            </div>`;
        }
    });
    document.getElementById('dynamic-fields').innerHTML = html;
}

document.getElementById('standard_id').onchange = renderDynamicFields;
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('standard_id');
    if (sel.value) sel.dispatchEvent(new Event('change'));
});
</script>
@endpush

@endsection