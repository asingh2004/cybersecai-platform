@extends('template')
@push('styles')
<style>
    .btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
    .table-classification th, .table-classification td { vertical-align: middle; }
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
<h2>Step 3: Data Classification Levels</h2>

<p>
    For each standard data classification below, you can optionally enter your preferred internal tag/label.
    If left empty, the standard label will be used for that level.
</p>

<form method="POST" action="{{ route('wizard.step3.post') }}">
    @csrf

    <table class="table table-bordered table-classification">
        <thead class="thead-light">
            <tr>
                <th>#</th>
                <th>Standard Name</th>
                <th>Examples</th>
                <th>Access Control</th>
                <th>Preferred Tag (optional)</th>
            </tr>
        </thead>
        <tbody>
        @foreach($levels as $level)
            <tr>
                <td>{{ $level['id'] }}</td>
                <td>{{ $level['name'] }}</td>
                <td>{{ $level['example'] }}</td>
                <td>{{ $level['access'] }}</td>
                <td>
                    <input type="text"
                        name="preferred_tags[{{ $level['id'] }}]"
                        class="form-control"
                        maxlength="100"
                        value="{{ old('preferred_tags.' . $level['id'], $preferredTags[$level['id']] ?? '') }}"
                        placeholder="e.g., MyCompany Confidential"/>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @error('preferred_tags.*')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="d-flex justify-content-between align-items-center btn-xxl mb-3">
        <div>
            <a href="{{ route('wizard.step2') }}" class="btn btn-primary">&#8592; Back</a>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Next</button>
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