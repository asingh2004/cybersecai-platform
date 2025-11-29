@extends('template')

@push('styles')
<style>
  .result-json { background: #f8fafc; padding: 1em; border-radius: 8px; margin-top: 14px; white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 13px; border: 1px solid #e5e7eb; }

  .ai-parsed { margin-top: 24px; }
  .ai-parsed .toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: 12px; }
  .ai-parsed .toolbar .count { color: #6b7280; font-size: 0.9rem; }

  .reg-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 12px; }
  @media (min-width: 768px) { .reg-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (min-width: 1200px) { .reg-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

  .reg-card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
  .reg-header { display: flex; gap: 12px; align-items: flex-start; }
  .reg-title { font-weight: 700; color: #111827; margin: 0; font-size: 1rem; line-height: 1.35; }
  .meta { color: #6b7280; font-size: 0.9rem; }

  .summary { color: #374151; font-size: 0.95rem; }
  .reg-card ul { padding-left: 18px; margin: 0; }
  .reg-card li { margin: 2px 0; }

  /* Custom "X" selection checkbox (Bootstrap 5 compatible) */
  .xcheck.form-check-input {
    width: 22px;
    height: 22px;
    margin-top: 2px;
    appearance: none;
    -webkit-appearance: none;
    background-color: #fff;
    border: 1px solid #adb5bd;
    border-radius: .25rem;
    display: inline-block;
    position: relative;
    cursor: pointer;
    outline: none;
  }
  .xcheck.form-check-input:checked {
    background-color: #eefdf2;
    border-color: #16a34a;
  }
  .xcheck.form-check-input:checked::after {
    content: "✕";
    color: #16a34a;
    font-weight: 800;
    font-size: 14px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -55%);
    line-height: 1;
  }

  .hint { color:#6b7280; font-size: .9rem; }
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
            <h1><strong>Relevant Privacy Regulations &amp; Standards</strong></h1>
            <p class="mb-4">
              Country: <b>{{ $country }}</b><br>
              Industry/Sector: <b>{{ $industry }}</b>
            </p>

            @if(isset($error) && $error)
              <div class="alert alert-danger">{{ $error }}</div>
            @endif

            @if(isset($result) && $result)
              <div class="alert alert-success mb-3">AI Response:</div>
              <pre class="result-json" id="rawResult">{{ $result }}</pre>

              <div class="ai-parsed" id="parsedSection" style="display:none;">
                <h4 class="mb-2">Parsed Regulations</h4>
                <div class="toolbar">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">Select all</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">Deselect all</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" id="copySelectedBtn">Copy selected JSON</button>
                  <span class="count ms-auto" id="selectionCount"></span>
                </div>
                <div class="reg-grid" id="regGrid"></div>
                <p class="hint mt-2" id="parseNote"></p>
              </div>

              <div class="mt-3" id="parseError" style="display:none;">
                <div class="alert alert-warning mb-2">
                  Couldn’t automatically parse the AI response into structured items.
                </div>
                <div class="hint">Tip: Ensure your AI prompt asks for strict JSON or JSON inside ```json fenced block.</div>
              </div>
            @else
              <div class="alert alert-info">No response yet.</div>
            @endif

            <form method="POST" action="{{ route('wizard.privacyRegulations') }}">
              @csrf
              <button class="btn btn-primary btn-lg mt-4" type="submit">Request Privacy Regulations/Standards</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const rawEl = document.getElementById('rawResult');
  const parsedSection = document.getElementById('parsedSection');
  const parseError = document.getElementById('parseError');
  if (!rawEl) return;

  const regGrid = document.getElementById('regGrid');
  const selectionCount = document.getElementById('selectionCount');
  const selectAllBtn = document.getElementById('selectAllBtn');
  const deselectAllBtn = document.getElementById('deselectAllBtn');
  const copySelectedBtn = document.getElementById('copySelectedBtn');
  const parseNote = document.getElementById('parseNote');

  // Helpers
  function stripCodeFences(s) {
    const trimmed = s.trim();
    const fenceMatch = trimmed.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
    if (fenceMatch && fenceMatch[1]) return fenceMatch[1].trim();
    return trimmed;
  }

  function extractJsonBlock(s) {
    const startIndexObj = s.indexOf('{');
    const startIndexArr = s.indexOf('[');
    let start = -1;
    let openChar = null;
    if (startIndexObj !== -1 && (startIndexArr === -1 || startIndexObj < startIndexArr)) {
      start = startIndexObj; openChar = '{';
    } else if (startIndexArr !== -1) {
      start = startIndexArr; openChar = '[';
    } else {
      return null;
    }
    const closeChar = openChar === '{' ? '}' : ']';
    let depth = 0, inStr = false, esc = false;
    for (let i = start; i < s.length; i++) {
      const ch = s[i];
      if (inStr) {
        if (esc) { esc = false; }
        else if (ch === '\\') { esc = true; }
        else if (ch === '"') { inStr = false; }
      } else {
        if (ch === '"') inStr = true;
        else if (ch === openChar) depth++;
        else if (ch === closeChar) {
          depth--;
          if (depth === 0) return s.substring(start, i + 1);
        }
      }
    }
    return null;
  }

  function tryParse(text) {
    let candidate = stripCodeFences(text);
    try { return JSON.parse(candidate); } catch (e) {}
    const block = extractJsonBlock(candidate);
    if (block) {
      try { return JSON.parse(block); } catch (e) {}
    }
    return null;
  }

  function getCI(obj, keys) {
    for (const k of keys) {
      if (Object.prototype.hasOwnProperty.call(obj, k)) return obj[k];
    }
    // case-insensitive pass
    const map = {};
    Object.keys(obj || {}).forEach(key => map[key.toLowerCase()] = obj[key]);
    for (const k of keys) {
      const v = map[String(k).toLowerCase()];
      if (v !== undefined) return v;
    }
    return undefined;
  }

  function toArrayMaybe(v) {
    if (v === undefined || v === null) return [];
    if (Array.isArray(v)) return v;
    if (typeof v === 'string') {
      const parts = v.split(/[,;]+/).map(s => s.trim()).filter(Boolean);
      return parts.length ? parts : [v];
    }
    return [v];
  }

  let text = rawEl.textContent || rawEl.innerText || '';
  const data = tryParse(text);

  if (!data) {
    if (parseError) parseError.style.display = '';
    if (parsedSection) parsedSection.style.display = 'none';
    return;
  }

  // Locate array of regulations
  let regs = [];
  if (Array.isArray(data)) regs = data;
  else if (Array.isArray(data.regulations)) regs = data.regulations;
  else if (Array.isArray(data.items)) regs = data.items;
  else if (Array.isArray(data.results)) regs = data.results;

  if (!regs.length) {
    if (parseError) parseError.style.display = '';
    if (parsedSection) parsedSection.style.display = 'none';
    return;
  }

  function normalize(r, idx) {
    const obj = (r && typeof r === 'object') ? r : {};
    const standard = String(getCI(obj, ['Standard','standard','act','law','name','title']) ?? '').trim();
    const citation = String(getCI(obj, ['citation','reference','id']) ?? '').trim();
    const jurisdiction = String(getCI(obj, ['Jurisdiction','jurisdiction','scope','region','country']) ?? '').trim();
    const summary = String(getCI(obj, ['summary','description']) ?? '').trim();
    const year = String(getCI(obj, ['year','effective_date']) ?? '').trim();
    const category = String(getCI(obj, ['category','type']) ?? '').trim();
    let obligations = getCI(obj, ['obligations','requirements','controls']) ?? [];
    obligations = toArrayMaybe(obligations).map(String);

    // Fields covered + rationale (for tooltip)
    const fieldsList = toArrayMaybe(getCI(obj, ['fields','data_fields','personal_data_fields','elements','categories'])).map(String);
    const rationale = String(getCI(obj, ['rationale','reason','rationale_for_suggestion']) ?? '').trim();

    return { standard, citation, jurisdiction, summary, year, category, obligations, fieldsList, rationale, _raw: obj };
  }

  const normalized = regs.map((r, i) => normalize(r, i));
  let selected = new Set(normalized.map((_, i) => i)); // default: select all

  function updateCount() {
    selectionCount.textContent = selected.size + ' selected';
  }

  function createCard(item, index) {
    const card = document.createElement('div');
    card.className = 'reg-card';

    const header = document.createElement('div');
    header.className = 'reg-header';

    const formCheck = document.createElement('div');
    formCheck.className = 'form-check mt-1';

    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.className = 'form-check-input xcheck';
    cb.id = 'reg_' + index;
    cb.checked = selected.has(index);
    cb.addEventListener('change', () => {
      if (cb.checked) selected.add(index); else selected.delete(index);
      updateCount();
    });
    formCheck.appendChild(cb);

    const titleWrap = document.createElement('div');
    const title = document.createElement('label');
    title.className = 'form-check-label reg-title';
    title.setAttribute('for', cb.id);
    title.textContent = item.standard || item.citation || ('Item ' + (index + 1));

    // Compact meta line: citation and year
    const meta = document.createElement('div');
    meta.className = 'meta';
    const bits = [];
    if (item.citation) bits.push(item.citation);
    if (item.year) bits.push(item.year);
    meta.textContent = bits.join(' • ');

    titleWrap.appendChild(title);
    if (bits.length) titleWrap.appendChild(meta);

    header.appendChild(formCheck);
    header.appendChild(titleWrap);

    const body = document.createElement('div');

    // Jurisdiction as its own line; tooltip shows Fields covered + Rationale
    if (item.jurisdiction) {
      const juris = document.createElement('div');
      juris.className = 'meta';
      juris.textContent = item.jurisdiction;

      const tooltipLines = [];
      if (item.fieldsList && item.fieldsList.length) tooltipLines.push('Fields: ' + item.fieldsList.join(', '));
      if (item.rationale) tooltipLines.push('Rationale: ' + item.rationale);

      if (tooltipLines.length) {
        juris.setAttribute('data-bs-toggle', 'tooltip');
        juris.setAttribute('data-bs-placement', 'top');
        juris.setAttribute('data-bs-title', tooltipLines.join('\n'));
      }

      body.appendChild(juris);
    }

    if (item.category) {
      const cat = document.createElement('div');
      cat.className = 'meta';
      cat.textContent = item.category;
      body.appendChild(cat);
    }

    if (item.summary) {
      const sum = document.createElement('div');
      sum.className = 'summary';
      sum.textContent = item.summary;
      body.appendChild(sum);
    }

    if (item.obligations && item.obligations.length) {
      const h6 = document.createElement('div');
      h6.style.marginTop = '6px';
      h6.style.fontWeight = '600';
      h6.textContent = 'Key obligations:';
      const ul = document.createElement('ul');
      item.obligations.forEach(ob => {
        const li = document.createElement('li');
        li.textContent = String(ob);
        ul.appendChild(li);
      });
      body.appendChild(h6);
      body.appendChild(ul);
    }

    card.appendChild(header);
    card.appendChild(body);
    return card;
  }

  // Render grid
  regGrid.innerHTML = '';
  normalized.forEach((item, i) => regGrid.appendChild(createCard(item, i)));

  // Toolbar actions
  selectAllBtn.addEventListener('click', () => {
    selected = new Set(normalized.map((_, i) => i));
    regGrid.querySelectorAll('input[type="checkbox"]').forEach((c) => c.checked = true);
    updateCount();
  });

  deselectAllBtn.addEventListener('click', () => {
    selected.clear();
    regGrid.querySelectorAll('input[type="checkbox"]').forEach((c) => c.checked = false);
    updateCount();
  });

  copySelectedBtn.addEventListener('click', async () => {
    const chosen = Array.from(selected).map(i => normalized[i]._raw || normalized[i]);
    const pretty = JSON.stringify(chosen, null, 2);
    try {
      await navigator.clipboard.writeText(pretty);
      const old = copySelectedBtn.textContent;
      copySelectedBtn.textContent = 'Copied!';
      setTimeout(() => copySelectedBtn.textContent = old, 1200);
    } catch (e) {
      window.prompt('Copy the selected JSON:', pretty);
    }
  });

  updateCount();
  if (parsedSection) parsedSection.style.display = '';
  if (parseError) parseError.style.display = 'none';
  parseNote.textContent = 'Parsed from AI JSON response. Hover over the jurisdiction to see fields covered and the rationale.';

  // Initialize Bootstrap 5 tooltips (requires Bootstrap JS on the page)
  try {
    if (window.bootstrap && bootstrap.Tooltip) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
      });
    }
  } catch (e) { /* no-op if Bootstrap not loaded */ }
});
</script>
@endpush