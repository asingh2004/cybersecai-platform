@extends('template')
@push('styles')
<style>
    .chat-window { border: 1px solid #aaa; padding: 2rem; height: 500px; overflow-y: auto; background: #fafbff;}
    .chat-message.user { text-align: right; color: #1a237e; margin-bottom: 1em;}
    .chat-message.ai { text-align: left; color: #00897b; font-weight: 600; margin-bottom: 1em;}
    .chat-message .bubble {
        background: #f5f5fc;
        display: inline-block;
        border-radius: 12px;
        padding: 1em 1.2em;
        max-width: 80%;
        word-break: break-word;
        box-shadow: 0 1px 6px rgba(0,0,0,0.08);
    }
    .chat-message.user .bubble { background: #e3f2fd; }
    .chat-message.ai .bubble  { background: #e2f8f3;}

    /* Markdown styles */
    .chat-message ul, .chat-message ol { margin-left:1em;}
    .chat-message pre { background:#222; color:#FFF; padding:0.5em 1em; border-radius:8px;}
    .chat-message code { background:#eee; padding:2px 6px; border-radius:4px;}
    .chat-message h1, .chat-message h2, .chat-message h3 { margin-top:1em; }
    .usecase-btn-area {margin-bottom:1.5em;}
    .usecase-btn {margin: 0.25em 0.5em 0.25em 0; white-space:normal;}
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
                        <h2>CybersecAI Secure Chatbot</h2>
                        <!-- USE CASE BUTTONS/CARDS -->
                        <h4>Jumpstart With a Scenario</h4>
                      	<p style="font-size:1em; color:#3258b1; margin-bottom:22px;">
  							You can ask: <span style="background:#fff url('') no-repeat 0 0;padding:2px 6px;border-radius:5px;">Show the forensic audit trail for high-risk files changed today</span> or 
  							<span style="background:#fff;padding:2px 6px;border-radius:5px;">Show the forensic audit trail for high-risk files changed on 01 July 2025</span>
						</p>
                        <div class="row usecase-btn-area">
    @foreach($useCases as $c)
        <div class="col-auto mb-2" style="min-width:210px;">
            <button class="btn btn-outline-primary use-case-btn usecase-btn"
                type="button"
                data-persona="{{ $c['persona'] }}"
                data-prompt="{{ $c['prompt'] }}"
                tabindex="0"
                title="{{ $c['alt'] }}">
                {{ $c['label'] }}
            </button>
        </div>
    @endforeach
</div>
                        <!-- END USE CASE BUTTONS -->
                        <form id="guardrail-form" class="mb-3">
                            <label for="persona">Choose your persona/context:</label>
                            <select id="persona" name="persona" class="form-select">
                                @foreach($personas as $p => $desc)
                                    <option value="{{ $p }}">{{ $p }} ({{ $desc }})</option>
                                @endforeach
                              	<option value="All">All (Integrated compliance + cyber)</option>
                              	<option value="Auditor/Security">Auditor/Security (Forensics and risk mapping)</option>
                            </select>
                        </form>
                        <div id="chat-window" class="chat-window mb-3"></div>
                        <div class="chat-controls">
                            <input type="text" id="query-input" class="form-control" placeholder="Type your question..." autocomplete="off">
                            <button class="btn btn-success mt-2" id="send-btn">Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- markdown-it for client-side markdown -->
<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
<script>
let messages = [];
const md = window.markdownit({
    html: true, linkify: true, breaks: true, typographer: true
});

function renderChat() {
    const el = document.getElementById('chat-window');
    el.innerHTML = '';
    messages.forEach(m => {
        let div = document.createElement('div');
        div.className = 'chat-message ' + m.role;
        let htmlSafeContent = m.content;
        if(m.role === 'ai') {
            htmlSafeContent = md.render(m.content || '');
        } else {
            htmlSafeContent = md.renderInline(m.content || '');
        }
        div.innerHTML = `<div class="bubble">${htmlSafeContent}</div>`;
        el.appendChild(div);
    });
    el.scrollTop = el.scrollHeight;
}

// Use-case button logic
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.use-case-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('persona').value = this.dataset.persona;
            document.getElementById('query-input').value = this.dataset.prompt;
            document.getElementById('query-input').focus();
        });
    });
});

document.getElementById('send-btn').onclick = function(e) {
    e.preventDefault();
    let query = document.getElementById('query-input').value;
    if (!query.trim()) return;
    let persona = document.getElementById('persona').value;
    messages.push({role: 'user', content: query});
    renderChat();
    document.getElementById('query-input').value = '';
    fetch('{{ route('agentic.chatbot.post') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ persona: persona, query: query, messages: messages }),
    }).then(r => r.json())
    .then(resp => {
        if(resp.reply_html) {
            messages.push({role: 'ai', content: resp.reply_html});
        } else {
            messages.push({role: 'ai', content: resp.reply || 'No reply.'});
        }
        renderChat();
    }).catch(err => {
        messages.push({role: 'ai', content: 'ERROR: ' + err});
        renderChat();
    });
};
</script>
@endsection
