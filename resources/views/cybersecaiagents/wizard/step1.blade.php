
@extends('cybersecaiagents.wizard.chatlayout')
@section('chat-body')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">

                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">

  <form class="ds-chat-form" method="POST" action="{{ route('cybersecaiagents.step1Post') }}">
    @csrf
    <div class="chat-bubble agent-bubble">Select your main data source:</div>
    @foreach($sources as $source)
      <label class="chat-select-option">
        <input type="radio" name="data_source_id" value="{{$source->id}}" required>
        <b>{{$source->data_source_name}}</b>
        <span class="chat-option-desc">{{$source->description}}</span>
      </label>
    @endforeach
    <button type="submit" class="chat-next-btn">Continue →</button>
  </form>
                      
                  </div></div></div></div></div></div>
@endsection