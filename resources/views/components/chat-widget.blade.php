@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* Scope everything to our widget */
.csai-offcanvas { --bs-offcanvas-width: min(420px, calc(100vw - 2rem)); }
#csai-toggle {
  position: fixed; right: 1rem; bottom: 1rem; z-index: 1061;
  width: 56px; height: 56px;
}
#csai-chat-window { scrollbar-width: thin; }
#csai-chat-window::-webkit-scrollbar { width: 8px; }
#csai-chat-window::-webkit-scrollbar-thumb { background: rgba(0,0,0,.2); border-radius: 6px; }
/* Messages */
.csai-msg { display:grid; grid-template-columns:36px 1fr; gap:.5rem; margin-bottom:.75rem; }
.csai-avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.12); background:#fff; }
.csai-bubble { border:1px solid var(--bs-border-color); border-radius:.75rem; padding:.6rem .75rem; max-width:88%; }
.csai-bubble-user { background: rgba(25,135,84,.08); border-color: rgba(25,135,84,.35); }
.csai-bubble-ai   { background: rgba(13,110,253,.07); border-color: rgba(13,110,253,.30); }
.csai-name { font-size:.75rem; font-weight:600; color: var(--bs-secondary-color); margin-bottom:.25rem; }
/* Markdown content refinements */
.csai-bubble pre, .csai-bubble code { font-family: var(--bs-font-monospace, ui-monospace, SFMono-Regular, Menlo, Consolas, monospace); }
.csai-bubble pre { background: var(--bs-light); padding:.6rem .75rem; border-radius:.5rem; overflow:auto; }
.csai-bubble code { background: rgba(0,0,0,.06); padding:.1rem .35rem; border-radius:.25rem; }
.csai-bubble a { color: var(--bs-primary); text-decoration: underline; }
/* Suggestions */
.csai-suggestion { margin: 0 .5rem .5rem 0; }
/* Typing indicator */
.csai-typing .dot { width:6px; height:6px; background: var(--bs-secondary-color); border-radius:50%; display:inline-block; margin-right:4px; animation: csai-bounce 1.2s infinite ease-in-out; }
.csai-typing .dot:nth-child(2){ animation-delay:.15s; } .csai-typing .dot:nth-child(3){ animation-delay:.3s; }
@keyframes csai-bounce { 0%,80%,100% { transform: scale(0.8); opacity:.5 } 40% { transform: scale(1); opacity:1 } }
@media (max-width: 576px) {
  .csai-offcanvas { --bs-offcanvas-width: 100vw; }
  #csai-toggle { right: .75rem; bottom: .75rem; width:52px; height:52px; }
  .csai-bubble { max-width: 100%; }
}
</style>
@endpush

<div id="csai-root"
     data-orchestrate-url="{{ route('chatorchestrator.orchestrate') }}"
     data-bootstrap-url="{{ route('chatorchestrator.bootstrap') }}"
     data-csrf="{{ csrf_token() }}"
     data-assistant-avatar="{{ asset('public/front/images/home/secure_data.svg') }}"
     data-user-avatar="{{ Auth::check() ? (Auth::user()->profile_src ?? asset('public/images/default-avatar.png')) : asset('public/images/default-avatar.png') }}">

  <!-- Floating button -->
  <button id="csai-toggle"
          class="btn btn-primary rounded-circle shadow d-flex align-items-center justify-content-center"
          type="button"
          data-bs-toggle="offcanvas" data-bs-target="#csai-offcanvas"
          aria-controls="csai-offcanvas" aria-label="Open chat">
    <i class="bi bi-chat-text-fill fs-4"></i>
  </button>

  <!-- Offcanvas panel -->
  <div class="offcanvas offcanvas-end csai-offcanvas" tabindex="-1" id="csai-offcanvas" aria-labelledby="csai-title" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
      <div class="d-flex align-items-center gap-2">
        <img src="{{ asset('public/front/images/home/secure_data.svg') }}" width="28" height="28" class="rounded bg-white border" alt="cybersecai">
        <div>
          <h6 class="offcanvas-title mb-0 fw-bold" id="csai-title">cybersecai Chat Orchestrator</h6>
          <small class="text-secondary">Multi‑persona expert agents</small>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column" style="height:min(75vh, 760px);">
      <div id="csai-chat-window" class="flex-grow-1 overflow-auto p-3 bg-body"></div>

      <div class="border-top p-2">
        <div id="csai-suggestions" class="px-1 mb-2"></div>
        <div class="input-group">
          <textarea id="csai-query-input" class="form-control" rows="1" placeholder="Type a message. Shift+Enter = newline"></textarea>
          <button id="csai-send" class="btn btn-primary">
            <i class="bi bi-send-fill me-1"></i>
            <span class="d-none d-sm-inline">Send</span>
          </button>
          <button id="csai-clear" class="btn btn-outline-secondary" title="Clear">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <input type="hidden" id="csai-persona" value="All">
        <small class="text-muted d-block mt-1">Enter to send, Shift+Enter for newline.</small>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
<script>
(function(){
  const ROOT = document.getElementById('csai-root'); if (!ROOT) return;

  const DATA = ROOT.dataset;
  const OFFCANVAS = document.getElementById('csai-offcanvas');

  const W = document.getElementById('csai-chat-window');
  const SUG = document.getElementById('csai-suggestions');
  const INPUT = document.getElementById('csai-query-input');
  const SEND = document.getElementById('csai-send');
  const CLEAR = document.getElementById('csai-clear');
  const PERSONA = document.getElementById('csai-persona');

  const md = window.markdownit({ html:true, linkify:true, breaks:true, typographer:true });

  let messages = []; let session_id = null; let prior_context = {}; let personas = {}; let useCases = [];
  const ASSISTANT_AVATAR = DATA.assistantAvatar;
  const USER_AVATAR = DATA.userAvatar;

  // restore
  try { messages = JSON.parse(localStorage.getItem('csai_chat_msgs') || '[]'); } catch(e){ messages = []; }
  function persist(){ try{ localStorage.setItem('csai_chat_msgs', JSON.stringify(messages)); }catch(e){} }

  function bubble(role, sender, contentHTML){
    const row = document.createElement('div');
    row.className = 'csai-msg';
    const av = document.createElement('div');
    av.className = 'csai-avatar';
    const img = document.createElement('img');
    img.src = role === 'user' ? USER_AVATAR : ASSISTANT_AVATAR;
    img.alt = role === 'user' ? 'You' : (sender || 'cybersecai');
    img.width = 36; img.height = 36; img.style.cssText = 'width:36px;height:36px;object-fit:cover;display:block;';
    img.onerror = function(){ this.src = "{{ asset('public/images/default-avatar.png') }}"; };
    av.appendChild(img);

    const bubble = document.createElement('div');
    bubble.className = 'csai-bubble ' + (role === 'user' ? 'csai-bubble-user' : 'csai-bubble-ai');

    const name = document.createElement('div');
    name.className = 'csai-name';
    name.textContent = role === 'user' ? 'You' : (sender || 'cybersecai');

    const body = document.createElement('div');
    body.innerHTML = contentHTML;

    bubble.appendChild(name); bubble.appendChild(body);
    row.appendChild(av); row.appendChild(bubble);
    return row;
  }

  function renderChat(){
    W.innerHTML = '';
    messages.forEach(m => {
      const html = (m.role === 'ai') ? md.render(m.content || '') : md.renderInline(m.content || '');
      W.appendChild(bubble(m.role, m.sender, html));
    });
    W.scrollTop = W.scrollHeight; persist();
  }

  function showTyping(){
    const row = document.createElement('div');
    row.className = 'csai-msg csai-typing';
    row.innerHTML = `
      <div class="csai-avatar"></div>
      <div class="csai-bubble csai-bubble-ai">
        <div class="csai-name">cybersecai</div>
        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
      </div>`;
    W.appendChild(row); W.scrollTop = W.scrollHeight; return row;
  }

  function showSuggestions(inputText){
    SUG.innerHTML = '';
    if (!useCases.length) return;

    let matches = [];
    const head = document.createElement('div');
    head.className = 'text-primary fw-semibold mb-1';

    if (!inputText || inputText.trim().length < 2) {
      matches = useCases.slice(0,5);
      if (!matches.length) return;
      head.textContent = 'Try a popular prompt:';
      SUG.appendChild(head);
    } else {
      matches = useCases.filter(u => {
        const t = inputText.toLowerCase();
        return (u.prompt && u.prompt.toLowerCase().includes(t)) || (u.label && u.label.toLowerCase().includes(t));
      }).slice(0,5);
      if (!matches.length) return;
      head.textContent = 'Did you mean:';
      SUG.appendChild(head);
    }

    matches.forEach(u => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm btn-outline-primary csai-suggestion';
      b.textContent = u.label;
      b.title = u.alt || '';
      b.onclick = () => {
        PERSONA.value = u.persona || 'All';
        INPUT.value = u.prompt || '';
        prior_context = { persona: PERSONA.value };
        if (u.operation) {
          prior_context.operation = u.operation;
          if (u.operation === 'm365_compliance_auto') prior_context.agent = 'm365_compliance_auto';
          if (u.operation === 'pentest_auto') prior_context.agent = 'pentest_auto';
        }
        try { prior_context.args = u.args || {}; } catch(e){ prior_context.args = {}; }
        try { prior_context.config_ids = u.config_ids || []; } catch(e){ prior_context.config_ids = []; }
        INPUT.focus();
      };
      SUG.appendChild(b);
    });
  }

  // Auto-resize textarea
  function autoResize(){
    INPUT.style.height = 'auto';
    INPUT.style.height = Math.min(140, INPUT.scrollHeight) + 'px';
  }
  INPUT.addEventListener('input', autoResize);

  // Events
  OFFCANVAS.addEventListener('shown.bs.offcanvas', function(){
    renderChat(); showSuggestions(''); setTimeout(()=>INPUT.focus(), 60);
  });

  INPUT.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      SEND.click();
    }
  });
  INPUT.addEventListener('focus', function(){ showSuggestions(this.value); });
  INPUT.addEventListener('input', function(){ showSuggestions(this.value); });

  CLEAR.addEventListener('click', function(){
    messages = []; session_id = null; prior_context = {};
    INPUT.value = ''; autoResize(); W.innerHTML = ''; showSuggestions(''); INPUT.focus(); persist();
  });

  function needPentestSlot(){
    if (prior_context.operation === 'pentest_auto') {
      if (!prior_context.args || !prior_context.args.domain || !prior_context.args.domain.trim()) {
        INPUT.placeholder = 'Domain to Pentest (e.g., example.com)';
        prior_context.pending_field = 'domain';
        INPUT.focus();
        return true;
      }
    }
    return false;
  }

  SEND.addEventListener('click', function(e){
    e.preventDefault();
    let query = INPUT.value;
    if (!query || !query.trim()) return;

    // pending slot fill
    if (prior_context && prior_context.pending_field) {
      prior_context.args = prior_context.args || {};
      prior_context.args[prior_context.pending_field] = query;
      delete prior_context.pending_field;
    }

    if (needPentestSlot()) return;

    INPUT.value = ''; autoResize(); showSuggestions('');

    messages.push({ role:'user', content: query, sender:'You' });
    renderChat();

    SEND.disabled = true; SEND.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Waiting';

    if (!session_id || Object.keys(prior_context).length === 0) {
      prior_context.persona = PERSONA.value || 'All';
    }

    const typing = showTyping();

    fetch(DATA.orchestrateUrl, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': DATA.csrf, 'Accept':'application/json' },
      body: JSON.stringify({ query, prior_context, session_id, messages })
    })
    .then(r => r.json())
    .then(resp => {
      typing.remove();
      const ai = resp.ai || {};
      const assistantName =
        (ai.prior_context && (ai.prior_context.persona || ai.prior_context.agent)) ||
        (prior_context && (prior_context.persona || prior_context.agent)) || 'cybersecai';

      messages.push({ role:'ai', content: ai.content, sender: assistantName });
      renderChat();

      SEND.disabled = false; SEND.innerHTML = '<i class="bi bi-send-fill me-1"></i><span class="d-none d-sm-inline">Send</span>';
      INPUT.focus();

      if (ai.prior_context) prior_context = ai.prior_context;
      if (!ai.pending) { session_id = null; prior_context = {}; }

      // Followups
      const existing = W.querySelector('.csai-followups'); if (existing) existing.remove();
      if (ai.followups && Array.isArray(ai.followups) && ai.followups.length) {
        const wrap = document.createElement('div');
        wrap.className = 'csai-followups mt-2';
        wrap.innerHTML = '<div class="small text-secondary mb-1">What would you like to do next?</div>';
        ai.followups.forEach(fu => {
          const b = document.createElement('button');
          b.className = 'btn btn-sm btn-outline-success me-2 mb-2';
          b.textContent = fu.label;
          b.onclick = () => {
            prior_context.operation = fu.operation;
            prior_context.args = fu.args || {};
            prior_context.config_ids = fu.config_ids || [];
            INPUT.value = fu.prompt || fu.label;
            autoResize();
            SEND.click();
          };
          wrap.appendChild(b);
        });
        W.appendChild(wrap);
        W.scrollTop = W.scrollHeight;
      }
    })
    .catch(err => {
      typing.remove();
      messages.push({ role:'ai', content: 'ERROR: ' + err, sender:'cybersecai' });
      renderChat();
      SEND.disabled = false; SEND.innerHTML = '<i class="bi bi-send-fill me-1"></i><span class="d-none d-sm-inline">Send</span>';
    });
  });

  // Bootstrap data once when opened the first time
  let bootstrapped = false;
  OFFCANVAS.addEventListener('show.bs.offcanvas', function(){
    if (bootstrapped) return;
    bootstrapped = true;
    fetch(DATA.bootstrapUrl, { headers: { 'Accept':'application/json' }})
      .then(r => r.json())
      .then(data => {
        useCases = data.useCases || [];
        personas = data.personas || {};
        if (!PERSONA.value || PERSONA.value === 'All') {
          const keys = Object.keys(personas);
          if (keys.length) PERSONA.value = keys[0];
        }
      })
      .catch(() => {});
  });

  // Render persisted immediately if any
  if (messages.length) renderChat();
})();
</script>
@endpush