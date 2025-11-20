@extends('template')
@section('main')
<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                    @include('users.sidebar')
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
    <h3>Step 1: Select a Data Source</h3>
    <form method="POST" action="{{ route('cybersecaiagents.step1Post') }}">
        @csrf
        @foreach($sources as $source)
        <div>
            <input type="radio" id="dsrc{{$source->id}}" name="data_source_id" value="{{$source->id}}" required>
            <label for="dsrc{{$source->id}}"><b>{{$source->data_source_name}}</b> &mdash; {{$source->description}}</label>
        </div>
        @endforeach
        <button class="btn btn-success mt-3" type="submit">Next</button>
    </form>
</div></div></div></div></div></div>
@endsection