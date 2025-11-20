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
<h2>Step 3: Choose Metadata that will be stored for Files that contain Sensitive Data</h2>

<div class="mb-3">
    <strong>Purpose:</strong> This metadata can be used to enable automated policy enforcement, access controls, DLP, audit, and reporting.
    <ul>
        <li>The system will tag files with metadata that describes:
            <ul>
                <li>What standards regulate the file’s contents</li>
                <li>The specific risk level and types of PII/fields present</li>
                <li>Other control tags (e.g., classification, owner)</li>
            </ul>
        </li>
    </ul>
</div>

<form method="POST" action="{{ route('wizard.step3.post') }}">
    @csrf

    <div class="mb-4">
        <label><strong>Select which Metadata to store for files containing Sensitive Data:</strong></label>
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th style="width:60px;"><input type="checkbox" id="selectAll" checked> All</th>
                    <th>Metadata Key</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            @foreach($allKeys as $meta)
                <tr>
                    <td>
                        <input type="checkbox"
                               class="meta-checkbox"
                               name="metadata_keys[]"
                               value="{{ $meta->id }}"
                               {{ in_array($meta->id, $selectedKeys) ? 'checked' : '' }}>
                    </td>
                    <td><code>{{ $meta->key }}</code></td>
                    <td>{{ $meta->description }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @error('metadata_keys')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
    </div>
    <div class="d-flex justify-content-between align-items-center btn-xxl mb-3">
        <div>
            <a href="{{ route('wizard.step2') }}" class="btn btn-primary">
                &#8592; Back
            </a>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">
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
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    const selectAll = document.getElementById('selectAll');
    if(selectAll) {
        selectAll.addEventListener('change', function() {
            let checkboxes = document.querySelectorAll('.meta-checkbox');
            checkboxes.forEach(function(box) {
                box.checked = selectAll.checked;
            });
        });
    }
});
</script>
@endpush