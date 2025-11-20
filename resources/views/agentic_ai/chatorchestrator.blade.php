@extends('template')

@push('styles')
<style>
/* Optional: only minor styles; all critical bits are inline below so globals can't override them */
.chat-modern .followups-panel .followup-btn { border-radius: 10px; font-weight:700; }
#chat-window::-webkit-scrollbar { width: 10px; }
#chat-window::-webkit-scrollbar-track { background: transparent; }
#chat-window::-webkit-scrollbar-thumb { background: rgba(120,120,120,.3); border-radius: 8px; }
#chat-window:hover::-webkit-scrollbar-thumb { background: rgba(120,120,120,.6); }
</style>
@endpush

@section('main')
<div class="col-md-10">
  <div class="main-panel min-height mt-4 chat-modern">
    <div class="row">
      <div class="margin-top-85">
        <div class="row m-0">
          @include('users.sidebar')

          <div class="col-md-9 mt-4 mt-sm-0 pl-4 pr-4">
            <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin:0 0 12px;">
              <div>
                <h1 style="margin:0;font-size:1.7rem;font-weight:800;letter-spacing:.4px;background:linear-gradient(90deg,#aee9ff,#5bd4ff 35%,#8b5cf6 75%);-webkit-background-clip:text;background-clip:text;color:transparent;">
                  mochanai Chat Orchestrator
                </h1>
                <p style="margin:6px 0 0;color:#6b7280;font-size:.98rem;">Talk to your data using our multi-persona expert agents.</p>
              </div>
            </div>

            <div class="alert alert-info mb-4">
              <b>About the Orchestrator:</b>
              <ul class="mb-1 mt-2">
                <li>Supports multiple personas — Board Member, Auditor, Data Privacy & Compliance Lawyer, Compliance Officer, Cyber Security Expert, Data Steward & more</li>
                <li>Jumpstart your chat by selecting a tile and adjusting your query</li>
              </ul>
            </div>

            <div class="row usecase-btn-area">
              @php
                $grouped = [];
                foreach($useCases as $c) { $grouped[$c['persona']][] = $c; }
                $personaNames = array_keys($grouped); sort($personaNames);
                $outlineColors = [
                    'border-primary bg-primary bg-opacity-10',
                    'border-success bg-success bg-opacity-10',
                    'border-danger bg-danger bg-opacity-10',
                    'border-warning bg-warning bg-opacity-10',
                    'border-info bg-info bg-opacity-10',
                    'border-secondary bg-secondary bg-opacity-10'
                ];
              @endphp

              <div class="col-12 mb-2">
                <h5 class="mt-4 mb-2 fw-bold text-primary">Data Breach Compliance Lawyer</h5>
                <div class="p-2 mb-3 rounded-3 border-dark bg-light bg-opacity-25" style="border-left:8px solid #333;min-height:60px;">
                  <div class="d-flex flex-wrap">
                    <div class="me-2 mb-2" style="min-width:210px;">
                      <a href="{{ route('agentic_ai.compliance') }}" class="btn btn-outline-dark w-100" style="font-weight:600;">
                        Conduct Quick Preliminary Data Breach Assessment
                      </a>
                    </div>
                    <div class="me-2 mb-2" style="min-width:210px;">
                      <a href="{{ route('databreach.events.create') }}" class="btn btn-outline-danger w-100" style="font-weight:600;">
                        Initiate Formal Data Breach Process
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              @foreach($personaNames as $idx => $persona)
                @if(!empty($grouped[$persona]))
                  @php $clr = $outlineColors[$idx % count($outlineColors)]; @endphp
                  <div class="col-12 mb-2">
                    <h5 class="mt-4 mb-2 fw-bold text-primary">{{ $persona }}</h5>
                    <div class="p-2 mb-3 rounded-3 {{ $clr }}" style="border-left:8px solid;min-height:60px;">
                      <div class="d-flex flex-wrap">
                        @foreach($grouped[$persona] as $c)
                          <div class="me-2 mb-2" style="min-width:210px;">
                            <button class="btn btn-outline-primary use-case-btn w-100"
                              type="button"
                              data-persona="{{ $c['persona'] }}"
                              data-prompt="{{ $c['prompt'] }}"
                              data-operation="{{ $c['operation'] ?? '' }}"
                              data-args='@json($c['args'] ?? [])'
                              data-config_ids='@json($c['config_ids'] ?? [])'
                              title="{{ $c['alt'] }}">
                              {{ $c['label'] }}
                            </button>
                          </div>
                        @endforeach
                      </div>
                    </div>
                  </div>
                @endif
              @endforeach
            </div>

            <form id="guardrail-form" style="display:none" class="mb-3">
              <label for="persona">Choose your persona/context:</label>
              <select id="persona" name="persona" class="form-select">
                @foreach($personas as $p => $desc)
                  <option value="{{ $p }}">{{ $p }} ({{ $desc }})</option>
                @endforeach
                <option value="All">All (Integrated compliance + cyber)</option>
                <option value="Auditor/Security">Auditor/Security (Forensics and risk mapping)</option>
              </select>
            </form>

            <!-- Chat card with enforced brand border -->
            <section class="card" aria-live="polite" style="
              position:relative;border-radius:16px;overflow:hidden;padding:0;
              border: 3px solid #1877c2; box-shadow: 0 0 0 3px rgba(24,119,194,.12), inset 0 0 0 1px rgba(54,211,153,.12);
              background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
            ">
              <div style="background:#ffffff;">
                <div id="chat-window" class="chat-window" style="
                  height: 65vh; max-height: 760px; min-height: 420px;
                  overflow-y: auto; padding:16px; background: transparent; scroll-behavior:smooth;
                "></div>
              </div>

              <div style="padding:12px;border-top:1px solid rgba(24,119,194,.28);background: rgba(24,119,194,0.03);">
                <div style="display:grid;grid-template-columns: 1fr auto;gap:10px;align-items:flex-start;">
                  <div style="width:100%;">
                    <input type="text" id="query-input" class="form-control" placeholder="Select a Query from the top or start typing to see suggestions ..." autocomplete="off"
                      style="width:100%;padding:12px 14px;border-radius:12px;min-height:46px;border:1px solid rgba(24,119,194,.35);background:rgba(24,119,194,.08);">
                    <div id="prompt-suggestions" class="mb-2"></div>
                  </div>
                  <div style="display:flex;gap:8px;">
                    <button type="button" id="send-btn" style="
                      display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:800;letter-spacing:.3px;font-size:1.05rem;line-height:1.2;
                      padding:12px 18px;border-radius:12px;cursor:pointer;border:none;min-height:44px;
                      background: linear-gradient(90deg, #1877c2, #36d399); color:#041018;
                    ">Send</button>
                    <button type="button" id="clear-btn" title="Clear chat" style="
                      display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:800;letter-spacing:.3px;font-size:1.05rem;line-height:1.2;
                      padding:12px 18px;border-radius:12px;cursor:pointer;border:2px solid rgba(54,211,153,.5);min-height:44px;
                      background: rgba(54,211,153,.12); color:#0f1720;
                    ">Clear</button>
                  </div>
                </div>
                <small class="text-muted d-block mt-2">Tip: Press Enter to send, Shift+Enter for a newline.</small>
              </div>
            </section>

            <input type="hidden" id="session_id">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>

<script>
window.allUseCases = @json($useCases);

// Avatars
const ASSISTANT_AVATAR = @json(asset('public/front/images/home/secure_data.svg')); // cybersecai icon
const USER_AVATAR = @json(Auth::check() ? (Auth::user()->profile_src ?? asset('public/images/default-avatar.png')) : asset('public/images/default-avatar.png'));
</script>

<script>
let messages = [];
let session_id = null;
let prior_context = {};

const md = window.markdownit({ html: true, linkify: true, breaks: true, typographer: true });

const slotFieldLabels = {
  event_type: { label: "Event Type", example: "Example: Accidental data sharing, Exfiltration of data, Unauthorized export, Ransomware attack" },
  data: { label: "Data Description / Details", example: "Example: Name, TFN, Medicare Number, Address, Passport Details, DL, Patient Record, Student Record" },
  standard: { label: "Compliance Standard (auto-filled)", example: "E.g.: GDPR, Australian Privacy Act" },
  jurisdiction: { label: "Jurisdiction (Country/Law, auto-filled)", example: "E.g.: Australia, UK, USA, EU, Canada" },
  corporate_domains: { label: "Corporate Email Domains, needed to establish external threat", example: "Example: cybersecai.io, myopenai.com" },
  domain: { label: "Domain (Target to test)", example: "Example: cybersecai.io" },
  region: { label: "Region / Country / Jurisdiction", example: "Example: Australia, UK, USA, Germany, Canada" }
};

// Render with inline layout so nothing can override it
function renderChat() {
  const el = document.getElementById('chat-window');
  el.innerHTML = '';

  messages.forEach(m => {
    const row = document.createElement('div');
    row.className = 'chat-row';
    row.style.cssText = 'display:grid;grid-template-columns:32px auto;column-gap:12px;align-items:flex-start;margin-bottom:12px;';

    const avatar = document.createElement('div');
    avatar.style.cssText = 'width:32px;height:32px;border-radius:50%;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.15);';
    const img = document.createElement('img');
    img.src = m.role === 'user' ? USER_AVATAR : ASSISTANT_AVATAR;
    img.alt = m.role === 'user' ? 'You' : 'cybersecai';
    img.width = 32; img.height = 32;
    img.style.cssText = (m.role === 'user')
      ? 'width:32px;height:32px;object-fit:cover;border-radius:50%;display:block;'
      : 'width:32px;height:32px;object-fit:contain;border-radius:50%;display:block;background:#fff;';
    img.onerror = function(){ this.src = '{{ asset('public/images/default-avatar.png') }}'; };
    avatar.appendChild(img);

    const bubble = document.createElement('div');
    bubble.style.cssText = 'display:inline-block;padding:12px 14px;border-radius:12px;line-height:1.55;max-width:85%;word-break:break-word;border:1px solid;';
    if (m.role === 'user') {
      bubble.style.background = 'rgba(54,211,153,.08)';
      bubble.style.borderColor = 'rgba(54,211,153,.45)';
      bubble.style.color = '#0f1720';
    } else {
      bubble.style.background = 'rgba(24,119,194,.08)';
      bubble.style.borderColor = 'rgba(24,119,194,.45)';
      bubble.style.color = '#0f1720';
    }

    const name = document.createElement('div');
    name.textContent = m.role === 'user' ? 'You' : (m.sender || 'cybersecai');
    name.style.cssText = 'font-size:.78rem;font-weight:700;color:#6b7280;margin-bottom:6px;display:block;';

    const body = document.createElement('div');
    const htmlSafeContent = m.role === 'ai' ? md.render(m.content || '') : md.renderInline(m.content || '');
    body.innerHTML = htmlSafeContent;

    bubble.appendChild(name);
    bubble.appendChild(body);

    row.appendChild(avatar);
    row.appendChild(bubble);
    el.appendChild(row);
  });

  el.scrollTop = el.scrollHeight;
}

// Suggestions (unchanged except DOM targets)
function showPromptSuggestions(inputText) {
  const container = document.getElementById('prompt-suggestions');
  if (!window.allUseCases || !Array.isArray(window.allUseCases)) {
    container.style.display = 'none';
    return;
  }
  container.innerHTML = '';

  let matches = [];
  if (!inputText || inputText.length < 2) {
    matches = window.allUseCases.slice(0, 5);
    if (matches.length === 0) { container.style.display = 'none'; return; }
    container.style.display = '';
    const head = document.createElement('div');
    head.textContent = 'Try a popular prompt:';
    head.style.cssText = 'margin-bottom:6px;color:#3949ab;font-weight:600;';
    container.appendChild(head);

    matches.forEach(usecase => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = usecase.label;
      b.className = 'prompt-suggestion-btn';
      b.style.cssText = 'display:inline-block;margin:0 8px 8px 0;padding:.5em 1em;border-radius:12px;border:1px solid rgba(24,119,194,.35);background:rgba(24,119,194,.12);font-weight:700;';
      b.dataset.persona = usecase.persona;
      b.dataset.prompt = (usecase.prompt || '');
      b.dataset.operation = (usecase.operation || '');
      b.dataset.args = JSON.stringify(usecase.args || {});
      b.dataset.config_ids = JSON.stringify(usecase.config_ids || []);
      b.title = usecase.alt || '';
      b.onclick = function() {
        document.getElementById('persona').value = this.dataset.persona;
        document.getElementById('query-input').value = this.dataset.prompt;
        document.getElementById('query-input').focus();
        prior_context = { persona: this.dataset.persona };
        if (this.dataset.operation) {
          prior_context.operation = this.dataset.operation;
          if (this.dataset.operation === 'm365_compliance_auto') prior_context.agent = 'm365_compliance_auto';
          if (this.dataset.operation === 'pentest_auto') prior_context.agent = 'pentest_auto';
        }
        try { prior_context.args = JSON.parse(this.dataset.args); } catch(e) { prior_context.args = {}; }
        try { prior_context.config_ids = JSON.parse(this.dataset.config_ids); } catch(e) { prior_context.config_ids = []; }
        container.style.display = 'none';
      };
      container.appendChild(b);
    });
  } else {
    matches = window.allUseCases.filter(u =>
      (u.prompt && u.prompt.toLowerCase().includes(inputText.toLowerCase())) ||
      (u.label && u.label.toLowerCase().includes(inputText.toLowerCase()))
    ).slice(0, 5);

    if (matches.length === 0) { container.style.display = 'none'; return; }
    container.style.display = '';
    const head = document.createElement('div');
    head.textContent = 'Did you mean:';
    head.style.cssText = 'margin-bottom:6px;color:#3949ab;font-weight:600;';
    container.appendChild(head);

    matches.forEach(usecase => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = usecase.label;
      b.className = 'prompt-suggestion-btn';
      b.style.cssText = 'display:inline-block;margin:0 8px 8px 0;padding:.5em 1em;border-radius:12px;border:1px solid rgba(24,119,194,.35);background:rgba(24,119,194,.12);font-weight:700;';
      b.dataset.persona = usecase.persona;
      b.dataset.prompt = (usecase.prompt || '');
      b.dataset.operation = (usecase.operation || '');
      b.dataset.args = JSON.stringify(usecase.args || {});
      b.dataset.config_ids = JSON.stringify(usecase.config_ids || []);
      b.title = usecase.alt || '';
      b.onclick = function() {
        document.getElementById('persona').value = this.dataset.persona;
        document.getElementById('query-input').value = this.dataset.prompt;
        document.getElementById('query-input').focus();
        prior_context = { persona: this.dataset.persona };
        if (this.dataset.operation) {
          prior_context.operation = this.dataset.operation;
          if (this.dataset.operation === 'm365_compliance_auto') prior_context.agent = 'm365_compliance_auto';
          if (this.dataset.operation === 'pentest_auto') prior_context.agent = 'pentest_auto';
        }
        try { prior_context.args = JSON.parse(this.dataset.args); } catch(e) { prior_context.args = {}; }
        try { prior_context.config_ids = JSON.parse(this.dataset.config_ids); } catch(e) { prior_context.config_ids = []; }
        container.style.display = 'none';
      };
      container.appendChild(b);
    });
  }
}

document.addEventListener('DOMContentLoaded', function(){
  const input = document.getElementById('query-input');

  input.addEventListener('input', function() { showPromptSuggestions(this.value); });
  input.addEventListener('focus', function() { showPromptSuggestions(this.value); });

  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('send-btn').click();
    }
  });

  document.querySelectorAll('.use-case-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('persona').value = this.dataset.persona;
      document.getElementById('query-input').value = this.dataset.prompt;
      document.getElementById('query-input').focus();

      prior_context = { persona: this.dataset.persona };
      if (this.dataset.operation) {
        prior_context.operation = this.dataset.operation;
        if (this.dataset.operation === 'm365_compliance_auto') prior_context.agent = 'm365_compliance_auto';
        if (this.dataset.operation === 'pentest_auto') prior_context.agent = 'pentest_auto';
      }
      try { prior_context.args = JSON.parse(this.dataset.args); } catch(e) { prior_context.args = {}; }
      try { prior_context.config_ids = JSON.parse(this.dataset.config_ids); } catch(e) { prior_context.config_ids = []; }
    });
  });

  document.getElementById('clear-btn').addEventListener('click', function() {
    messages = [];
    session_id = null;
    prior_context = {};
    document.getElementById('chat-window').innerHTML = '';
    input.value = '';
    input.placeholder = "Select a Query from the top or start typing to see suggestions ...";
    const lbl = document.getElementById('user-prompt-label'); if (lbl) lbl.remove();
    const sug = document.getElementById('prompt-suggestions'); if (sug) { sug.innerHTML = ''; sug.style.display = 'none'; }
    input.focus();
  });
});

document.getElementById('send-btn').onclick = function(e) {
  e.preventDefault();
  let queryInput = document.getElementById('query-input');
  let query = queryInput.value;

  queryInput.value = "";
  queryInput.placeholder = "Select a Query from the top or start typing to see suggestions ...";
  showPromptSuggestions("");

  queryInput.readOnly = true;
  setTimeout(() => { queryInput.readOnly = false; }, 800);

  if (!query.trim()) return;
  let persona = document.getElementById('persona').value;

  if (prior_context && prior_context.pending_field) {
    prior_context.args = prior_context.args || {};
    prior_context.args[prior_context.pending_field] = query;
    delete prior_context.pending_field;
    let oldLab = document.getElementById('user-prompt-label'); if (oldLab) oldLab.remove();
  }

  if (prior_context.operation === 'pentest_auto') {
    if (!prior_context.args || !prior_context.args.domain || !prior_context.args.domain.trim()) {
      const slotInfo = { label: "Domain to Pentest", example: "e.g., example.com" };
      let lbl = document.getElementById('user-prompt-label');
      if (!lbl) {
        lbl = document.createElement('div');
        lbl.id = 'user-prompt-label';
        lbl.textContent = slotInfo.label + ':';
        lbl.style.cssText = 'color:#365;font-size:1em;font-weight:600;margin-bottom:2px;';
        queryInput.parentNode.insertBefore(lbl, queryInput);
      }
      queryInput.placeholder = slotInfo.example;
      prior_context.pending_field = 'domain';
      queryInput.focus();
      return;
    }
  }

  messages.push({role: 'user', content: query, sender: 'You'});
  renderChat();

  let sendBtn = document.getElementById('send-btn');
  sendBtn.disabled = true;
  sendBtn.textContent = "Awaiting cybersecai Agent's Response";

  if (!session_id || Object.keys(prior_context).length === 0) {
    prior_context['persona'] = persona;
  }

  fetch('{{ route("chatorchestrator.orchestrate") }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    body: JSON.stringify({ query: query, prior_context: prior_context, session_id: session_id, messages: messages }),
  })
  .then(r => r.json())
  .then(resp => {
    let ai = resp.ai || {};
    const assistantName =
      (ai.prior_context && (ai.prior_context.persona || ai.prior_context.agent)) ||
      (prior_context && (prior_context.persona || prior_context.agent)) ||
      'cybersecai';

    messages.push({role: 'ai', content: ai.content, sender: assistantName});
    renderChat();

    sendBtn.disabled = false;
    sendBtn.textContent = "Send";

    queryInput.placeholder = "Select a Query from the top or start typing to see suggestions ...";
    showPromptSuggestions('');
    queryInput.focus();

    if (ai.prior_context) { prior_context = ai.prior_context; }

    if (ai.pending) {
      let lbl = document.getElementById('user-prompt-label');
      if (!lbl) {
        lbl = document.createElement('div');
        lbl.id = 'user-prompt-label';
        lbl.style.cssText = 'color:#365;font-size:1em;font-weight:600;margin-bottom:2px;';
        queryInput.parentNode.insertBefore(lbl, queryInput);
      }
      lbl.textContent = 'Please provide required info:';
      queryInput.placeholder = '';
    } else {
      session_id = null;
      prior_context = {};
      const oldLab = document.getElementById('user-prompt-label'); if (oldLab) oldLab.remove();
    }

    document.querySelectorAll('.followups-panel').forEach(el => el.remove());
    if (ai.followups && Array.isArray(ai.followups) && ai.followups.length > 0) {
      let followupsDiv = document.createElement("div");
      followupsDiv.className = "followups-panel mt-3";
      followupsDiv.innerHTML = "<b>What would you like to do next?</b><br>";
      ai.followups.forEach(fu => {
        let btn = document.createElement('button');
        btn.className = "btn btn-outline-success ms-2 followup-btn";
        btn.textContent = fu.label;
        btn.onclick = function() {
          prior_context['operation'] = fu.operation;
          prior_context['args'] = fu.args || {};
          prior_context['config_ids'] = fu.config_ids || [];
          document.getElementById('query-input').value = fu.prompt || fu.label;
          document.getElementById('send-btn').click();
        };
        followupsDiv.appendChild(btn);
      });
      const cw = document.getElementById('chat-window');
      cw.appendChild(followupsDiv);
      cw.scrollTop = cw.scrollHeight;
    }
  })
  .catch(err => {
    messages.push({role: 'ai', content: 'ERROR: ' + err, sender: 'cybersecai'});
    renderChat();
    sendBtn.disabled = false;
    sendBtn.textContent = "Send";
  });
};
</script>
@endsection