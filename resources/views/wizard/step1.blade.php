@extends('template')

<style>
  	.btn-xxl { font-size: 1.6rem; padding: 1rem 2.5rem; }
</style>

@section('main')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                      <h2><bold>Step 1: Select Data Source</bold></h2>
                        @php
                            // Normalize selection to single string for radio button
                            $selectedValue = old('data_sources', $selected ?? '');
                            if (is_array($selectedValue)) {
                                $selectedValue = $selectedValue[0] ?? '';
                            }
                        @endphp

                        <form method="POST" action="{{ route('wizard.step1.post') }}">
                            @csrf

                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Select</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($sources as $source)
                                    <tr
                                        style="cursor:pointer;"
                                        onclick="document.getElementById('radio_{{ $loop->index }}').checked = true;"
                                    >
                                        <td class="align-middle text-center">
                                            <input
                                                type="radio"
                                                id="radio_{{ $loop->index }}"
                                                name="data_sources"
                                                value="{{ $source->data_source_name }}"
                                                {{ $selectedValue == $source->data_source_name ? 'checked' : '' }}
                                            >
                                        </td>
                                        <td class="align-middle">{{ $source->data_source_name }}</td>
                                        <td class="align-middle text-muted" style="font-size:0.96em;">{{ $source->description }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @error('data_sources')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-primary btn-lg mt-3">Next</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection