@extends('template')

@php
  $user = auth()->user();
  $userName  = $user ? ($user->first_name ?? $user->name ?? 'You') : 'You';
  $userEmail = $user ? $user->email : 'guest@example.com';
  $userAvatar = method_exists($user ?? null, 'profile_src') ? ($user->profile_src ?? null) : (property_exists($user ?? (object)[], 'profile_src') ? $user->profile_src : null);
  if (!$userAvatar) {
      $userAvatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($userEmail))) . '?d=identicon&s=64';
  }
  $botName   = (config('app.name') ? config('app.name').' Assistant' : 'AI Assistant');
  $botAvatar = asset('images/logo-icon.png');
@endphp

@push('css')
<style>
  .chat-container { max-width: 980px; margin: 0 auto; }
  .chat-window { border: 1px solid rgba(24,119,194,.28); background: #fff; border-radius: 12px; padding: 16px; height: 60vh; min-height: 420px; max-height: 760px; overflow-y: auto; scroll-behavior: smooth; }

  .chat-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
  .chat-row.user { flex-direction: row-reverse; }
  .chat-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(0,0,0,.08); background:#fff; }
  .chat-meta { font-size: 12px; color: #6b7280; margin-bottom: 4px; }

  .chat-bubble { padding: 10px 14px; border-radius: 12px; margin-bottom: 4px; max-width: 720px; line-height: 1.5; border: 1px solid; word-break: break-word; }
  .chat-bubble.bot { background: rgba(24,119,194,.08); color: #0f1720; border-color: rgba(24,119,194,.45); }
  .chat-bubble.user { background: rgba(54,211,153,.08); color: #0f1720; border-color: rgba(54,211,153,.45); }

  .chat-input { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: flex-start; margin-top: 10px; }
  .quick-replies { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
  .quick-replies .chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(24,119,194,.35); background: rgba(24,119,194,.10); cursor: pointer; }
  .quick-replies .chip.active { background: rgba(54,211,153,.15); border-color: rgba(54,211,153,.45); }
  .quick-replies .chip i { font-size: 14px; opacity: .9; }

  .progress-steps { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; align-items: center; }
  .step-pill { padding: 4px 8px; border-radius: 999px; font-size: 0.85rem; border: 1px solid #ddd; background: #fafafa; cursor: pointer; }
  .step-pill.done { border-color: #4caf50; background: #e8f5e9; color: #2e7d32; }
  .step-pill.active { border-color: #3467eb; background: #eaf0ff; color: #1d39c4; }

  .step-controls .btn { padding: 2px 10px; }

  .step-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; background: #fff; }
  .step-card h6 { margin-bottom: 8px; }
  .option-grid { display: flex; flex-wrap: wrap; gap: 6px; }
  .option-grid .btn { border-radius: 14px; }
  .option-grid .btn.selected { border-color: #2e7d32; background: #e8f5e9; color: #2e7d32; }
  .small-muted { font-size: .875rem; color: #6b7280; }
  .brand-card { border-radius:16px; overflow:hidden; padding:0; border: 3px solid #1877c2; box-shadow: 0 0 0 3px rgba(24,119,194,.12), inset 0 0 0 1px rgba(54,211,153,.12); background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01)); }
  .suggestions-head { margin-bottom:6px;color:#3949ab;font-weight:600; }
  .prompt-suggestion-btn { display:inline-block;margin:0 8px 8px 0;padding:.5em 1em;border-radius:12px;border:1px solid rgba(24,119,194,.35);background:rgba(24,119,194,.12);font-weight:700; cursor:pointer; }

  .form-check-input { width: 1.1rem; height: 1.1rem; cursor: pointer; accent-color: #1877c2; }
  .form-check-input:checked { background-color: #1877c2; border-color: #1877c2; }
  .state-icon { margin-left: 6px; font-size: 0.95rem; }
  .state-icon .fa-check { color: #2e7d32; }
  .state-icon .fa-times { color: #c53030; }
</style>
@endpush

@section('main')
<div class="margin-top-85">
  <div class="row m-0">

    @include('users.sidebar')

    <div class="col-lg-10">
      <div class="main-panel">
        <div class="container-fluid container-fluid-90 margin-top-85 min-height d-flex flex-column">
          <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin:0 0 10px;">
            <div>
              <h1 style="margin:0;font-size:1.7rem;font-weight:800;letter-spacing:.4px;background:linear-gradient(90deg,#aee9ff,#5bd4ff 35%,#8b5cf6 75%);-webkit-background-clip:text;background-clip:text;color:transparent;">
                AI Configuration Assistant (Agentic)
              </h1>
              <p class="text-muted" style="margin:6px 0 0;">Use step cards to pick from database values. Your selections appear in chat as "You". Press Send to save and move to the next step.</p>
            </div>
            <div class="step-controls">
              <div class="btn-group" role="group">
                <button type="button" id="prevStepBtn" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</button>
                <button type="button" id="nextStepBtn" class="btn btn-outline-secondary btn-sm">Next<i class="fas fa-arrow-right ms-1"></i></button>
              </div>
              <button type="button" id="startOverBtn" class="btn btn-outline-danger btn-sm ms-2" title="Delete current configuration and start fresh">
                <i class="fas fa-trash me-1"></i>Start over
              </button>
            </div>
          </div>

          <div class="progress-steps" id="progressSteps">
            <span class="step-pill" data-step="1" title="Go to step 1">1. Data Source</span>
            <span class="step-pill" data-step="2" title="Go to step 2">2. Regulations</span>
            <span class="step-pill" data-step="3" title="Go to step 3">3. Metadata</span>
            <span class="step-pill" data-step="4" title="Go to step 4">4. Connection Details</span>
          </div>

          <!-- Step-aware sub-prompts from DB (chips under chat) -->
          <div id="quickReplies" class="quick-replies"></div>

          <!-- Step panel -->
          <div id="stepOptions" class="mb-3"></div>

          <!-- Chat card -->
          <section class="card brand-card" aria-live="polite">
            <div style="background:#ffffff;">
              <div id="chatWindow" class="chat-window"></div>
            </div>

            <div style="padding:12px;border-top:1px solid rgba(24,119,194,.28);background: rgba(24,119,194,0.03);">
              <div id="user-prompt-label" style="display:none;color:#365;font-size:1em;font-weight:600;margin-bottom:2px;">Press Send to save and continue</div>
              <div style="display:grid;grid-template-columns: 1fr auto;gap:10px;align-items:flex-start;">
                <div style="width:100%;">
                  <input type="text" id="chatInput" class="form-control" placeholder="Type to search DB values for this step (e.g., source name, regulation, metadata)..." autocomplete="off"
                    style="width:100%;padding:12px 14px;border-radius:12px;min-height:46px;border:1px solid rgba(24,119,194,.35);background:rgba(24,119,194,.08);">
                  <div id="prompt-suggestions" class="mt-2"></div>
                </div>
                <div style="display:flex;gap:8px;">
                  <button type="button" id="sendBtn" class="btn btn-primary" style="min-width:110px;">Send</button>
                  <button type="button" id="clearBtn" class="btn btn-outline-success">Clear</button>
                </div>
              </div>
              <small class="text-muted d-block mt-2">Tip: Press Enter to send, Shift+Enter for a newline. Click step chips or use Back/Next to navigate steps. Use Start over to reset.</small>
              <div class="mt-2">
                <button class="btn btn-link p-0" id="startBtn">Start configuration</button>
              </div>
            </div>
          </section>

          <input type="hidden" id="sessionId" value="">
          <input type="hidden" id="csrf" value="{{ csrf_token() }}">
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
<script>
  // Server-provided data
  const ORCH_URL         = @json($orchUrl);
  const API_CHAT_URL     = @json(route('agentic.chat'));
  const API_SAVE_URL     = @json(route('agentic.save'));
  const START_MESSAGE    = "start";
  const CATALOG          = @json($catalog);
  const USER_ID          = @json(auth()->id());
  const CONFIG_ID        = @json($config_id ?? null);
  const INITIAL_PROGRESS = @json($initial_progress ?? ['done'=>[], 'active'=>1]);
  const INITIAL_STATE    = @json($initial_state ?? []);
  const INITIAL_SUBPROMPTS = @json($initial_sub_prompts ?? ['type'=>'generic','items'=>[]]);

  const USER_NAME   = @json($userName);
  const USER_AVATAR = @json($userAvatar);
  const BOT_NAME    = @json($botName);
  const BOT_AVATAR  = @json($botAvatar) || 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f916.svg';

  // DOM
  const chatWindow   = document.getElementById('chatWindow');
  const chatInput    = document.getElementById('chatInput');
  const sendBtn      = document.getElementById('sendBtn');
  const clearBtn     = document.getElementById('clearBtn');
  const startBtn     = document.getElementById('startBtn');
  const quickReplies = document.getElementById('quickReplies');
  const sessionIdEl  = document.getElementById('sessionId');
  const csrf         = document.getElementById('csrf').value;
  const stepOptions  = document.getElementById('stepOptions');
  const promptSuggest= document.getElementById('prompt-suggestions');
  const labelPrompt  = document.getElementById('user-prompt-label');
  const stepPills    = document.querySelectorAll('.step-pill');
  const prevStepBtn  = document.getElementById('prevStepBtn');
  const nextStepBtn  = document.getElementById('nextStepBtn');
  const startOverBtn = document.getElementById('startOverBtn');

  // Renderer
  const md = window.markdownit({ html: false, linkify: true, breaks: true, typographer: true });

  // State
  let CURRENT_PROGRESS = INITIAL_PROGRESS || { done: [], active: 1 };
  let CURRENT_STATE    = INITIAL_STATE || {};
  let CURRENT_SUBPROMPTS = INITIAL_SUBPROMPTS || { type: 'generic', items: [] };
  let messages         = [];

  // Pending/UI selections before saving
  let PENDING = { source: null };
  let uiSelectedStandards = new Set(); // numeric ids
  let uiSelectedMetadata  = new Set(); // numeric ids
  let HAS_UNSAVED = false;

  // Maps/helpers from catalog
  const STANDARDS_BY_ID = new Map((CATALOG?.standards || []).map(s => [parseInt(s.id,10), s]));
  const METADATA_BY_ID  = new Map((CATALOG?.metadata_keys || []).map(k => [parseInt(k.id,10), k]));
  const SOURCES_BY_NAME = new Map((CATALOG?.sources || []).map(s => [String(s.name), s]));

  function stdLabelById(id) {
    const s = STANDARDS_BY_ID.get(parseInt(id,10));
    if (!s) return String(id);
    return `${s.standard}${s.jurisdiction ? ' ('+s.jurisdiction+')' : ''}`;
    }
  function metaLabelById(id) {
    const m = METADATA_BY_ID.get(parseInt(id,10));
    return m ? m.key : String(id);
  }

  // Helpers
  function stepTitle(n) {
    switch (parseInt(n, 10)) {
      case 1: return 'Data Source';
      case 2: return 'Regulations';
      case 3: return 'Metadata';
      case 4: return 'Connection Details';
      default: return 'Configuration';
    }
  }

  function sourceIcon(name='') {
    const n = String(name).toLowerCase();
    if (n.includes('m365') || n.includes('microsoft') || n.includes('onedrive') || n.includes('sharepoint')) return 'fab fa-microsoft';
    if (n.includes('google')) return 'fab fa-google-drive';
    if (n.includes('aws') || n.includes('s3')) return 'fab fa-aws';
    if (n.includes('dropbox')) return 'fab fa-dropbox';
    if (n.includes('box')) return 'fas fa-box';
    if (n.includes('github')) return 'fab fa-github';
    return 'fas fa-database';
  }

  function addBubble(text, who='bot', withStepContext=false, stepNum=null) {
    const row = document.createElement('div');
    row.className = 'chat-row ' + (who === 'user' ? 'user' : 'bot');

    const img = document.createElement('img');
    img.className = 'chat-avatar';
    img.src = who === 'user' ? USER_AVATAR : BOT_AVATAR;
    img.alt = who === 'user' ? USER_NAME : BOT_NAME;
    img.onerror = function() { this.onerror = null; this.src = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f916.svg'; };

    const col = document.createElement('div');
    col.style.maxWidth = '80%';

    const meta = document.createElement('div');
    meta.className = 'chat-meta';
    meta.textContent = (who === 'user' ? USER_NAME : BOT_NAME) + (withStepContext ? ` · Step ${stepNum}: ${stepTitle(stepNum)}` : '');

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + (who === 'user' ? 'user' : 'bot');
    bubble.innerHTML = who === 'bot' ? md.render(text || '') : md.renderInline(text || '');

    col.appendChild(meta);
    col.appendChild(bubble);

    row.appendChild(img);
    row.appendChild(col);

    chatWindow.appendChild(row);
    chatWindow.scrollTop = chatWindow.scrollHeight;
  }

  function setProgress(stepStatus) {
    const pills = document.querySelectorAll('.step-pill');
    pills.forEach(p => p.classList.remove('done', 'active'));
    if (stepStatus && stepStatus.done) {
      (stepStatus.done || []).forEach(s => {
        const el = document.querySelector(`.step-pill[data-step="${s}"]`);
        if (el) el.classList.add('done');
      });
    }
    if (stepStatus && stepStatus.active) {
      const el = document.querySelector(`.step-pill[data-step="${stepStatus.active}"]`);
      if (el) el.classList.add('active');
    }
    CURRENT_PROGRESS = stepStatus || CURRENT_PROGRESS;
    renderStepOptions();
    renderSubPrompts(); // fallback if server didn't update
  }

  async function parseJsonOrThrow(resp) {
    const ct = resp.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await resp.text();
      throw new Error(`Unexpected response (${resp.status}). ${text.slice(0, 200)}`);
    }
    return resp.json();
  }

  async function postJson(url, payload) {
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    return parseJsonOrThrow(resp);
  }

  // Typeahead suggestions by active step, using full DB values
  function showPromptSuggestions(inputText) {
    const active = (CURRENT_PROGRESS && CURRENT_PROGRESS.active) ? CURRENT_PROGRESS.active : 1;
    const q = (inputText || '').toLowerCase().trim();
    promptSuggest.innerHTML = '';

    let items = [];
    if (active === 1) {
      items = (CATALOG?.sources || []).map(s => ({label: s.name, value: s.name, icon: sourceIcon(s.name), action: 'source'}));
    } else if (active === 2) {
      items = (CATALOG?.standards || []).map(st => ({
        label: `${st.standard}${st.jurisdiction ? ' ('+st.jurisdiction+')' : ''}`,
        value: st.id, icon: 'fas fa-gavel', action: 'standard'
      }));
    } else if (active === 3) {
      items = (CATALOG?.metadata_keys || []).map(k => ({label: k.key, value: k.id, icon: 'fas fa-tag', action: 'metadata'}));
    } else if (active === 4) {
      const sel = CURRENT_STATE?.data_source_name || '';
      const src = (CATALOG?.sources || []).find(s => s.name === sel);
      const req = src?.required_fields || [];
      items = req.map(f => ({label: f, value: f, icon: 'fas fa-key', action: 'connection'}));
    }

    let matches = items;
    if (q.length > 0) {
      matches = items.filter(it => it.label.toLowerCase().includes(q));
    }

    matches.slice(0, 20).forEach(it => {
      const b = document.createElement('span');
      b.className = 'prompt-suggestion-btn';
      b.innerHTML = `<i class="${it.icon}"></i> ${it.label}`;
      b.onclick = function() {
        if (it.action === 'source') {
          selectDataSource(it.label);
        } else if (it.action === 'standard') {
          toggleStandardId(it.value, it.label);
        } else if (it.action === 'metadata') {
          toggleMetadataId(it.value, it.label);
        } else if (it.action === 'connection') {
          const inputs = Array.from(document.querySelectorAll('.conf-input'));
          const f = inputs.find(inp => (inp.dataset.field === it.value) || (inp.dataset.slug === slugify(it.value)));
          if (f) f.focus();
        }
        promptSuggest.innerHTML = '';
      };
      promptSuggest.appendChild(b);
    });

    if (!matches.length && q.length > 0) {
      const empty = document.createElement('div');
      empty.className = 'small-muted';
      empty.textContent = 'No matches found.';
      promptSuggest.appendChild(empty);
    }
  }

  function handleSaveResponse(data) {
    if (data.error) {
      addBubble("Save error: " + data.error, 'bot', true, CURRENT_PROGRESS.active);
      return;
    }
    addBubble(data.message || 'Saved', 'bot', true, (data.progress && data.progress.active) ? data.progress.active : CURRENT_PROGRESS.active);
    CURRENT_STATE      = data.state || CURRENT_STATE;
    CURRENT_SUBPROMPTS = data.sub_prompts || CURRENT_SUBPROMPTS;
    setProgress(data.progress || {});
    // After a save, sync UI sets to DB state and clear unsaved flag
    syncUiSetsFromState(true);
    HAS_UNSAVED = false;
    labelPrompt.style.display = 'none';
    renderSubPrompts(CURRENT_SUBPROMPTS);
  }

  async function applyAction(action) {
    try {
      const data = await postJson(API_SAVE_URL, { op: action.op, payload: action.payload || {} });
      handleSaveResponse(data);
    } catch (e) {
      addBubble("Save failed: " + (e?.message || e), 'bot', true, CURRENT_PROGRESS.active);
    }
  }

  // Quick-save helpers for each step (DB save + continue chat)
  async function quickSaveStep1(name) {
    addBubble(`Selecting data source: ${name}`, 'user', true, 1);
    const res = await postJson(API_SAVE_URL, { op: 'save_step1', payload: { data_source_name: name } });
    handleSaveResponse(res);
    await sendMessage('continue');
  }

  async function quickSaveStep2(ids) {
    const names = ids.map(id => stdLabelById(id));
    addBubble(`Selected regulations: ${names.join(', ')}`, 'user', true, 2);
    const res = await postJson(API_SAVE_URL, { op: 'save_step2', payload: { standard_ids: ids } });
    handleSaveResponse(res);
    await sendMessage('continue');
  }

  async function quickSaveStep3(ids) {
    const names = ids.map(id => metaLabelById(id));
    addBubble(`Selected metadata keys: ${names.join(', ')}`, 'user', true, 3);
    const res = await postJson(API_SAVE_URL, { op: 'save_step3', payload: { metadata_key_ids: ids } });
    handleSaveResponse(res);
    await sendMessage('continue');
  }

  async function quickSaveStep4(values) {
    const fields = Object.keys(values).filter((k, idx, arr) => !arr.includes(k.replace(/_/g,' '))); // rough unique
    addBubble(`Saving connection details (${fields.length} fields)`, 'user', true, 4);
    const res = await postJson(API_SAVE_URL, { op: 'save_step4', payload: { config_values: values } });
    handleSaveResponse(res);
    await sendMessage('continue');
  }

  // Goto step and Reset (Back/Next/Start over)
  async function gotoStep(step) {
    const res = await postJson(API_SAVE_URL, { op: 'goto_step', payload: { step } });
    handleSaveResponse(res);
    await sendMessage('continue');
  }

  async function resetConfig() {
    const res = await postJson(API_SAVE_URL, { op: 'reset_config', payload: {} });
    handleSaveResponse(res);
    // Clear chat and UI
    chatWindow.innerHTML = '';
    addBubble('Configuration reset. You can start again at Step 1.', 'bot', true, 1);
    promptSuggest.innerHTML = '';
    uiSelectedStandards.clear();
    uiSelectedMetadata.clear();
    PENDING.source = null;
    HAS_UNSAVED = false;
  }

  // Checkbox icons helpers (visible tick/cross)
  function attachStateIcons(containerSel) {
    const cont = document.querySelector(containerSel);
    if (!cont) return;
    cont.querySelectorAll('.form-check').forEach(fc => {
      let icon = fc.querySelector('.state-icon');
      if (!icon) {
        icon = document.createElement('span');
        icon.className = 'state-icon';
        fc.querySelector('label')?.appendChild(icon);
      }
    });
    updateStateIcons(containerSel);
  }
  function updateStateIcons(containerSel) {
    const cont = document.querySelector(containerSel);
    if (!cont) return;
    cont.querySelectorAll('.form-check').forEach(fc => {
      const input = fc.querySelector('input[type="checkbox"]');
      const icon = fc.querySelector('.state-icon');
      if (!icon) return;
      icon.innerHTML = input.checked
        ? '<i class="fas fa-check"></i>'
        : '<i class="fas fa-times"></i>';
    });
  }

  // Step options (DB-driven)
  function slugify(label) {
    return String(label || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }

  function computeStandardIdsFromState() {
    const regs = Array.isArray(CURRENT_STATE?.regulations) ? CURRENT_STATE.regulations : [];
    const ids = new Set();
    regs.forEach(r => {
      const label = `${r.standard}${r.jurisdiction ? ' ('+r.jurisdiction+')' : ''}`;
      const found = (CATALOG?.standards || []).find(s => {
        const l2 = `${s.standard}${s.jurisdiction ? ' ('+s.jurisdiction+')' : ''}`;
        return l2 === label;
      });
      if (found) ids.add(parseInt(found.id, 10));
    });
    return ids;
  }

  function computeMetadataIdsFromState() {
    const ids = Array.isArray(CURRENT_STATE?.metadata?.selected_metadata_keys) ? CURRENT_STATE.metadata.selected_metadata_keys : [];
    return new Set(ids.map(v => parseInt(v, 10)).filter(v => !isNaN(v)));
  }

  function syncUiSetsFromState(replace = false) {
    const baseStd = computeStandardIdsFromState();
    const baseMeta = computeMetadataIdsFromState();
    if (replace) {
      uiSelectedStandards = baseStd;
      uiSelectedMetadata = baseMeta;
    } else {
      uiSelectedStandards = new Set([...uiSelectedStandards, ...baseStd]);
      uiSelectedMetadata = new Set([...uiSelectedMetadata, ...baseMeta]);
    }
  }

  function renderStepOptions() {
    const active = (CURRENT_PROGRESS && CURRENT_PROGRESS.active) ? CURRENT_PROGRESS.active : 1;
    stepOptions.innerHTML = '';

    if (active === 1) {
      const sources = (CATALOG && CATALOG.sources) ? CATALOG.sources : [];
      let html = '<div class="step-card"><h6>Step 1: Choose a data source</h6>';
      html += '<div class="option-grid">';
      sources.forEach(s => {
        const selected = (PENDING.source === s.name) || (CURRENT_STATE?.data_source_name === s.name);
        html += `<button type="button" class="btn btn-sm btn-outline-secondary step1-option ${selected ? 'selected' : ''}" data-name="${s.name}">
                  <i class="${sourceIcon(s.name)}"></i> ${s.name}
                </button>`;
      });
      html += '</div>';
      if (CURRENT_STATE && CURRENT_STATE.data_source_name) {
        html += `<div class="small-muted mt-2">Currently saved: ${CURRENT_STATE.data_source_name}</div>`;
      } else if (PENDING.source) {
        html += `<div class="small-muted mt-2">Selected (pending save): ${PENDING.source}</div>`;
      }
      html += '</div>';
      stepOptions.innerHTML = html;

      document.querySelectorAll('.step1-option').forEach(btn => {
        btn.addEventListener('click', () => selectDataSource(btn.dataset.name));
      });
    }

    if (active === 2) {
      const standards = (CATALOG && CATALOG.standards) ? CATALOG.standards : [];
      // union of DB state and pending UI selections
      const stateIds = computeStandardIdsFromState();
      const selectedIds = new Set([...stateIds, ...uiSelectedStandards]);

      let html = '<div class="step-card"><h6>Step 2: Select applicable regulations</h6>';
      html += '<div class="row" id="regulations-area">';
      standards.forEach(st => {
        const id = `std_${st.id}`;
        const checked = selectedIds.has(parseInt(st.id, 10)) ? 'checked' : '';
        html += `
          <div class="col-md-6 mb-1">
            <div class="form-check">
              <input class="form-check-input std-check" type="checkbox" value="${st.id}" id="${id}" ${checked}>
              <label class="form-check-label" for="${id}"><i class="fas fa-gavel"></i> ${st.standard} ${st.jurisdiction ? '('+st.jurisdiction+')' : ''}</label>
            </div>
          </div>`;
      });
      html += '</div>';
      html += '<button type="button" class="btn btn-sm btn-primary mt-2" id="btnSaveReg">Save regulations</button>';
      html += '</div>';
      stepOptions.innerHTML = html;

      // state icons + change listeners
      attachStateIcons('#regulations-area');
      document.querySelectorAll('.std-check').forEach(chk => {
        chk.addEventListener('change', () => {
          const id = parseInt(chk.value, 10);
          if (chk.checked) {
            uiSelectedStandards.add(id);
            addBubble(`Selected regulation: ${stdLabelById(id)}`, 'user', true, 2);
          } else {
            uiSelectedStandards.delete(id);
            addBubble(`Deselected regulation: ${stdLabelById(id)}`, 'user', true, 2);
          }
          HAS_UNSAVED = true;
          labelPrompt.style.display = '';
          updateStateIcons('#regulations-area');
          renderSubPrompts();
        });
      });

      document.getElementById('btnSaveReg').addEventListener('click', () => {
        const ids = getSelectedStandardIds();
        if (!ids.length) { addBubble('Please select at least one regulation.', 'bot', true, 2); return; }
        quickSaveStep2(ids);
      });
    }

    if (active === 3) {
      const keys = (CATALOG && CATALOG.metadata_keys) ? CATALOG.metadata_keys : [];
      const stateIds = computeMetadataIdsFromState();
      const selectedIds = new Set([...stateIds, ...uiSelectedMetadata]);

      let html = '<div class="step-card"><h6>Step 3: Select metadata keys</h6>';
      html += '<div class="row" id="metadata-area">';
      keys.forEach(k => {
        const id = `mk_${k.id}`;
        const checked = selectedIds.has(parseInt(k.id, 10)) ? 'checked' : '';
        html += `
          <div class="col-md-6 mb-1">
            <div class="form-check">
              <input class="form-check-input mk-check" type="checkbox" value="${k.id}" id="${id}" ${checked}>
              <label class="form-check-label" for="${id}"><i class="fas fa-tag"></i> ${k.key}${k.description ? ' - '+k.description : ''}</label>
            </div>
          </div>`;
      });
      html += '</div>';
      html += '<button type="button" class="btn btn-sm btn-primary mt-2" id="btnSaveMeta">Save metadata</button>';
      html += '</div>';
      stepOptions.innerHTML = html;

      attachStateIcons('#metadata-area');
      document.querySelectorAll('.mk-check').forEach(chk => {
        chk.addEventListener('change', () => {
          const id = parseInt(chk.value, 10);
          if (chk.checked) {
            uiSelectedMetadata.add(id);
            addBubble(`Selected metadata: ${metaLabelById(id)}`, 'user', true, 3);
          } else {
            uiSelectedMetadata.delete(id);
            addBubble(`Deselected metadata: ${metaLabelById(id)}`, 'user', true, 3);
          }
          HAS_UNSAVED = true;
          labelPrompt.style.display = '';
          updateStateIcons('#metadata-area');
          renderSubPrompts();
        });
      });

      document.getElementById('btnSaveMeta').addEventListener('click', () => {
        const ids = getSelectedMetadataIds();
        if (!ids.length) { addBubble('Please select at least one metadata key.', 'bot', true, 3); return; }
        quickSaveStep3(ids);
      });
    }

    if (active === 4) {
      const selected = CURRENT_STATE && CURRENT_STATE.data_source_name ? CURRENT_STATE.data_source_name : (PENDING.source || null);
      const src = (CATALOG && CATALOG.sources) ? CATALOG.sources.find(s => s.name === selected) : null;
      let html = '<div class="step-card"><h6>Step 4: Enter connection details</h6>';

      if (!selected || !src) {
        html += '<div class="small-muted">Please select a data source in Step 1 first.</div></div>';
        stepOptions.innerHTML = html;
        return;
      }

      const required = src.required_fields || [];
      html += `<div class="small-muted mb-2">Data source: ${selected}</div>`;
      required.forEach(field => {
        const slug = slugify(field);
        const pre = (CURRENT_STATE && CURRENT_STATE.connection) ? (CURRENT_STATE.connection[field] || CURRENT_STATE.connection[slug] || '') : '';
        const safe = (pre === null || pre === undefined) ? '' : String(pre).replace(/"/g, '&quot;');
        html += `
          <div class="mb-2">
            <label class="form-label">${field}</label>
            <input type="text" class="form-control form-control-sm conf-input" data-field="${field}" data-slug="${slug}" value="${safe}">
          </div>`;
      });
      html += '<button type="button" class="btn btn-sm btn-primary" id="btnSaveConn">Save connection</button>';
      html += '</div>';
      stepOptions.innerHTML = html;

      document.getElementById('btnSaveConn').addEventListener('click', () => {
        const inputs = Array.from(document.querySelectorAll('.conf-input'));
        const values = {};
        const missing = [];
        inputs.forEach(inp => {
          const field = inp.dataset.field;
          const slug  = inp.dataset.slug;
          const val   = inp.value || '';
          if (!String(val).trim()) missing.push(field);
          values[slug]  = val;
          values[field] = val; // send both for safety
        });
        if (missing.length) {
          addBubble('Please fill the required fields: ' + missing.join(', '), 'bot', true, 4);
          labelPrompt.style.display = '';
          return;
        }
        quickSaveStep4(values);
      });
    }
  }

  // Selection helpers
  function getSelectedStandardIds() {
    const els = Array.from(document.querySelectorAll('.std-check:checked'));
    const ids = els.map(i => parseInt(i.value, 10)).filter(n => !isNaN(n));
    if (!ids.length && uiSelectedStandards.size) {
      return Array.from(uiSelectedStandards);
    }
    return ids;
  }
  function getSelectedMetadataIds() {
    const els = Array.from(document.querySelectorAll('.mk-check:checked'));
    const ids = els.map(i => parseInt(i.value, 10)).filter(n => !isNaN(n));
    if (!ids.length && uiSelectedMetadata.size) {
      return Array.from(uiSelectedMetadata);
    }
    return ids;
  }

  function renderSubPrompts(sp = null) {
    const active = (CURRENT_PROGRESS && CURRENT_PROGRESS.active) ? CURRENT_PROGRESS.active : 1;
    quickReplies.innerHTML = '';
    const current = sp || CURRENT_SUBPROMPTS || { type:'generic', items:[] };

    if (current.type === 'sources' && active === 1) {
      (current.items || []).forEach(it => {
        const chip = document.createElement('div');
        chip.className = 'chip' + ((PENDING.source === it.label || CURRENT_STATE?.data_source_name === it.label) ? ' active' : '');
        chip.innerHTML = `<i class="${sourceIcon(it.label)}"></i><span>${it.label}</span>`;
        chip.onclick = () => selectDataSource(it.value || it.label);
        quickReplies.appendChild(chip);
      });
    } else if (current.type === 'standards' && active === 2) {
      (current.items || []).forEach(it => {
        const id = parseInt(it.value, 10);
        const isActive = uiSelectedStandards.has(id) || computeStandardIdsFromState().has(id);
        const chip = document.createElement('div');
        chip.className = 'chip' + (isActive ? ' active' : '');
        chip.innerHTML = `<i class="fas fa-gavel"></i><span>${it.label}</span>`;
        chip.onclick = () => toggleStandardId(id, it.label);
        quickReplies.appendChild(chip);
      });

      const saveBtn = document.createElement('button');
      saveBtn.className = 'btn btn-sm btn-primary ms-1';
      saveBtn.textContent = 'Save selected regulations';
      saveBtn.onclick = () => {
        const ids = getSelectedStandardIds();
        if (!ids.length) { addBubble('Please select at least one regulation.', 'bot', true, 2); return; }
        quickSaveStep2(ids);
      };
      quickReplies.appendChild(saveBtn);
    } else if (current.type === 'metadata' && active === 3) {
      (current.items || []).forEach(it => {
        const id = parseInt(it.value, 10);
        const isActive = uiSelectedMetadata.has(id) || computeMetadataIdsFromState().has(id);
        const chip = document.createElement('div');
        chip.className = 'chip' + (isActive ? ' active' : '');
        chip.innerHTML = `<i class="fas fa-tag"></i><span>${it.label}</span>`;
        chip.onclick = () => toggleMetadataId(id, it.label);
        quickReplies.appendChild(chip);
      });

      const saveBtn = document.createElement('button');
      saveBtn.className = 'btn btn-sm btn-primary ms-1';
      saveBtn.textContent = 'Save selected metadata';
      saveBtn.onclick = () => {
        const ids = getSelectedMetadataIds();
        if (!ids.length) { addBubble('Please select at least one metadata key.', 'bot', true, 3); return; }
        quickSaveStep3(ids);
      };
      quickReplies.appendChild(saveBtn);
    } else if (current.type === 'connection' && active === 4) {
      const tip = document.createElement('div');
      tip.className = 'small-muted';
      tip.textContent = 'Use the form above to fill in required connection fields, then Save or press Send.';
      quickReplies.appendChild(tip);

      const saveBtn = document.createElement('button');
      saveBtn.className = 'btn btn-sm btn-success ms-1';
      saveBtn.textContent = 'Save connection';
      saveBtn.onclick = () => {
        const inputs = Array.from(document.querySelectorAll('.conf-input'));
        if (!inputs.length) { addBubble('No connection fields to save. Pick a source in Step 1.', 'bot', true, 4); return; }
        const values = {};
        const missing = [];
        inputs.forEach(inp => {
          const field = inp.dataset.field;
          const slug  = inp.dataset.slug;
          const val   = inp.value || '';
          if (!String(val).trim()) missing.push(field);
          values[slug]  = val;
          values[field] = val;
        });
        if (missing.length) {
          addBubble('Please fill the required fields: ' + missing.join(', '), 'bot', true, 4);
          return;
        }
        quickSaveStep4(values);
      };
      quickReplies.appendChild(saveBtn);
    } else {
      const b = document.createElement('div');
      b.className = 'small-muted';
      b.textContent = 'Use the step panel above or start chatting with the assistant.';
      quickReplies.appendChild(b);
    }
  }

  // Toggle/select helpers that also echo in chat and mark unsaved state
  function selectDataSource(name) {
    PENDING.source = name;
    labelPrompt.style.display = '';
    HAS_UNSAVED = true;
    addBubble(`Selected data source: ${name}`, 'user', true, 1);
    chatInput.value = name;
    // highlight selected button if present
    document.querySelectorAll('.step1-option').forEach(b => {
      if (b.dataset.name === name) b.classList.add('selected');
      else b.classList.remove('selected');
    });
    renderSubPrompts();
  }

  function toggleStandardId(id, label) {
    id = parseInt(id, 10);
    if (uiSelectedStandards.has(id)) {
      uiSelectedStandards.delete(id);
      addBubble(`Deselected regulation: ${label}`, 'user', true, 2);
    } else {
      uiSelectedStandards.add(id);
      addBubble(`Selected regulation: ${label}`, 'user', true, 2);
    }
    HAS_UNSAVED = true;
    labelPrompt.style.display = '';
    // reflect checkbox if present
    const chk = document.querySelector(`.std-check[value="${id}"]`);
    if (chk) chk.checked = uiSelectedStandards.has(id);
    renderSubPrompts();
  }

  function toggleMetadataId(id, label) {
    id = parseInt(id, 10);
    if (uiSelectedMetadata.has(id)) {
      uiSelectedMetadata.delete(id);
      addBubble(`Deselected metadata: ${label}`, 'user', true, 3);
    } else {
      uiSelectedMetadata.add(id);
      addBubble(`Selected metadata: ${label}`, 'user', true, 3);
    }
    HAS_UNSAVED = true;
    labelPrompt.style.display = '';
    const chk = document.querySelector(`.mk-check[value="${id}"]`);
    if (chk) chk.checked = uiSelectedMetadata.has(id);
    renderSubPrompts();
  }

  // Chat flow
  async function sendMessage(msg) {
    if (!msg || !msg.trim()) return;

    addBubble(msg, 'user');
    messages.push({ role: 'user', content: msg });

    chatInput.value = '';
    promptSuggest.innerHTML = '';
    labelPrompt.style.display = 'none';

    try {
      sendBtn.disabled = true;
      const oldText = sendBtn.textContent;
      sendBtn.textContent = "Awaiting assistant...";

      const payload = { message: msg, session_id: sessionIdEl.value || null, catalog: CATALOG };
      const data = await postJson(API_CHAT_URL, payload);

      sendBtn.disabled = false;
      sendBtn.textContent = oldText;

      if (data.error) {
        addBubble("Error: " + data.error, 'bot', true, CURRENT_PROGRESS.active);
        return;
      }
      if (data.session_id) sessionIdEl.value = data.session_id;

      const stepNum = (data.progress && data.progress.active) ? data.progress.active : CURRENT_PROGRESS.active;

      if (data.pending && data.question) {
        addBubble(data.question, 'bot', true, stepNum);
        labelPrompt.style.display = '';
        chatInput.placeholder = data.question;
      } else {
        labelPrompt.style.display = 'none';
        chatInput.placeholder = "Type to search DB values for this step...";
      }

      if (data.result) addBubble(data.result, 'bot', true, stepNum);

      CURRENT_STATE      = data.state || CURRENT_STATE;
      CURRENT_SUBPROMPTS = data.sub_prompts || CURRENT_SUBPROMPTS;
      setProgress(data.progress || {});
      syncUiSetsFromState(false);
      renderSubPrompts(CURRENT_SUBPROMPTS);

      // Auto-apply actions then continue
      if (Array.isArray(data.actions) && data.actions.length) {
        for (const action of data.actions) {
          await applyAction(action);
        }
        await sendMessage('continue');
      }
    } catch (e) {
      sendBtn.disabled = false;
      sendBtn.textContent = "Send";
      addBubble("Network error talking to AI orchestrator: " + (e?.message || e), 'bot', true, CURRENT_PROGRESS.active);
    }
  }

  // Commit current step via Send button if there are pending selections
  async function commitCurrentStepViaSend(msgText) {
    const step = CURRENT_PROGRESS?.active || 1;

    if (step === 1) {
      let name = PENDING.source;
      const typed = (msgText || '').trim();
      if (!name && typed) {
        const exact = (CATALOG?.sources || []).find(s => s.name.toLowerCase() === typed.toLowerCase());
        if (exact) name = exact.name;
        else {
          const matches = (CATALOG?.sources || []).filter(s => s.name.toLowerCase().includes(typed.toLowerCase()));
          if (matches.length === 1) name = matches[0].name;
        }
      }
      if (name) {
        HAS_UNSAVED = false;
        PENDING.source = null;
        await quickSaveStep1(name);
        return true;
      }
      return false;
    }

    if (step === 2) {
      let ids = getSelectedStandardIds();
      if (!ids.length) ids = Array.from(uiSelectedStandards);
      if (ids.length) {
        HAS_UNSAVED = false;
        await quickSaveStep2(ids);
        return true;
      }
      return false;
    }

    if (step === 3) {
      let ids = getSelectedMetadataIds();
      if (!ids.length) ids = Array.from(uiSelectedMetadata);
      if (ids.length) {
        HAS_UNSAVED = false;
        await quickSaveStep3(ids);
        return true;
      }
      return false;
    }

    if (step === 4) {
      const inputs = Array.from(document.querySelectorAll('.conf-input'));
      if (!inputs.length) return false;
      const values = {};
      const missing = [];
      inputs.forEach(inp => {
        const field = inp.dataset.field;
        const slug  = inp.dataset.slug;
        const val   = inp.value || '';
        if (!String(val).trim()) missing.push(field);
        values[slug]  = val;
        values[field] = val;
      });
      if (missing.length) {
        addBubble('Please fill the required fields: ' + missing.join(', '), 'bot', true, 4);
        labelPrompt.style.display = '';
        return true; // handled
      }
      HAS_UNSAVED = false;
      await quickSaveStep4(values);
      return true;
    }

    return false;
  }

  async function handleSendClick() {
    const msg = chatInput.value;
    // First try to commit DB selections for the current step
    const committed = await commitCurrentStepViaSend(msg);
    if (committed) {
      chatInput.value = '';
      labelPrompt.style.display = 'none';
      return;
    }
    // If nothing to save and user typed a message, send to AI
    if (msg && msg.trim()) {
      await sendMessage(msg);
    }
  }

  // Events
  sendBtn.addEventListener('click', handleSendClick);
  chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendClick();
    }
  });
  chatInput.addEventListener('input', function() { showPromptSuggestions(this.value); });
  chatInput.addEventListener('focus', function() { showPromptSuggestions(this.value); });

  startBtn.addEventListener('click', () => sendMessage(START_MESSAGE));

  clearBtn.addEventListener('click', () => {
    messages = [];
    chatWindow.innerHTML = '';
    quickReplies.innerHTML = '';
    chatInput.value = '';
    promptSuggest.innerHTML = '';
    labelPrompt.style.display = 'none';
    chatInput.placeholder = "Type to search DB values for this step...";
    addBubble("Chat cleared. You can continue using the step panel or start a new conversation.", 'bot', true, CURRENT_PROGRESS.active);
    renderStepOptions();
    renderSubPrompts();
  });

  prevStepBtn.addEventListener('click', () => {
    const s = Math.max(1, (CURRENT_PROGRESS?.active || 1) - 1);
    gotoStep(s);
  });
  nextStepBtn.addEventListener('click', () => {
    const s = Math.min(4, (CURRENT_PROGRESS?.active || 1) + 1);
    gotoStep(s);
  });
  startOverBtn.addEventListener('click', () => {
    if (confirm('This will delete the current configuration and start a fresh one. Continue?')) {
      resetConfig();
    }
  });

  stepPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const s = parseInt(pill.dataset.step, 10);
      if (!isNaN(s)) gotoStep(s);
    });
  });

  // Initial render
  setProgress(INITIAL_PROGRESS);
  CURRENT_SUBPROMPTS = INITIAL_SUBPROMPTS || CURRENT_SUBPROMPTS;
  syncUiSetsFromState(true);
  renderSubPrompts(CURRENT_SUBPROMPTS);
  addBubble("Hello! I'm your AI Configuration Assistant. Pick values from the step cards or suggestions; your selections appear here. Press Send to save and continue.", 'bot', true, CURRENT_PROGRESS.active);
</script>
@endsection