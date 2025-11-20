# agents/config_wizard.py
import re

def _lc(s): return (s or "").strip().lower()

def _match_source(user_msg, sources):
    q = _lc(user_msg)
    best = None
    for s in sources:
        n = _lc(s['name'])
        if n in q:
            return s['name']
        # common synonyms
        if ('m365' in q or 'onedrive' in q or 'sharepoint' in q) and 'm365' in n:
            best = s['name']
        if 's3' in q and 's3' in n:
            best = s['name']
        if 'gdrive' in q or 'google drive' in q:
            if 'google' in n or 'gdrive' in n:
                best = s['name']
        if 'smb' in q and 'smb' in n:
            best = s['name']
        if 'nfs' in q and 'nfs' in n:
            best = s['name']
        if 'oracle' in q and 'oracle' in n:
            best = s['name']
        if 'mysql' in q and 'mysql' in n:
            best = s['name']
        if 'sql server' in q or 'mssql' in q:
            if 'sql server' in n or 'fabric' in n:
                best = s['name']
    return best

def _match_standards(user_msg, standards):
    q = _lc(user_msg)
    hits = []
    for st in standards:
        name = _lc(st['standard'])
        juris = _lc(st['jurisdiction'])
        if name in q or juris in q:
            hits.append(st['id'])
        # common shortcuts:
        if 'gdpr' in q and 'gdpr' in name:
            if st['id'] not in hits:
                hits.append(st['id'])
        if 'hipaa' in q and 'hipaa' in name:
            if st['id'] not in hits:
                hits.append(st['id'])
        if 'ccpa' in q and ('ccpa' in name or 'cpra' in name):
            if st['id'] not in hits:
                hits.append(st['id'])
        if 'australian privacy' in q and 'australian' in juris:
            if st['id'] not in hits:
                hits.append(st['id'])
        if 'pii' in q and 'pii' in name:
            if st['id'] not in hits:
                hits.append(st['id'])
    # dedup
    return list(dict.fromkeys(hits))

def _extract_single_value(user_msg):
    # naive: pick first token-like after ':' or 'is'
    m = re.search(r'(?::|is)\s*([^\s].+)$', user_msg, flags=re.I)
    if m:
        return m.group(1).strip()
    # fallback: return entire msg
    return user_msg.strip()

def agent_config_wizard(context: dict):
    """
    Conversational wizard:
      step 1: data source (save_step1)
      step 2: regulations (save_step2)
      step 3: metadata (save_step3)
      step 4: source-required fields (save_step4)
    Emits actions = [{op, payload}] for Laravel to persist.
    """
    catalog = context.get('catalog', {})
    catalog_sources = catalog.get('sources', [])
    catalog_standards = catalog.get('standards', [])
    catalog_metakeys = catalog.get('metadata_keys', [])

    messages = context.get('messages', [])
    user_msg = context.get('query') or (messages[-1]['content'] if messages else '')
    user_msg_l = _lc(user_msg)

    wiz = context.setdefault('wizard', {})
    step = wiz.get('step', 1)

    reply = ""
    actions = []
    followups = []

    def list_sources_top(n=6):
        items = [f"- {s['name']}: {s.get('description','')}" for s in catalog_sources[:n]]
        return "Here are popular data sources:\n" + "\n".join(items)

    def list_regulations_top(n=8):
        items = [f"- {st['standard']} ({st['jurisdiction']})" for st in catalog_standards[:n]]
        return "Some common regulations:\n" + "\n".join(items) + "\n(You can say e.g., 'GDPR and HIPAA')"

    def list_metakeys():
        items = [f"- {m['key']} ({m['id']})" for m in catalog_metakeys]
        return "Available metadata keys:\n" + "\n".join(items) + "\n(Reply 'all' to select all)"

    # Move step back/forward on "back" or "continue"
    if 'back' in user_msg_l:
        step = max(1, step - 1)
        wiz['step'] = step
    if 'continue' in user_msg_l or 'next' in user_msg_l:
        # continue means proceed to next pending step if current saved
        pass

    # Step 1: Data Source
    if step <= 1 and not wiz.get('data_source'):
        if user_msg_l in ('start', 'hi', 'hello', 'hey'):
            reply = "Let's get started. Which data source would you like to configure?\n" + list_sources_top()
            followups = [s['name'] for s in catalog_sources[:6]]
            wiz['step'] = 1
            return {"reply": reply, "followups": followups, "actions": []}

        pick = _match_source(user_msg, catalog_sources)
        if not pick:
            reply = "Please choose a data source to configure.\n" + list_sources_top()
            followups = [s['name'] for s in catalog_sources[:6]]
            wiz['step'] = 1
            return {"reply": reply, "followups": followups, "actions": []}

        wiz['data_source'] = pick
        wiz['step'] = 2
        actions.append({"op":"save_step1","payload":{"data_source_name": pick}})
        reply = f"Great. We'll configure: {pick}.\nNext, which privacy regulations apply to your data?\n" + list_regulations_top()
        followups = ["GDPR", "HIPAA", "CCPA/CPRA", "Australian Privacy Act", "Skip"]
        return {"reply": reply, "followups": followups, "actions": actions}

    # Step 2: Regulations
    if step <= 2 and not wiz.get('regulation_ids'):
        if 'skip' in user_msg_l:
            wiz['regulation_ids'] = []
            wiz['step'] = 3
            actions.append({"op":"save_step2","payload":{"standard_ids": []}})
            reply = "Skipping regulation selection. Next, choose which metadata to store for files with sensitive data.\n" + list_metakeys()
            followups = ["All", "Minimal", "Custom"]
            return {"reply": reply, "followups": followups, "actions": actions}

        matched = _match_standards(user_msg, catalog_standards)
        if not matched:
            reply = "Please mention the regulations (by name or jurisdiction) that apply.\n" + list_regulations_top()
            followups = ["GDPR", "HIPAA", "CCPA/CPRA", "Australian Privacy Act", "Skip"]
            wiz['step'] = 2
            return {"reply": reply, "followups": followups, "actions": []}

        wiz['regulation_ids'] = matched
        wiz['step'] = 3
        actions.append({"op":"save_step2","payload":{"standard_ids": matched}})
        reply = "Saved your regulation choices. Now select metadata keys (or say 'all').\n" + list_metakeys()
        followups = ["All", "Minimal", "Custom"]
        return {"reply": reply, "followups": followups, "actions": actions}

    # Step 3: Metadata keys
    if step <= 3 and not wiz.get('metadata_key_ids'):
        key_ids = []
        if 'all' in user_msg_l:
            key_ids = [m['id'] for m in catalog_metakeys]
        elif 'minimal' in user_msg_l:
            # pick a minimal default: regulations + risk
            wanted = set([_lc('regulations'), _lc('risk_rating'), _lc('data_classification')])
            for m in catalog_metakeys:
                if _lc(m['key']) in wanted:
                    key_ids.append(m['id'])
            if not key_ids:
                key_ids = [m['id'] for m in catalog_metakeys[:3]]
        else:
            # try parse numeric ids or words
            nums = re.findall(r'\b(\d{1,4})\b', user_msg_l)
            if nums:
                ids = [int(x) for x in nums]
                known = set([m['id'] for m in catalog_metakeys])
                key_ids = [i for i in ids if i in known]
            if not key_ids:
                # fallback: pick first few
                key_ids = [m['id'] for m in catalog_metakeys[:5]]

        wiz['metadata_key_ids'] = key_ids
        wiz['step'] = 4
        actions.append({"op":"save_step3","payload":{"metadata_key_ids": key_ids}})

        # now prompt for required fields for selected data source
        ds_name = wiz.get('data_source')
        req_fields = []
        for s in catalog_sources:
            if s['name'] == ds_name:
                req_fields = s.get('required_fields') or []
                break
        wiz['source_required_fields'] = req_fields
        wiz['config_values'] = {}
        wiz['pending_field_index'] = 0

        if not req_fields:
            # nothing to collect, finish
            reply = "No connection fields required for this source. Configuration is complete. Do you want to complete and save?"
            followups = ["Finish", "Add another source"]
            return {"reply": reply, "followups": followups, "actions": actions}

        f0 = req_fields[0]
        reply = f"Now let's enter connection details for {ds_name}.\nPlease provide '{f0}'. You can reply like '{f0}: <value>'."
        followups = [f"{f0}: ..."]
        return {"reply": reply, "followups": followups, "actions": actions}

    # Step 4: Collect required fields one-by-one
    req_fields = wiz.get('source_required_fields', [])
    idx = int(wiz.get('pending_field_index', 0))
    if req_fields and idx < len(req_fields):
        current_field = req_fields[idx]
        # Capture value from user_msg
        value = _extract_single_value(user_msg)
        if not value or value.lower() in ('continue','next'):
            # re-ask
            reply = f"Please provide '{current_field}'. You can reply like '{current_field}: <value>'."
            followups = [f"{current_field}: ..."]
            return {"reply": reply, "followups": followups, "actions": []}

        # store and move next
        cfgvals = wiz.get('config_values', {})
        key_snake = re.sub(r'[\s\-]+', '_', current_field.strip().lower())
        cfgvals[key_snake] = value
        wiz['config_values'] = cfgvals
        idx += 1
        wiz['pending_field_index'] = idx

        if idx >= len(req_fields):
            # done with config values
            actions.append({"op":"save_step4", "payload":{"config_values": cfgvals}})
            wiz['step'] = 4
            reply = "Thanks, all required details collected. Do you want to complete and save?"
            followups = ["Finish", "Add another source"]
            return {"reply": reply, "followups": followups, "actions": actions}
        else:
            nf = req_fields[idx]
            reply = f"Got it. Next, please provide '{nf}'."
            followups = [f"{nf}: ..."]
            return {"reply": reply, "followups": followups, "actions": []}

    # Finish handling
    if 'finish' in user_msg_l or 'complete' in user_msg_l:
        actions.append({"op": "complete", "payload": {}})
        reply = "Configuration saved as complete. You can proceed to run classification from your dashboard."
        followups = ["Go to dashboard", "Configure another data source"]
        return {"reply": reply, "followups": followups, "actions": actions}

    # default fallback
    reply = "I can help configure your data sources. Say 'start' or tell me a data source (e.g., 'Configure M365 and GDPR')."
    followups = ["Start", "M365 - OneDrive, SharePoint & Teams Files", "AWS S3", "Google Drive", "SMB Fileshare"]
    return {"reply": reply, "followups": followups, "actions": []}