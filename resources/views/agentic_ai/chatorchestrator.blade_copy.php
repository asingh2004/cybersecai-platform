@extends('template')
@push('styles')
<style>

    .chat-window { border: 1px solid #aaa; padding: 2rem; height: 500px; overflow-y: auto; background: #fafbff;}
  	  	.chat-window table {
    display: block;
    overflow-x: auto;
    max-width: 100%;
    width: fit-content;
    min-width: 700px; /* or whatever you want as min width */
    white-space: nowrap;
}

.chat-window th, .chat-window td {
    word-break: break-word;
    max-width: 350px; /* optional: limit cell grow */
    padding: 0.4em 0.6em;
    border: 1px solid #eee;
}
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
    .chat-message ul, .chat-message ol { margin-left:1em;}
    .chat-message pre { background:#222; color:#FFF; padding:0.5em 1em; border-radius:8px;}
    .chat-message code { background:#eee; padding:2px 6px; border-radius:4px;}
    .chat-message h1, .chat-message h2, .chat-message h3 { margin-top:1em; }
    .usecase-btn-area {margin-bottom:1.5em;}
    .usecase-btn {margin: 0.25em 0.5em 0.25em 0; white-space:normal;}

.prompt-suggestion-btn {
  display: inline-block;
  margin: 0 8px 8px 0;
  padding: .5em 1em;
  border-radius: 8px;
  background: #e3f2fd;
  color: #222;
  cursor: pointer;
  border: 1px solid #2196f3;
  font-weight: 500;
  transition: background 0.18s, border 0.18s;
}
.prompt-suggestion-btn:hover {
  background: #bbdefb;
  border-color: #1565c0;
}
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
                        <div class="form-section">
                            <h1 class="form-title">
                                       
                                <strong>cybersecai Chat Orchestrator - talk to your data!</strong>
                            </h1>
     
 <div class="alert alert-info mb-4">

                              
    <b>About the Orchestrator:</b> <br>
    <h4> <br>
    <ul class="mb-1">
        <li>* Supports multiple personas - Board Member, Auditor, Data Privacy & Compliance Lawyer, Compliance Officer Cyber Security Expert, Data Steward & more</li>
        <li>* Jumstart your chat by selecting a tile and adjusting your query! </li>
      </ul></h4>
</div>
                  
                        <div class="row usecase-btn-area">
   						@php
    // 1. Group use cases by persona
    $grouped = [];
    foreach($useCases as $c) {
        $grouped[$c['persona']][] = $c;
    }
    // 2. Sort personas alphabetically
    $personaNames = array_keys($grouped);
    sort($personaNames);

    // 3. Assign outline colours (repeat if not enough)
    $outlineColors = [
        'border-primary bg-primary bg-opacity-10',
        'border-success bg-success bg-opacity-10',
        'border-danger bg-danger bg-opacity-10',
        'border-warning bg-warning bg-opacity-10',
        'border-info bg-info bg-opacity-10',
        'border-secondary bg-secondary bg-opacity-10'
    ];
@endphp

	<div class="row usecase-btn-area">
      <div class="col-12 mb-2">
    <h5 class="mt-4 mb-2 fw-bold text-primary">Data Breach Compliance Lawyer</h5>
    <div class="p-2 mb-3 rounded-3 border-dark bg-light bg-opacity-25" style="border-left: 8px solid #333; min-height:60px;">
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
                <div class="p-2 mb-3 rounded-3 {{ $clr }}" style="border-left: 8px solid; min-height:60px;">
                    <div class="d-flex flex-wrap">
                        @foreach($grouped[$persona] as $c)
                        <div class="me-2 mb-2" style="min-width:210px;">
                            <button class="btn btn-outline-primary use-case-btn usecase-btn"
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
                      
                        <div id="chat-window" class="chat-window mb-3"></div>
                         
                        <div class="chat-controls">
                            <input type="text" id="query-input" class="form-control" placeholder="Select a Query from the top or start typing to see suggestions ..." autocomplete="off">
                            <div id="prompt-suggestions" class="mb-3"></div>
                          	<button class="btn btn-primary btn-lg btn-lg-custom mt-3" id="send-btn">Chat with cybersecai Expert Agents</button>
                        </div>
                        <input type="hidden" id="session_id">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
  

<script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<!-- And include daterangepicker.css if needed too -->
<script>
window.allUseCases = @json($useCases);
</script>
 
<script>
let messages = [];
let session_id = null;
let prior_context = {};

const md = window.markdownit({ html: true, linkify: true, breaks: true, typographer: true });

const slotFieldLabels = {
    event_type: {
        label: "Event Type",
        example: "Example: Accidental data sharing, Exfiltration of data, Unauthorized export, Ransomware attack"
    },
    data: {
        label: "Data Description / Details",
        example: "Example: Name, TFN, Medicare Number, Address, Passport Details, DL, Patient Record, Student Record"
    },
    standard: {
        label: "Compliance Standard (auto-filled)",
        example: "E.g.: GDPR, Australian Privacy Act"
    },
    jurisdiction: {
        label: "Jurisdiction (Country/Law, auto-filled)",
        example: "E.g.: Australia, UK, USA, EU, Canada"
    },
  	corporate_domains: {
    	label: "Corporate Email Domains, needed to establish external threat",
    	example: "Example: cybersecai.io, myopenai.com"
	},
  	domain: { label: "Domain (Target to test)", example: "Example: cybersecai.io" },
    region: {
        label: "Region / Country / Jurisdiction",
        example: "Example: Australia, UK, USA, Germany, Canada"
    }
    // Add others as needed
};

function renderChat() {
    const el = document.getElementById('chat-window');
    el.innerHTML = '';
    messages.forEach(m => {
        let div = document.createElement('div');
        div.className = 'chat-message ' + m.role;
        let htmlSafeContent = m.role === 'ai' ? md.render(m.content || '') : md.renderInline(m.content || '');
        div.innerHTML = `<div class="bubble">${htmlSafeContent}</div>`;
        el.appendChild(div);
    });
    el.scrollTop = el.scrollHeight;
}


// =========== PROMPT SUGGESTION LOGIC =============
function showPromptSuggestions(inputText) {
    // Guard: make sure data is on window
    if (!window.allUseCases || !Array.isArray(window.allUseCases)) {
        document.getElementById('prompt-suggestions').style.display = 'none';
        return;
    }
    const container = document.getElementById('prompt-suggestions');
    container.innerHTML = '';

    let matches = [];

    // Suggest defaults when input is empty or too short
    if (!inputText || inputText.length < 2) {
        matches = window.allUseCases.slice(0, 5); // First 5 as popular
        if (matches.length === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = '';

        let html = '<div class="mb-2" style="color:#3949ab;font-weight:600;">Try a popular prompt:</div>';
        matches.forEach(usecase => {
            html += `
                <button type="button"
                    class="prompt-suggestion-btn"
                    data-persona="${usecase.persona}"
                    data-prompt="${(usecase.prompt || '').replace(/"/g,'&quot;')}"
                    data-operation="${usecase.operation || ''}"
                    data-args='${JSON.stringify(usecase.args || {})}'
                    data-config_ids='${JSON.stringify(usecase.config_ids || [])}'
                    title="${usecase.alt || ''}"
                >${usecase.label}</button>
            `;
        });
        container.innerHTML = html;
    } else {
        // Fuzzy match to prompt or label fields
        matches = window.allUseCases.filter(u =>
            (u.prompt && u.prompt.toLowerCase().includes(inputText.toLowerCase())) ||
            (u.label && u.label.toLowerCase().includes(inputText.toLowerCase()))
        ).slice(0, 5); // limit to 5 matches

        if (matches.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = '';
        let html = '<div class="mb-2" style="color:#3949ab;font-weight:600;">Did you mean:</div>';
        matches.forEach(usecase => {
            html += `
                <button type="button"
                    class="prompt-suggestion-btn"
                    data-persona="${usecase.persona}"
                    data-prompt="${(usecase.prompt || '').replace(/"/g,'&quot;')}"
                    data-operation="${usecase.operation || ''}"
                    data-args='${JSON.stringify(usecase.args || {})}'
                    data-config_ids='${JSON.stringify(usecase.config_ids || [])}'
                    title="${usecase.alt || ''}"
                >${usecase.label}</button>
            `;
        });
        container.innerHTML = html;
    }

    // Wire up all the suggestion buttons (works for both default and matched)
    container.querySelectorAll('.prompt-suggestion-btn').forEach(btn => {
        btn.onclick = function() {
            document.getElementById('persona').value = this.dataset.persona;
            document.getElementById('query-input').value = this.dataset.prompt;
            document.getElementById('query-input').focus();
            // Set up context as in your use-case logic
            prior_context = {};
            prior_context['persona'] = this.dataset.persona;
            if(this.dataset.operation) {
                prior_context['operation'] = this.dataset.operation;
                if (this.dataset.operation === 'm365_compliance_auto') {
                    prior_context['agent'] = 'm365_compliance_auto';
                }
                if (this.dataset.operation === 'pentest_auto') {
                    prior_context['agent'] = 'pentest_auto';
                }
            }
            if(this.dataset.args) {
                try {
                    prior_context['args'] = JSON.parse(this.dataset.args);
                } catch(e) {
                    prior_context['args'] = {};
                }
            }
            if(this.dataset.config_ids) {
                try {
                    prior_context['config_ids'] = JSON.parse(this.dataset.config_ids);
                } catch(e) {
                    prior_context['config_ids'] = [];
                }
            }
            // Remove suggestions after selection
            document.getElementById('prompt-suggestions').style.display = 'none';
        };
    });
}
// =========== END SUGGESTION LOGIC =============


// =========== DOM EVENT HANDLING ===============
document.addEventListener('DOMContentLoaded', function(){
    // Suggestions on every input and focus!
    document.getElementById('query-input').addEventListener('input', function(e) {
        showPromptSuggestions(this.value);
    });
    document.getElementById('query-input').addEventListener('focus', function(e) {
        showPromptSuggestions(this.value);
    });

    // Tiles up top
    document.querySelectorAll('.use-case-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('persona').value = this.dataset.persona;
            document.getElementById('query-input').value = this.dataset.prompt;
            document.getElementById('query-input').focus();

            // --- Use-case agentic logic: collect operation/args/config_ids into prior_context if present
            prior_context = {}; // Always reset so it's "clean" per button click
            // Set persona always in prior_context
            prior_context['persona'] = this.dataset.persona;

            if(this.dataset.operation) {
                prior_context['operation'] = this.dataset.operation;
                if (this.dataset.operation === 'm365_compliance_auto') {
                    prior_context['agent'] = 'm365_compliance_auto';
                }
                if (this.dataset.operation === 'pentest_auto') {
                    prior_context['agent'] = 'pentest_auto';
                }
            }
            if(this.dataset.args) {
                try { prior_context['args'] = JSON.parse(this.dataset.args); }
                catch(e) { prior_context['args'] = {}; }
            }
            if(this.dataset.config_ids) {
                try { prior_context['config_ids'] = JSON.parse(this.dataset.config_ids); }
                catch(e) { prior_context['config_ids'] = []; }
            }
        });
    });
});

// ============= HANDLE SEND (resets and prompts) ===============
document.getElementById('send-btn').onclick = function(e) {
    e.preventDefault();
    let queryInput = document.getElementById('query-input');
    let query = queryInput.value;

    // UX: Clean input and prepare for suggestion prompt if needed.
    // (Do NOT set .value to a static instructional string!)
    queryInput.value = "";
    queryInput.placeholder = "Select a Query from the top or start typing to see suggestions ...";
    showPromptSuggestions("");

    // Optional: Dimming input briefly
    queryInput.readOnly = true;
    setTimeout(() => { queryInput.readOnly = false; }, 800);

    if (!query.trim()) return;
    let persona = document.getElementById('persona').value;

    // SLOT filler logic BEFORE actually sending:
    // (Handles ANY kind of slot, not just domain! You can generalize more if needed)
if (prior_context && prior_context.pending_field) {
    prior_context.args = prior_context.args || {};
    prior_context.args[prior_context.pending_field] = query;
    delete prior_context.pending_field;
    let oldLab = document.getElementById('user-prompt-label');
    if (oldLab) { oldLab.remove(); }
    // DO NOT return here! Now, allow the handler to continue, and send!
    queryInput.value = '';
    queryInput.placeholder = "Select a Query from the top or start typing to see suggestions ...";
    showPromptSuggestions('');
    // Do not return; continue below to send
}

    // ---- SLOT-FILL: Pentest_auto requires DOMAIN before send ----
    if (prior_context.operation === 'pentest_auto') {
        // If missing or blank:
        if (!prior_context.args || !prior_context.args.domain || !prior_context.args.domain.trim()) {
            // Prompt the user for the domain before sending!
            const slotInfo = slotFieldLabels.domain || {label: "Domain to Pentest", example: "e.g., example.com"};
            queryInput.value = '';
            queryInput.placeholder = slotInfo.example || "e.g., example.com";
            let oldLab = document.getElementById('user-prompt-label');
            if (oldLab) oldLab.remove();
            let lbl = document.createElement('div');
            lbl.id = 'user-prompt-label';
            lbl.style = 'color:#365;font-size:1em;font-weight:600;margin-bottom:2px;';
            lbl.textContent = slotInfo.label || "Enter domain to pentest:";
            if (slotInfo.example) {
                let ex = document.createElement('div');
                ex.style = 'color:#aaa;font-size:0.98em;font-weight:400;margin-bottom:4px;';
                ex.textContent = slotInfo.example;
                lbl.appendChild(document.createElement('br'));
                lbl.appendChild(ex);
            }
            queryInput.parentNode.insertBefore(lbl, queryInput);
            prior_context.pending_field = 'domain';
            queryInput.focus();
            return; // Wait for user entry!
        }
    }

// --- Actually send the user chat message to the backend/agent ---
messages.push({role: 'user', content: query});
renderChat();

// Button state
let sendBtn = document.getElementById('send-btn');
sendBtn.disabled = true;
sendBtn.innerText = "Awaiting cybersecai Agent's Response";

if (!session_id || Object.keys(prior_context).length === 0) {
    prior_context['persona'] = persona;
}

fetch('{{ route("chatorchestrator.orchestrate") }}', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
    },
    body: JSON.stringify({
        query: query,
        prior_context: prior_context,
        session_id: session_id,
        messages: messages
    }),
})
.then(r => r.json())
.then(resp => {
    let ai = resp.ai || {};
    messages.push({role: 'ai', content: ai.content});
    renderChat();

    sendBtn.disabled = false;
    sendBtn.innerText = "Chat with cybersecai Expert Agents";

    // Always reset for next user input
    queryInput.value = '';
    queryInput.placeholder = "Select a Query from the top or start typing to see suggestions ...";
    showPromptSuggestions('');
    queryInput.focus();

    // Pass on prior context as returned
    if (ai.prior_context) {
        prior_context = ai.prior_context;
    }

    // Slot filler/next step logic if agent needs more info
    if (ai.pending) {
        let m = ai.content.match(/I need the following: ([^,\. ]+)/i);
        let slot = m ? m[1] : '';
        let slotInfo = slotFieldLabels[slot] || {label: slot, example: ""};
        let promptLabel = slotInfo.label
            ? `Please enter ${slotInfo.label}:`
            : "Please provide required info:";
        queryInput.value = '';
        queryInput.placeholder = slotInfo.example || '';
        let oldLab = document.getElementById('user-prompt-label');
        if (oldLab) oldLab.remove();
        let lbl = document.createElement('div');
        lbl.id = 'user-prompt-label';
        lbl.style = 'color:#365;font-size:1em;font-weight:600;margin-bottom:2px;';
        lbl.textContent = promptLabel;
        if (slotInfo.example) {
            let ex = document.createElement('div');
            ex.style = 'color:#aaa;font-size:0.98em;font-weight:400;margin-bottom:4px;';
            ex.textContent = slotInfo.example;
            lbl.appendChild(document.createElement('br'));
            lbl.appendChild(ex);
        }
        var parent = queryInput.parentNode;
        parent.insertBefore(lbl, queryInput);
    }

    if (ai.pending) {
        session_id = ai.session_id || session_id;
    } else {
        session_id = null;
        prior_context = {};
    }

    // ======= LOG FOLLOWUPS FOR DEBUG =======
    console.log("Followups in API response:", ai.followups);

    // ========================
    // FOLLOWUP BUTTON HANDLING
    // ========================
    document.querySelectorAll('.followups-panel').forEach(el => el.remove());
    if(ai.followups && Array.isArray(ai.followups) && ai.followups.length > 0) {
        let followupsDiv = document.createElement("div");
        followupsDiv.className = "followups-panel mt-3";
        followupsDiv.innerHTML = "<b>What would you like to do next?</b><br>";
        ai.followups.forEach(fu => {
            let btn = document.createElement('button');
            btn.className = "btn btn-outline-success ms-2 followup-btn";
            btn.textContent = fu.label;
            btn.onclick = function() {
                // Fill prior_context for followup:
                prior_context['operation'] = fu.operation;
                prior_context['args'] = fu.args || {};
                prior_context['config_ids'] = fu.config_ids || [];
                document.getElementById('query-input').value = fu.prompt || fu.label;
                // Optionally auto-submit:
                document.getElementById('send-btn').click();
            };
            followupsDiv.appendChild(btn);
        });
        document.getElementById('chat-window').appendChild(followupsDiv);
    }
})
.catch(err => {
    messages.push({role: 'ai', content: 'ERROR: ' + err});
    renderChat();
    sendBtn.disabled = false;
    sendBtn.innerText = "Send";
});
};
</script>
@endsection