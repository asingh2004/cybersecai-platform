@extends('cybersecaiagents.wizard.chatlayout')
@section('chat-body')
<ol class="chatlog" id="chatlog">
  @foreach($messages ?? [] as $m)
    <li>
      @if($m['role'] === 'agent')
        <span class="chat-bubble agent-bubble">{!! $m['content'] !!}</span>
      @else
        <span class="chat-bubble user-bubble">{{ $m['content'] }}</span>
      @endif
    </li>
  @endforeach
</ol>
<!-- Form for user input -->
<form id="chat-form" autocomplete="off" method="POST" action="{{ route('cybersecaiagents.agentStep') }}">
    @csrf
    <div style="display:flex; align-items:center;">
        <input name="chat_input" id="chat-text" type="text" class="chat-input" autofocus autocomplete="off" placeholder="Type your instructions..." />
        <input type="hidden" name="step" value="{{ $step }}" />
        <button type="submit" class="chat-next-btn" id="chat-send-btn" style="margin-left:7px;">Send</button>
    </div>
</form>
<div id="typing-ind" style="display:none;" class="typing-indicator">  
    <span class="typing-dot"></span>
    <span class="typing-dot"></span>
    <span class="typing-dot"></span>
    <span style="color:#b9eaca">&nbsp;Agent is typing...</span>
</div>
<script>
let form = document.getElementById('chat-form');
form.addEventListener('submit',function(e){
    e.preventDefault();
    let chatInput = document.getElementById('chat-text');
    let input = chatInput.value.trim();
    if(!input) return;
    let chatlog = document.getElementById('chatlog');
    chatlog.innerHTML += `<li><span class="chat-bubble user-bubble">${input}</span></li>`;
    chatInput.value = '';
    document.getElementById('typing-ind').style.display = 'inline-block';

    // AJAX to backend
    fetch("{{ route('cybersecaiagents.agentChat') }}",{
        method:'POST', credentials:'same-origin',
        headers:{'X-CSRF-TOKEN': '{{csrf_token()}}','Content-Type':'application/json'}, 
        body:JSON.stringify({"chat_input":input,"step":"{{ $step }}"})
    }).then(r=>r.json()).then(data=>{
        setTimeout(()=>{ // simulate AI typing delay
            document.getElementById('typing-ind').style.display='none';
            chatlog.innerHTML += `<li><span class="chat-bubble agent-bubble">${data.reply}</span></li>`;
            chatlog.scrollTop = chatlog.scrollHeight; // scroll to bottom
        }, 900 + Math.random()*1100);
    });
    chatlog.scrollTop = chatlog.scrollHeight;
});
</script>
@endsection