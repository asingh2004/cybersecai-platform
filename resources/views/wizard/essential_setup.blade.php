@extends('template')

@push('styles')
<style>
  .setup-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  @media (min-width: 768px) { .setup-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
  .setup-tile { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 16px; cursor: pointer; transition: box-shadow .15s ease, border-color .15s ease, transform .1s ease; position: relative; min-height: 110px; }
  .setup-tile:hover { box-shadow: 0 4px 18px rgba(0,0,0,.06); transform: translateY(-1px); }
  .setup-tile.active { border-color: #1d4ed8; box-shadow: 0 6px 22px rgba(29,78,216,.12); }
  .setup-tile h5 { font-size: 1rem; margin: 0 0 6px 0; font-weight: 700; color: #111827; }
  .setup-tile p { margin: 0; color: #6b7280; font-size: .92rem; }
  .status-dot { position: absolute; top: 10px; right: 10px; width: 10px; height: 10px; border-radius: 999px; border: 2px solid #ffffff; box-shadow: 0 0 0 1px rgba(0,0,0,.08); }
  .status-green { background: #16a34a; }
  .status-red { background: #dc2626; }
  .content-panel { margin-top: 18px; }
  .content-card { border: 1px solid #e5e7eb; background: #fff; border-radius: 10px; padding: 18px; }
  .content-card h4 { margin: 0 0 12px; font-weight: 700; color: #111827; }
  .field-label { font-weight: 600; color: #374151; font-size: .95rem; }
  .readonly-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
  .alert-fixed { margin-top: 12px; }

  /* Shared toolbar */
  .ai-parsed .toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: 12px; }
  .ai-parsed .toolbar .count { color: #6b7280; font-size: 0.9rem; }

  /* Section 3 tiles (regulations) */
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

  /* Section 4 tiles (subject categories) */
  .cat-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 12px; }
  @media (min-width: 768px) { .cat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (min-width: 1200px) { .cat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  .cat-card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
  .cat-header { display: flex; gap: 12px; align-items: flex-start; }
  .cat-title { font-weight: 700; color: #111827; margin: 0; font-size: 1rem; line-height: 1.35; }

  /* Custom "X" checkbox */
  .xcheck.form-check-input {
    width: 22px; height: 22px; margin-top: 2px;
    appearance: none; -webkit-appearance: none;
    background-color: #fff; border: 1px solid #adb5bd; border-radius: .25rem;
    display: inline-block; position: relative; cursor: pointer; outline: none;
  }
  .xcheck.form-check-input:checked { background-color: #eefdf2; border-color: #16a34a; }
  .xcheck.form-check-input:checked::after {
    content: "✕"; color: #16a34a; font-weight: 800; font-size: 14px;
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -55%); line-height: 1;
  }

  .hint { color:#6b7280; font-size: .9rem; }
  .table-classification th, .table-classification td { vertical-align: middle; }
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

            <h1><strong>Essential Setup</strong></h1>
            <p class="text-muted">Complete key steps to set up your company profile and preferences. Team members with the same business ID can view and update these settings.</p>

            @if (session('success'))
              <div class="alert alert-success alert-fixed">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
              <div class="alert alert-danger alert-fixed">
                <ul class="mb-0">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <div class="setup-tiles mb-3">
              <div class="setup-tile {{ $activeSection === 1 ? 'active' : '' }}" data-section="1" role="button" tabindex="0">
                <span class="status-dot {{ $sectionStatuses['s1'] ? 'status-green' : 'status-red' }}"></span>
                <h5>Section 1: Complete your Company Profile</h5>
                <p>Business details shared across your team.</p>
              </div>
              <div class="setup-tile {{ $activeSection === 2 ? 'active' : '' }}" data-section="2" role="button" tabindex="0">
                <span class="status-dot {{ $sectionStatuses['s2'] ? 'status-green' : 'status-red' }}"></span>
                <h5>Section 2: Tailor Data Classification [Optional]</h5>
                <p>Customize sensitivity levels and tags.</p>
              </div>
              <div class="setup-tile {{ $activeSection === 3 ? 'active' : '' }}" data-section="3" role="button" tabindex="0">
                <span class="status-dot {{ $sectionStatuses['s3'] ? 'status-green' : 'status-red' }}"></span>
                <h5>Section 3: Select Applicable Regulations [Mandatory]</h5>
                <p>Choose standards relevant to your organization.</p>
              </div>
              <div class="setup-tile {{ $activeSection === 4 ? 'active' : '' }}" data-section="4" role="button" tabindex="0">
                <span class="status-dot {{ $sectionStatuses['s4'] ? 'status-green' : 'status-red' }}"></span>
                <h5>Section 4: Select Data Subject Category</h5>
                <p>Identify data subject groups you handle.</p>
              </div>
            </div>

            <div class="content-panel">

              <!-- Section 1 -->
              <div id="section-1" class="content-card" style="{{ $activeSection === 1 ? '' : 'display:none;' }}">
                <div class="d-flex align-items-center mb-2">
                  <h4 class="mb-0">Section 1: Company Profile</h4>
                  @if($profile)
                    <button type="button" id="btnEditS1" class="btn btn-outline-primary btn-sm ms-auto">Edit</button>
                  @endif
                </div>
                <p class="text-muted mb-3">This profile is shared for your business_id across all users in your company.</p>
                <div id="s1View" style="{{ $profile ? '' : 'display:none;' }}">
                  <div class="mb-3"><div class="field-label mb-1">Business ID</div><div class="readonly-box">{{ $bizId ?? '-' }}</div></div>
                  <div class="mb-3"><div class="field-label mb-1">Industry</div><div class="readonly-box">{{ $profile->industry ?? '-' }}</div></div>
                  <div class="mb-3"><div class="field-label mb-1">Country</div><div class="readonly-box">{{ $profile->country ?? '-' }}</div></div>
                  <div class="mb-2"><div class="field-label mb-1">About Company</div><div class="readonly-box">{{ $profile->about_company ?? '-' }}</div></div>
                </div>
                <div id="s1Edit" style="{{ $profile ? 'display:none;' : '' }}">
                  <form method="POST" action="{{ route('wizard.essentialSetup.section1.save') }}">
                    @csrf
                    <div class="mb-3">
                      <label class="form-label">Business ID</label>
                      <input type="text" class="form-control" value="{{ $bizId ?? '' }}" disabled>
                      <div class="form-text">This is derived from your account and cannot be changed.</div>
                    </div>
                    <div class="mb-3">
                      <label for="industry" class="form-label">Industry</label>
                      <input type="text" id="industry" name="industry" class="form-control" value="{{ old('industry', $profile->industry ?? '') }}" required>
                    </div>
                    <div class="mb-3">
                      <label for="country" class="form-label">Country</label>
                      <select id="country" name="country" class="form-select" required>
                        <option value="" disabled {{ old('country', $profile->country ?? '') ? '' : 'selected' }}>Select a country</option>
                        @foreach($countries as $cVal => $cName)
                          <option value="{{ $cVal }}" {{ old('country', $profile->country ?? '') === $cVal ? 'selected' : '' }}>{{ $cName }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="about_company" class="form-label">About Company</label>
                      <textarea id="about_company" name="about_company" rows="4" class="form-control">{{ old('about_company', $profile->about_company ?? '') }}</textarea>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      <button type="submit" class="btn btn-primary">Save Company Profile</button>
                      @if($profile)
                        <button type="button" id="btnCancelS1" class="btn btn-outline-secondary">Cancel</button>
                      @endif
                    </div>
                  </form>
                </div>
              </div>

              <!-- Section 2 -->
              <div id="section-2" class="content-card" style="{{ $activeSection === 2 ? '' : 'display:none;' }}">
                <h4 class="mb-2">Section 2: Tailor Data Classification [Optional]</h4>
                <p class="text-muted">Optionally set preferred internal tags for each standard level. Saved to your business profile.</p>
                <form method="POST" action="{{ route('wizard.essentialSetup.section2.save') }}">
                  @csrf
                  <table class="table table-bordered table-classification mt-2">
                    <thead><tr><th>#</th><th>Standard Name</th><th>Examples</th><th>Access Control</th><th>Preferred Tag (optional)</th></tr></thead>
                    <tbody>
                      @foreach($dcLevels as $lvl)
                        <tr>
                          <td>{{ $lvl['id'] }}</td>
                          <td>{{ $lvl['name'] }}</td>
                          <td>{{ $lvl['example'] }}</td>
                          <td>{{ $lvl['access'] }}</td>
                          <td>
                            <input type="text" name="preferred_tags[{{ $lvl['id'] }}]" class="form-control" maxlength="100"
                              value="{{ old('preferred_tags.' . $lvl['id'], $dcPreferredTags[$lvl['id']] ?? '') }}"
                              placeholder="e.g., MyCompany Confidential">
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  @error('preferred_tags.*') <div class="alert alert-danger">{{ $message }}</div> @enderror
                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save Data Classification</button>
                  </div>
                </form>
              </div>

              <!-- Section 3 -->
              @php
                $savedRegsDecoded = is_string($savedRegulations) ? json_decode($savedRegulations, true) : (is_array($savedRegulations) ? $savedRegulations : []);
                $hasSavedRegs = is_array($savedRegsDecoded) && count($savedRegsDecoded) > 0;
                $hasRecommendation = !empty($recommendationRaw);
              @endphp
              <div id="section-3" class="content-card" style="{{ $activeSection === 3 ? '' : 'display:none;' }}">
                <h4 class="mb-2">Section 3: Select Applicable Regulations [Mandatory]</h4>
                <p class="text-muted mb-2">
                  Country: <b>{{ $country ?? '-' }}</b> | Industry/Sector: <b>{{ $industry ?? '-' }}</b>
                </p>
                @if(!empty($about_company))
                  <div class="text-muted mb-3" style="font-size:.95rem;">
                    <b>About Company:</b> {{ $about_company }}
                  </div>
                @endif

                @if(!empty($regulationsError))
                  <div class="alert alert-warning mb-3">{{ $regulationsError }}</div>
                @endif

                @if(!$hasSavedRegs && !$hasRecommendation)
                  <div class="text-center py-4">
                    <p class="mb-3">No regulations saved yet for your company profile.</p>
                    <form method="POST" action="{{ route('wizard.essentialSetup.section3.recommend') }}">
                      @csrf
                      <button type="submit" class="btn btn-primary btn-lg">Recommend Regulations based on my Company</button>
                    </form>
                  </div>
                @else
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <form method="POST" action="{{ route('wizard.essentialSetup.section3.recommend') }}" class="m-0">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-warning">Re-Generate</button>
                    </form>
                  </div>

                  <form id="regForm" method="POST" action="{{ route('wizard.essentialSetup.section3.save') }}">
                    @csrf
                    <input type="hidden" id="regulations_json" name="regulations_json" value="">
                    <div class="ai-parsed">
                      <div class="toolbar">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">Select all</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">Deselect all</button>
                        <span class="count ms-auto" id="selectionCount"></span>
                      </div>
                      <div class="reg-grid" id="regGrid"></div>
                      <p class="hint mt-2" id="parseNote">Hover over the jurisdiction to see fields covered and the rationale (when available).</p>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                      <button type="submit" id="saveRegBtn" class="btn btn-primary">Save Selected Regulations</button>
                    </div>

                    <script id="regData" type="application/json">
                      @php
                        if ($hasRecommendation) {
                            echo json_encode($recommendationRaw);
                        } elseif ($hasSavedRegs) {
                            echo is_string($savedRegulations) ? $savedRegulations : json_encode($savedRegulations);
                        } else {
                            echo 'null';
                        }
                      @endphp
                    </script>
                  </form>
                @endif
              </div>

              <!-- Section 4: Subject Categories -->
              @php
                $savedSubjDecoded = is_string($savedSubjectCategories) ? json_decode($savedSubjectCategories, true) : (is_array($savedSubjectCategories) ? $savedSubjectCategories : []);
                $hasSavedSubjects = is_array($savedSubjDecoded) && count($savedSubjDecoded) > 0;
                $hasSubjectsRecommendation = !empty($subjectsRecommendationRaw);
              @endphp
              <div id="section-4" class="content-card" style="{{ $activeSection === 4 ? '' : 'display:none;' }}">
                <h4 class="mb-2">Section 4: Select Data Subject Category</h4>
                <p class="text-muted mb-2">Country: <b>{{ $country ?? '-' }}</b> | Industry/Sector: <b>{{ $industry ?? '-' }}</b></p>
                @if(!empty($about_company))
                  <div class="text-muted mb-3" style="font-size:.95rem;">
                    <b>About Company:</b> {{ $about_company }}
                  </div>
                @endif

                @if(!empty($subjectsError))
                  <div class="alert alert-warning mb-3">{{ $subjectsError }}</div>
                @endif

                @if(!$hasSavedSubjects && !$hasSubjectsRecommendation)
                  <div class="text-center py-4">
                    <p class="mb-3">No subject categories saved yet for your company profile.</p>
                    <form method="POST" action="{{ route('wizard.essentialSetup.section4.recommend') }}">
                      @csrf
                      <button type="submit" class="btn btn-primary btn-lg">Recommend Subject Categories based on my Company</button>
                    </form>
                  </div>
                @else
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <form method="POST" action="{{ route('wizard.essentialSetup.section4.recommend') }}" class="m-0">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-warning">Re-Generate Subject Categories</button>
                    </form>
                  </div>

                  <form id="subjForm" method="POST" action="{{ route('wizard.essentialSetup.section4.save') }}">
                    @csrf
                    <input type="hidden" id="subject_categories_json" name="subject_categories_json" value="">
                    <div class="ai-parsed">
                      <div class="toolbar">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="subjSelectAllBtn">Select all</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="subjDeselectAllBtn">Deselect all</button>
                        <span class="count ms-auto" id="subjSelectionCount"></span>
                      </div>
                      <div class="cat-grid" id="catGrid"></div>
                      <p class="hint mt-2" id="subjParseNote">Hover over a category title to see fields covered and the rationale (if provided by the model).</p>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                      <button type="submit" id="subjSaveBtn" class="btn btn-primary">Save Selected Subject Categories</button>
                    </div>

                    <script id="subjData" type="application/json">
                      @php
                        if ($hasSubjectsRecommendation) {
                            echo json_encode($subjectsRecommendationRaw);
                        } elseif ($hasSavedSubjects) {
                            echo is_string($savedSubjectCategories) ? $savedSubjectCategories : json_encode($savedSubjectCategories);
                        } else {
                            echo 'null';
                        }
                      @endphp
                    </script>
                  </form>
                @endif
              </div>

            </div>

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
  const tiles = document.querySelectorAll('.setup-tile');
  tiles.forEach(t => {
    t.addEventListener('click', () => activateSection(t.dataset.section));
    t.addEventListener('keypress', (e) => { if (e.key === 'Enter' || e.key === ' ') activateSection(t.dataset.section); });
  });
  function activateSection(num) {
    tiles.forEach(el => el.classList.toggle('active', el.dataset.section == num));
    [1,2,3,4].forEach(i => {
      const el = document.getElementById('section-' + i);
      if (el) el.style.display = (String(i) === String(num)) ? '' : 'none';
    });
    const url = new URL(window.location.href);
    url.searchParams.set('section', String(num));
    window.history.replaceState({}, '', url.toString());
  }

  const btnEdit = document.getElementById('btnEditS1');
  const btnCancel = document.getElementById('btnCancelS1');
  const view = document.getElementById('s1View');
  const edit = document.getElementById('s1Edit');
  if (btnEdit && view && edit) btnEdit.addEventListener('click', () => { view.style.display = 'none'; edit.style.display = ''; });
  if (btnCancel && view && edit) btnCancel.addEventListener('click', () => { edit.style.display = 'none'; view.style.display = ''; });

  // Section 3 JS
  const regGrid = document.getElementById('regGrid');
  if (regGrid) {
    const selectionCount = document.getElementById('selectionCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const hiddenJson = document.getElementById('regulations_json');
    const regDataEl = document.getElementById('regData');

    function tryParsePayload(raw) {
      if (!raw) return null;
      if (Array.isArray(raw)) return raw;
      if (typeof raw === 'object') {
        if (Array.isArray(raw.regulations)) return raw.regulations;
        if (Array.isArray(raw.items)) return raw.items;
        if (Array.isArray(raw.results)) return raw.results;
        return null;
      }
      if (typeof raw === 'string') {
        const trimmed = raw.trim();
        if (!trimmed) return null;
        if (trimmed.startsWith('[')) { try { return JSON.parse(trimmed); } catch (e) {} }
        const fenceMatch = trimmed.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
        if (fenceMatch && fenceMatch[1]) { try { return JSON.parse(fenceMatch[1].trim()); } catch (e) {} }
        const startObj = trimmed.indexOf('{'), startArr = trimmed.indexOf('[');
        let start = -1, openChar = null;
        if (startArr !== -1 && (startObj === -1 || startArr < startObj)) { start = startArr; openChar = '['; }
        else if (startObj !== -1) { start = startObj; openChar = '{'; }
        if (start >= 0) {
          const closeChar = openChar === '[' ? ']' : '}';
          let depth=0, inStr=false, esc=false;
          for (let i=start; i<trimmed.length; i++) {
            const ch = trimmed[i];
            if (inStr) { if (esc) esc=false; else if (ch==='\\') esc=true; else if (ch === '"') inStr=false; }
            else {
              if (ch === '"') inStr=true;
              else if (ch === openChar) depth++;
              else if (ch === closeChar) { depth--; if (depth === 0) { const block = trimmed.substring(start, i+1); try { return JSON.parse(block); } catch (e) {} } }
            }
          }
        }
      }
      return null;
    }

    let serverPayload = null;
    try { serverPayload = JSON.parse(regDataEl ? regDataEl.textContent : 'null'); }
    catch (e) { serverPayload = regDataEl ? regDataEl.textContent : null; }

    let regsArr = tryParsePayload(serverPayload);
    if (!Array.isArray(regsArr)) regsArr = [];

    function getCI(obj, keys) {
      for (const k of keys) if (Object.prototype.hasOwnProperty.call(obj, k)) return obj[k];
      const map = {}; Object.keys(obj||{}).forEach(key => map[key.toLowerCase()] = obj[key]);
      for (const k of keys) { const v = map[String(k).toLowerCase()]; if (v !== undefined) return v; }
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
      const fieldsList = toArrayMaybe(getCI(obj, ['fields','data_fields','personal_data_fields','elements','categories'])).map(String);
      const rationale = String(getCI(obj, ['rationale','reason','rationale_for_suggestion']) ?? '').trim();
      return { standard, citation, jurisdiction, summary, year, category, obligations, fieldsList, rationale, _raw: obj };
    }

    const normalized = regsArr.map((r, i) => normalize(r, i));
    let selected = new Set(normalized.map((_, i) => i));
    function updateCount() { if (selectionCount) selectionCount.textContent = selected.size + ' selected'; }

    function createCard(item, index) {
      const card = document.createElement('div'); card.className = 'reg-card';
      const header = document.createElement('div'); header.className = 'reg-header';

      const formCheck = document.createElement('div'); formCheck.className = 'form-check mt-1';
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'form-check-input xcheck'; cb.id = 'reg_' + index; cb.checked = selected.has(index);
      cb.addEventListener('change', () => { if (cb.checked) selected.add(index); else selected.delete(index); updateCount(); });
      formCheck.appendChild(cb);

      const titleWrap = document.createElement('div');
      const title = document.createElement('label'); title.className = 'form-check-label reg-title'; title.setAttribute('for', cb.id);
      title.textContent = item.standard || item.citation || ('Item ' + (index + 1));

      const meta = document.createElement('div'); meta.className = 'meta';
      const bits = []; if (item.citation) bits.push(item.citation); if (item.year) bits.push(item.year);
      meta.textContent = bits.join(' • ');

      titleWrap.appendChild(title);
      if (bits.length) titleWrap.appendChild(meta);
      header.appendChild(formCheck); header.appendChild(titleWrap);

      const body = document.createElement('div');
      if (item.jurisdiction) {
        const juris = document.createElement('div'); juris.className = 'meta'; juris.textContent = item.jurisdiction;
        const tt = []; if (item.fieldsList && item.fieldsList.length) tt.push('Fields: ' + item.fieldsList.join(', ')); if (item.rationale) tt.push('Rationale: ' + item.rationale);
        if (tt.length) { juris.setAttribute('data-bs-toggle', 'tooltip'); juris.setAttribute('data-bs-placement', 'top'); juris.setAttribute('data-bs-title', tt.join('\n')); }
        body.appendChild(juris);
      }
      if (item.category) { const cat = document.createElement('div'); cat.className = 'meta'; cat.textContent = item.category; body.appendChild(cat); }
      if (item.summary)  { const sum = document.createElement('div'); sum.className = 'summary'; sum.textContent = item.summary; body.appendChild(sum); }
      if (item.obligations && item.obligations.length) {
        const h6 = document.createElement('div'); h6.style.marginTop = '6px'; h6.style.fontWeight = '600'; h6.textContent = 'Key obligations:';
        const ul = document.createElement('ul'); item.obligations.forEach(ob => { const li = document.createElement('li'); li.textContent = String(ob); ul.appendChild(li); });
        body.appendChild(h6); body.appendChild(ul);
      }
      card.appendChild(header); card.appendChild(body);
      return card;
    }

    regGrid.innerHTML = '';
    normalized.forEach((item, i) => regGrid.appendChild(createCard(item, i)));
    updateCount();

    if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
      selected = new Set(normalized.map((_, i) => i));
      regGrid.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = true);
      updateCount();
    });
    if (deselectAllBtn) deselectAllBtn.addEventListener('click', () => {
      selected.clear();
      regGrid.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
      updateCount();
    });

    const regForm = document.getElementById('regForm');
    if (regForm && hiddenJson) {
      regForm.addEventListener('submit', function(e) {
        const chosen = Array.from(selected).map(i => normalized[i]._raw || normalized[i]);
        if (!chosen.length) { e.preventDefault(); alert('Please select at least one standard before saving.'); return false; }
        hiddenJson.value = JSON.stringify(chosen);
      });
    }

    try { if (window.bootstrap && bootstrap.Tooltip) { document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); }); } } catch (e) {}
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const catGrid = document.getElementById('catGrid');
  if (!catGrid) return;

  const selectionCount = document.getElementById('subjSelectionCount');
  const selectAllBtn = document.getElementById('subjSelectAllBtn');
  const deselectAllBtn = document.getElementById('subjDeselectAllBtn');
  const hiddenJson = document.getElementById('subject_categories_json');
  const subjDataEl = document.getElementById('subjData');

  function tryParsePayload(raw) {
    if (!raw) return null;
    if (Array.isArray(raw)) return raw;
    if (typeof raw === 'object') {
      if (Array.isArray(raw.categories)) return raw.categories;
      if (Array.isArray(raw.items)) return raw.items;
      if (Array.isArray(raw.results)) return raw.results;
      if (Array.isArray(raw.subjects)) return raw.subjects;
      return null;
    }
    if (typeof raw === 'string') {
      const trimmed = raw.trim();
      if (!trimmed) return null;
      if (trimmed.startsWith('[')) { try { return JSON.parse(trimmed); } catch (e) {} }
      const fenceMatch = trimmed.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
      if (fenceMatch && fenceMatch[1]) { try { return JSON.parse(fenceMatch[1].trim()); } catch (e) {} }
      const startObj = trimmed.indexOf('{'), startArr = trimmed.indexOf('[');
      let start = -1, openChar = null;
      if (startArr !== -1 && (startObj === -1 || startArr < startObj)) { start = startArr; openChar = '['; }
      else if (startObj !== -1) { start = startObj; openChar = '{'; }
      if (start >= 0) {
        const closeChar = openChar === '[' ? ']' : '}';
        let depth=0, inStr=false, esc=false;
        for (let i=start; i<trimmed.length; i++) {
          const ch = trimmed[i];
          if (inStr) { if (esc) esc=false; else if (ch==='\\') esc=true; else if (ch === '"') inStr=false; }
          else {
            if (ch === '"') inStr=true;
            else if (ch === openChar) depth++;
            else if (ch === closeChar) { depth--; if (depth===0) { const block = trimmed.substring(start,i+1); try { return JSON.parse(block); } catch(e){} } }
          }
        }
      }
    }
    return null;
  }

  function getCI(obj, keys) {
    for (const k of keys) if (Object.prototype.hasOwnProperty.call(obj, k)) return obj[k];
    const map = {}; Object.keys(obj||{}).forEach(key => map[key.toLowerCase()] = obj[key]);
    for (const k of keys) { const v = map[String(k).toLowerCase()]; if (v !== undefined) return v; }
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

  let serverPayload = null;
  try { serverPayload = JSON.parse(subjDataEl ? subjDataEl.textContent : 'null'); }
  catch (e) { serverPayload = subjDataEl ? subjDataEl.textContent : null; }

  let items = tryParsePayload(serverPayload);
  if (!Array.isArray(items)) items = [];

  function normalize(r, idx) {
    const obj = (r && typeof r === 'object') ? r : {};
    const category = String(getCI(obj, ['category','name','subject','title','label']) ?? '').trim() || `Category ${idx+1}`;
    const description = String(getCI(obj, ['description','summary','details','about']) ?? '').trim();
    const rationale = String(getCI(obj, ['rationale','reason','relevance','why']) ?? '').trim();
    const fieldsList = toArrayMaybe(getCI(obj, ['fields','data_fields','attributes','data_elements','personal_data_types','columns'])).map(String);
    const examples = toArrayMaybe(getCI(obj, ['examples','sample_items','records','instances'])).map(String);
    const tags = toArrayMaybe(getCI(obj, ['tags','keywords','labels'])).map(String);
    const sensitivity = String(getCI(obj, ['sensitivity','classification','risk']) ?? '').trim();
    const code = String(getCI(obj, ['code','id','ref']) ?? '').trim();
    const compliance = toArrayMaybe(getCI(obj, ['standards','regulations','frameworks','applicable_standards'])).map(String);
    return { category, description, rationale, fieldsList, examples, tags, sensitivity, code, compliance, _raw: obj };
  }

  const normalized = items.map((r, i) => normalize(r, i));
  let selected = new Set(normalized.map((_, i) => i));

  function updateCount() { selectionCount.textContent = selected.size + ' selected'; }

  function createCard(item, index) {
    const card = document.createElement('div'); card.className = 'cat-card';
    const header = document.createElement('div'); header.className = 'cat-header';

    const formCheck = document.createElement('div'); formCheck.className = 'form-check mt-1';
    const cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'form-check-input xcheck'; cb.id = 'cat_' + index; cb.checked = selected.has(index);
    cb.addEventListener('change', () => { if (cb.checked) selected.add(index); else selected.delete(index); updateCount(); });
    formCheck.appendChild(cb);

    const titleWrap = document.createElement('div');
    const title = document.createElement('label'); title.className = 'form-check-label cat-title'; title.setAttribute('for', cb.id);
    title.textContent = item.category;

    const tt = [];
    if (item.fieldsList && item.fieldsList.length) tt.push('Fields: ' + item.fieldsList.join(', '));
    if (item.rationale) tt.push('Rationale: ' + item.rationale);
    if (tt.length) { title.setAttribute('data-bs-toggle', 'tooltip'); title.setAttribute('data-bs-placement', 'top'); title.setAttribute('data-bs-title', tt.join('\n')); }

    const meta = document.createElement('div'); meta.className = 'meta';
    const bits = []; if (item.code) bits.push(item.code); if (item.sensitivity) bits.push(item.sensitivity);
    meta.textContent = bits.join(' • ');

    titleWrap.appendChild(title);
    if (bits.length) titleWrap.appendChild(meta);

    header.appendChild(formCheck); header.appendChild(titleWrap);

    const body = document.createElement('div');

    if (item.tags && item.tags.length) {
      const tagsWrap = document.createElement('div');
      item.tags.forEach(t => { const chip = document.createElement('span'); chip.className = 'chip'; chip.textContent = t; tagsWrap.appendChild(chip); });
      body.appendChild(tagsWrap);
    }

    if (item.description) {
      const desc = document.createElement('div'); desc.className = 'summary'; desc.textContent = item.description; body.appendChild(desc);
    }

    if (item.fieldsList && item.fieldsList.length) {
      const fldWrap = document.createElement('div');
      const label = document.createElement('div'); label.className = 'meta'; label.style.fontWeight = '600'; label.textContent = 'Fields covered:';
      fldWrap.appendChild(label);
      const chips = document.createElement('div');
      item.fieldsList.forEach(f => { const chip = document.createElement('span'); chip.className = 'chip'; chip.textContent = f; chips.appendChild(chip); });
      fldWrap.appendChild(chips);
      body.appendChild(fldWrap);
    }

    if (item.examples && item.examples.length) {
      const h6 = document.createElement('div'); h6.style.marginTop = '6px'; h6.style.fontWeight = '600'; h6.textContent = 'Examples:';
      const ul = document.createElement('ul'); item.examples.forEach(ex => { const li = document.createElement('li'); li.textContent = String(ex); ul.appendChild(li); });
      body.appendChild(h6); body.appendChild(ul);
    }

    if (item.compliance && item.compliance.length) {
      const compWrap = document.createElement('div');
      const label = document.createElement('div'); label.className = 'meta'; label.style.fontWeight = '600'; label.textContent = 'Related standards/regulations:';
      compWrap.appendChild(label);
      const chips = document.createElement('div'); item.compliance.forEach(c => { const chip = document.createElement('span'); chip.className = 'chip'; chip.textContent = c; chips.appendChild(chip); });
      compWrap.appendChild(chips);
      body.appendChild(compWrap);
    }

    card.appendChild(header); card.appendChild(body);
    return card;
  }

  catGrid.innerHTML = '';
  normalized.forEach((item, i) => catGrid.appendChild(createCard(item, i)));
  updateCount();

  if (selectAllBtn) selectAllBtn.addEventListener('click', () => {
    selected = new Set(normalized.map((_, i) => i));
    catGrid.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = true);
    updateCount();
  });
  if (deselectAllBtn) deselectAllBtn.addEventListener('click', () => {
    selected.clear();
    catGrid.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
    updateCount();
  });

  const subjForm = document.getElementById('subjForm');
  if (subjForm && hiddenJson) {
    subjForm.addEventListener('submit', function(e) {
      const chosen = Array.from(selected).map(i => normalized[i]._raw || normalized[i]);
      if (!chosen.length) { e.preventDefault(); alert('Please select at least one category before saving.'); return false; }
      hiddenJson.value = JSON.stringify(chosen);
    });
  }

  try { if (window.bootstrap && bootstrap.Tooltip) { document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); }); } } catch (e) {}
});
</script>
@endpush