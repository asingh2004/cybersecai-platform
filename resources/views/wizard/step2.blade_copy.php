@extends('template')

@push('styles')
<style>
    .btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
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
                        <h2><strong>Step 2: Select Applicable PII Regulations</strong></h2>
                        <form method="POST" action="{{ route('wizard.step2.post') }}" id="regulationForm">
                            @csrf
                            <table class="table table-bordered" id="standards-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" id="checkAll"></th>
                                        <th>Standard</th>
                                        <th>Jurisdiction/ Country</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($standards as $standard)
                                        <tr data-id="{{ $standard->id }}">
                                            <td>
                                                <input type="checkbox"
                                                    class="regulation-checkbox"
                                                    name="regulations[]"
                                                    value="{{ $standard->id }}"
                                                    {{ in_array($standard->id, old('regulations', $selected ?? [])) ? 'checked' : '' }}>
                                            </td>
                                            <td>{{ $standard->standard }}</td>
                                            <td>{{ $standard->jurisdiction }}</td>
                                            <td>{{ $standard->detailed_jurisdiction_notes }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @error('regulations')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            <!-- Fields displayed here -->
                            <div id="fields-section" class="my-4"></div>

                            <!-- Hidden JSON field for submitting to controller -->
                            <input type="hidden" id="regulations_json" name="regulations_json" />

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <a href="{{ route('wizard.step1') }}" class="btn btn-lg btn-primary">
                                        &#8592; Back
                                    </a>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-lg btn-primary">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function() {
    // Build ID-to-name and ID-to-fields maps
    var idToStandard = {};
    var idToFields = {};
    var idToJurisdiction = {}; // <-- Added

    @foreach($standards as $standard)
        idToStandard[{{ $standard->id }}] = @json($standard->standard);
        idToFields[{{ $standard->id }}] = {!! is_array($standard->fields) ? json_encode($standard->fields) : ($standard->fields ? $standard->fields : '[]') !!};
        idToJurisdiction[{{ $standard->id }}] = @json($standard->jurisdiction);
    @endforeach

    // Used for restoring previously selected and fields (when editing)
    var defaultRegJson = [];
    @if(isset($config) && !empty($config->regulations))
        defaultRegJson = @json(json_decode($config->regulations, true));
    @endif

    function renderFields() {
        var selected = [];
        $('.regulation-checkbox:checked').each(function(){
            selected.push($(this).val());
        });

        var $fieldsDiv = $("#fields-section");
        $fieldsDiv.empty();

        if (!selected.length) {
            $fieldsDiv.html('<div class="alert alert-light">Select a regulation above to view its fields.</div>');
            return;
        }

        var restoreMap = {};
        if (Array.isArray(defaultRegJson)) {
            defaultRegJson.forEach(function(stObj){
                restoreMap[stObj.standard] = stObj;
            });
        }

        var table = $('<table class="table table-sm table-bordered"><thead><tr><th width="200">Regulation</th><th>Fields</th></tr></thead><tbody></tbody></table>');

        selected.forEach(function(id){
            var standard = idToStandard[id];
            var jurisdiction = idToJurisdiction[id];
            var fieldsArr = [];

            // If editing and have saved field values, use those
            if (restoreMap.hasOwnProperty(standard)
                && (Array.isArray(restoreMap[standard]['High Risk fields']) || Array.isArray(restoreMap[standard]['Medium Risk fields']) || Array.isArray(restoreMap[standard]['fields']))
            ) {
                // (use all fields present under any key)
                if (Array.isArray(restoreMap[standard]['fields'])) {
                    fieldsArr = restoreMap[standard]['fields'];
                } else {
                    fieldsArr = [].concat(
                        restoreMap[standard]['High Risk fields'] || [],
                        restoreMap[standard]['Medium Risk fields'] || []
                    );
                }
            } else {
                // Fallback: use 'fields' from standard def; support array or JSON string
                let raw = idToFields[id] || '[]';
                try {
                    fieldsArr = typeof raw === "string" ? JSON.parse(raw) : raw;
                } catch(e) {
                    // fallback if it's a comma list
                    fieldsArr = raw.split(",").map(e => e.trim()).filter(Boolean);
                }
            }

            var $tr = $('<tr></tr>');
            $tr.append('<td><strong>' + standard + '</strong><br><span style="font-size:0.9em;color:#666;">'+jurisdiction+'</span></td>');
            $tr.append(
                '<td>' +
                (fieldsArr.length
                  ? '<ul>' + fieldsArr.map(field =>
                      (typeof field === 'object' && field.label ? '<li>'+field.label+'</li>' : '<li>'+field+'</li>')).join('')
                  + '</ul>' : '<em>No fields defined.</em>') +
                '</td>');
            table.append($tr);
        });
        $fieldsDiv.append(table);
    }

    // Check/uncheck all
    $("#checkAll").on('change', function() {
        $('.regulation-checkbox').prop('checked', $(this).is(':checked'));
        renderFields();
    });
    $('.regulation-checkbox').on('change', function() {
        renderFields();
    });

    // On submit, populate regulations_json hidden with standard, jurisdiction, fields as array
    $('#regulationForm').on('submit', function() {
        var regs = [];
        $('.regulation-checkbox:checked').each(function(){
            var id = $(this).val();
            var standard = idToStandard[id];
            var jurisdiction = idToJurisdiction[id];
            var raw = idToFields[id] || '[]';
            var fieldsArr = [];
            try {
                fieldsArr = typeof raw === "string" ? JSON.parse(raw) : raw;
            } catch(e) {
                fieldsArr = raw.split(",").map(e => e.trim()).filter(Boolean);
            }
            regs.push({
                'standard': standard,
                'jurisdiction': jurisdiction,
                'fields': fieldsArr
            });
        });
        $("#regulations_json").val(JSON.stringify(regs));
    });

    // Initial load: restore previous selection if editing
    renderFields();
});
</script>
@endpush