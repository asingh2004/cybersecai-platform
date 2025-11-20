@extends('cybersecaiagents.wizard.chatlayout')
@section('chat-body')

<div class="col-md-10">
    <div class="main-panel min-height mt-4">
        <div class="row">
            <div class="margin-top-85">
                <div class="row m-0">
                 
                    <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
                      
  <form class="policy-upload-form" method="POST" action="{{route('cybersecaiagents.policySubmit')}}" enctype="multipart/form-data">
    @csrf
    <div class="chat-bubble agent-bubble">Upload your policy document or enter a link below. I'll analyze your controls for compliance and risk automatically.</div>
    <input type="text" name="policy_name" required class="chat-input" placeholder="Policy Name">
    <input type="url" name="policy_url" class="chat-input" placeholder="Policy URL (optional)">
    <input type="file" name="policy_file" class="chat-input">
    <button class="chat-next-btn" type="submit">Save and Continue</button>
  </form>
                      
                  </div></div></div></div></div></div>
@endsection