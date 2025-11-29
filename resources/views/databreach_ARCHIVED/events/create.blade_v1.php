@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
<h1><strong>Log Compliance Event (Breach, DSAR etc)</strong></h1>
<form method="post" action="{{ route('gdpr.events.store') }}">
    @csrf
    <label>Standard:<br>
  <select name="standard_id" id="standard_id" required>
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
</label>
    <br>
    <label>Type:
      <select name="event_type" required>
         <option value="breach" {{ old('event_type')=='breach'?'selected':'' }}>Breach</option>
         <option value="dsar" {{ old('event_type')=='dsar'?'selected':'' }}>DSAR</option>
      </select>
    </label>
    <br><br>
    <div id="dynamic-fields"></div>
    <button type="submit">Log Event & Trigger AI</button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>

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
        html = '<div style="color: darkred;">No fields defined for this standard.</div>';
    }
    fields.forEach(function(f){
        const fname = f.name;
        const flabel = f.label || fname;
        const isReq = f.required ? 'required' : '';
        let type = 'text';
        if(/date|time/i.test(fname)) type = 'datetime-local';
        else if(/email/i.test(fname)) type = 'email';
        else if(/number|count|affected/i.test(fname)) type = 'number';
        else if(/desc|notes/i.test(fname)) type = 'textarea';

        let val = safeValue(oldInput[fname]);
        if(type === 'textarea') {
            html += `<label>${flabel}<br><textarea name="data[${fname}]" ${isReq}>${val}</textarea></label><br>`;
        } else {
            html += `<label>${flabel} <input name="data[${fname}]" type="${type}" ${isReq} value="${val}" /></label><br>`;
        }
    });
    document.getElementById('dynamic-fields').innerHTML = html;
}

// Bind and initialize
document.getElementById('standard_id').onchange = renderDynamicFields;
document.addEventListener('DOMContentLoaded', function() {
    // If there is a selected standard (old or default), trigger rendering
    var sel = document.getElementById('standard_id');
    if (sel.value) sel.dispatchEvent(new Event('change'));
});
</script>

@endsection