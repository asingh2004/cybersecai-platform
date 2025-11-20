@extends('template')

@section('main')

 <div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                        <div>Step 1: Select Data Source</div>
                        @php
                            // Normalize selection to single string for radio button
                            $selectedValue = old('data_sources', $selected ?? '');
                            if (is_array($selectedValue)) {
                                $selectedValue = $selectedValue[0] ?? '';
                            }
                        @endphp
                        <form method="POST" action="{{ route('wizard.step1.post') }}">
                            @csrf
                            @foreach($sources as $source)
                                <div class="mb-2">
                                    <label>
                                        <input
                                            type="radio"
                                            name="data_sources"
                                            value="{{ $source }}"
                                            {{ $selectedValue == $source ? 'checked' : '' }}>
                                        {{ $source }}
                                    </label>
                                </div>
                            @endforeach
                            @error('data_sources')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-primary">Next</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection