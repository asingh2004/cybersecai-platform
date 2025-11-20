<?php
// app/Http/Controllers/AgenticAI/ConfigAssistantController.php
namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\DataSourceRef;
use App\Models\ComplianceStandard;
use App\Models\MetadataKey;
use App\Models\DataConfig;

class ConfigAssistantController extends Controller
{
    public function index(Request $request)
    {
        $config     = $this->ensureConfig();
        $catalog    = $this->buildCatalog();
        $progress   = $this->progressStatus();
        $state      = $this->stateSnapshot();
        $subPrompts = $this->subPrompts($progress, $catalog, $state);

        $orchUrl = config('services.agentic.orchestrator_url', 'http://127.0.0.1:8224/agentic/auto_orchestrate');

        return view('agentic_ai.config_assistant', [
            'catalog'              => $catalog,
            'orchUrl'              => $orchUrl,
            'config_id'            => $config->id ?? null,
            'initial_progress'     => $progress,
            'initial_state'        => $state,
            'initial_sub_prompts'  => $subPrompts,
        ]);
    }

    // Chat proxy to Python Orchestrator
    public function chat(Request $request)
    {
        $request->validate([
            'message'    => 'required|string',
            'catalog'    => 'nullable|array',
            'session_id' => 'nullable|string'
        ]);

        $orchUrl = config('services.agentic.orchestrator_url', 'http://127.0.0.1:8224/agentic/auto_orchestrate');

        $catalog    = $request->input('catalog') ?: $this->buildCatalog();
        $session_id = $request->input('session_id', null);
        $user       = Auth::user();

        $payload = [
            'user_query'   => $request->input('message'),
            'session_id'   => $session_id,
            'prior_context'=> [
                'agent'   => 'config_wizard',
                'user_id' => $user ? $user->id : null,
                'catalog' => $catalog
            ]
        ];

        try {
            $resp = Http::timeout(60)->asJson()->post($orchUrl, $payload);
            if (!$resp->successful()) {
                return response()->json(['error' => 'AI Orchestrator error: ' . $resp->status() . ' ' . $resp->body()], 502);
            }
            $data = $resp->json();

            $progress   = $this->progressStatus();
            $state      = $this->stateSnapshot();
            $subPrompts = $this->subPrompts($progress, $catalog, $state);

            $result = [
                'pending'      => $data['pending'] ?? false,
                'question'     => $data['question'] ?? null,
                'result'       => $data['result'] ?? '',
                'session_id'   => $data['session_id'] ?? $session_id,
                'followups'    => $data['followups'] ?? [],
                'actions'      => $data['actions'] ?? [],
                'progress'     => $progress,
                'state'        => $state,
                'sub_prompts'  => $subPrompts,
            ];
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error("[AgenticChat] " . $e->getMessage());
            return response()->json(['error' => 'Chat failed: ' . $e->getMessage()], 500);
        }
    }

    // Apply Agent "actions" -> persist to DB
    public function save(Request $request)
    {
        $op      = $request->input('op');
        $payload = $request->input('payload', []);
        $config  = $this->ensureConfig();

        try {
            switch ($op) {
                case 'save_step1':
                    $this->saveStep1($config, $payload);
                    $message = "Saved Data Source selection.";
                    break;
                case 'save_step2':
                    $this->saveStep2($config, $payload);
                    $message = "Saved applicable regulations.";
                    break;
                case 'save_step3':
                    $this->saveStep3($config, $payload);
                    $message = "Saved metadata keys.";
                    break;
                case 'save_step4':
                    $this->saveStep4($config, $payload);
                    $message = "Saved connection details for data source.";
                    break;
                case 'complete':
                    $config->update(['status' => 'complete']);
                    $message = "Configuration complete!";
                    break;
                case 'goto_step':
                    $step = (int)($payload['step'] ?? 1);
                    $step = max(1, min(4, $step));
                    $this->setActiveOverride($step);
                    $message = "Moved to step {$step}.";
                    break;
                case 'reset_config':
                    $this->resetCurrentConfig();
                    $message = "Configuration reset. Started a fresh one.";
                    break;
                default:
                    return response()->json(['error' => 'Unknown action: ' . $op], 400);
            }

            $catalog    = $this->buildCatalog();
            $progress   = $this->progressStatus();
            $state      = $this->stateSnapshot();
            $subPrompts = $this->subPrompts($progress, $catalog, $state);

            return response()->json([
                'success'     => true,
                'message'     => $message,
                'progress'    => $progress,
                'state'       => $state,
                'sub_prompts' => $subPrompts,
            ]);
        } catch (\Throwable $e) {
            \Log::error("[AgenticSave][$op] " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // Helpers

    private function ensureConfig()
    {
        $editId = session('wizard.edit_id');
        if ($editId) {
            $cfg = auth()->user()->dataConfigs()->find($editId);
            if ($cfg) return $cfg;
        }
        $cfg = auth()->user()->dataConfigs()->create(['status' => 'in_progress']);
        session(['wizard.edit_id' => $cfg->id]);
        return $cfg;
    }

    private function resetCurrentConfig(): void
    {
        $user = auth()->user();
        $editId = session('wizard.edit_id');
        if ($editId && $user) {
            $cfg = $user->dataConfigs()->find($editId);
            if ($cfg) {
                $cfg->delete();
            }
        }
        session()->forget([
            'wizard.edit_id',
            'wizard.data_sources',
            'wizard.regulations',
            'wizard.metadata',
            'wizard.active_override',
        ]);
        // Create a fresh config
        $this->ensureConfig();
    }

    private function setActiveOverride(?int $step): void
    {
        if ($step === null) {
            session()->forget('wizard.active_override');
        } else {
            session(['wizard.active_override' => max(1, min(4, (int)$step))]);
        }
    }

    private function buildCatalog()
    {
        $sourcesRef = DataSourceRef::select('data_source_name', 'description', 'storage_type_config')->get();
        $sources = [];
        foreach ($sourcesRef as $s) {
            $req = [];
            if (is_array($s->storage_type_config)) {
                $req = $s->storage_type_config['required'] ?? [];
            } elseif ($s->storage_type_config) {
                $arr = @json_decode($s->storage_type_config, true);
                $req = $arr['required'] ?? [];
            }
            $sources[] = [
                'name'            => $s->data_source_name,
                'description'     => $s->description ?? '',
                'required_fields' => $req
            ];
        }

        $standardsRef = ComplianceStandard::select('id', 'standard', 'jurisdiction', 'fields')->get();
        $standards = [];
        foreach ($standardsRef as $st) {
            $fieldsStr = $st->fields ?? '';
            $fieldsArr = array_values(array_filter(array_map('trim', explode(',', $fieldsStr ?: ''))));
            $standards[] = [
                'id'           => $st->id,
                'standard'     => $st->standard,
                'jurisdiction' => $st->jurisdiction,
                'fields'       => $fieldsArr
            ];
        }

        $metaRef = MetadataKey::select('id', 'key', 'description')->get();
        $metakeys = [];
        foreach ($metaRef as $m) {
            $metakeys[] = ['id' => $m->id, 'key' => $m->key, 'description' => $m->description];
        }

        return [
            'sources'       => $sources,
            'standards'     => $standards,
            'metadata_keys' => $metakeys
        ];
    }

    private function progressStatus()
    {
        $cfg = $this->ensureConfig();
        $done = [];
        $active = 1;
        if (!empty($cfg->data_sources)) { $done[] = 1; $active = 2; }
        if (!empty($cfg->regulations))  { $done[] = 2; $active = 3; }
        if (!empty($cfg->metadata))     { $done[] = 3; $active = 4; }
        if (!empty($cfg->m365_config_json)) { $done[] = 4; $active = 4; }

        // Allow manual override (back/forward navigation)
        $override = session('wizard.active_override');
        if ($override) {
            $active = max(1, min(4, (int)$override));
        }

        return ['done' => $done, 'active' => $active];
    }

    private function stateSnapshot(): array
    {
        $cfg = $this->ensureConfig();

        $regs = [];
        if (!empty($cfg->regulations)) {
            if (is_array($cfg->regulations)) {
                $regs = $cfg->regulations;
            } else {
                $regs = @json_decode($cfg->regulations, true) ?: [];
            }
        }

        $meta = [];
        if (!empty($cfg->metadata)) {
            if (is_array($cfg->metadata)) {
                $meta = $cfg->metadata;
            } else {
                $meta = @json_decode($cfg->metadata, true) ?: [];
            }
        }

        $conn = [];
        if (!empty($cfg->m365_config_json)) {
            if (is_array($cfg->m365_config_json)) {
                $conn = $cfg->m365_config_json;
            } else {
                $conn = @json_decode($cfg->m365_config_json, true) ?: [];
            }
        }

        return [
            'data_source_name' => $cfg->data_sources ?? null,
            'regulations'      => $regs,
            'metadata'         => $meta,
            'connection'       => $conn,
        ];
    }

    private function subPrompts(array $progress, array $catalog, array $state = []): array
    {
        $active = $progress['active'] ?? 1;

        if ($active === 1) {
            $items = [];
            foreach (($catalog['sources'] ?? []) as $s) {
                $items[] = ['label' => $s['name'], 'value' => $s['name']];
            }
            return ['type' => 'sources', 'items' => $items];
        }

        if ($active === 2) {
            $items = [];
            foreach (($catalog['standards'] ?? []) as $st) {
                $label = trim($st['standard'] . ($st['jurisdiction'] ? ' (' . $st['jurisdiction'] . ')' : ''));
                $items[] = ['id' => $st['id'], 'label' => $label, 'value' => $st['id']];
            }
            return ['type' => 'standards', 'items' => $items];
        }

        if ($active === 3) {
            $items = [];
            foreach (($catalog['metadata_keys'] ?? []) as $m) {
                $items[] = ['id' => $m['id'], 'label' => $m['key'], 'value' => $m['id']];
            }
            return ['type' => 'metadata', 'items' => $items];
        }

        if ($active === 4) {
            $ds = $state['data_source_name'] ?? null;
            $required = [];
            if ($ds) {
                foreach (($catalog['sources'] ?? []) as $s) {
                    if ($s['name'] === $ds) {
                        $required = $s['required_fields'] ?? [];
                        break;
                    }
                }
            }
            $items = array_map(fn($f) => ['label' => $f, 'value' => $f], $required);
            return ['type' => 'connection', 'items' => $items];
        }

        return ['type' => 'generic', 'items' => []];
    }

    private function saveStep1(DataConfig $config, array $payload)
    {
        $name = trim((string)($payload['data_source_name'] ?? ''));
        if (!$name) throw new \Exception("data_source_name is required.");
        $exists = DataSourceRef::where('data_source_name', $name)->exists();
        if (!$exists) throw new \Exception("Unknown data source: $name");
        $config->update(['data_sources' => $name, 'status' => 'in_progress']);
        session(['wizard.data_sources' => $name]);

        // Always advance to Step 2 after saving Step 1
        $this->setActiveOverride(2);
    }

    private function saveStep2(DataConfig $config, array $payload)
    {
        $ids = $payload['standard_ids'] ?? [];
        if (!is_array($ids) || !count($ids)) {
            throw new \Exception("standard_ids must be a non-empty array");
        }
        $stds = ComplianceStandard::whereIn('id', $ids)->get();
        if ($stds->isEmpty()) throw new \Exception("No standards found for provided IDs.");

        $regs = [];
        foreach ($stds as $st) {
            $fieldsStr = $st->fields ?? '';
            $fieldsArr = array_values(array_filter(array_map('trim', explode(',', $fieldsStr ?: ''))));
            $regs[] = [
                'standard'     => $st->standard,
                'jurisdiction' => $st->jurisdiction,
                'fields'       => $fieldsArr
            ];
        }
        $config->update(['regulations' => json_encode($regs, JSON_UNESCAPED_UNICODE)]);
        session(['wizard.regulations' => $regs]);

        // Always advance to Step 3 after saving Step 2
        $this->setActiveOverride(3);
    }

    private function saveStep3(DataConfig $config, array $payload)
    {
        $ids = $payload['metadata_key_ids'] ?? [];
        if (!is_array($ids) || !count($ids)) {
            throw new \Exception("metadata_key_ids must be a non-empty array");
        }
        $valid = MetadataKey::whereIn('id', $ids)->pluck('id')->toArray();
        if (count($valid) !== count(array_unique($ids))) {
            throw new \Exception("One or more metadata_key_ids are invalid.");
        }
        $metadata = ['selected_metadata_keys' => array_values(array_unique($ids))];
        $config->update(['metadata' => json_encode($metadata)]);
        session(['wizard.metadata' => $metadata]);

        // Always advance to Step 4 after saving Step 3
        $this->setActiveOverride(4);
    }

    private function saveStep4(DataConfig $config, array $payload)
    {
        $vals = $payload['config_values'] ?? [];
        if (!is_array($vals) || !count($vals)) {
            throw new \Exception("config_values must be a non-empty object/map.");
        }
        $source = $config->data_sources ?? '';
        $sourceRef = DataSourceRef::where('data_source_name', $source)->first();
        if (!$sourceRef) throw new \Exception("Selected source not found: $source");

        $required = [];
        if (is_array($sourceRef->storage_type_config)) {
            $required = $sourceRef->storage_type_config['required'] ?? [];
        } elseif ($sourceRef->storage_type_config) {
            $arr = @json_decode($sourceRef->storage_type_config, true);
            $required = $arr['required'] ?? [];
        }

        $normalized = [];
        foreach ($required as $field) {
            $key = strtolower(str_replace([' ', '-'], '_', $field));
            $val = $vals[$key] ?? $vals[$field] ?? null;
            if (!is_string($val)) $val = (string)$val;
            if (!strlen(trim((string)$val))) {
                throw new \Exception("Missing value for required field: {$field}");
            }
            $normalized[$field] = $val;
        }

        if (stripos($source, 'M365') !== false || stripos($source, 'OneDrive') !== false || stripos($source, 'SharePoint') !== false) {
            $config_id = $config->id;
            $user_id   = auth()->id();
            $combo_id  = $user_id . $config_id;
            $webhook_url = url('/webhook/' . $combo_id);
            $normalized['webhook_url'] = $webhook_url;
            $normalized['resource'] = 'bulk';
            $normalized['webhook_client_state'] = $combo_id;
        }

        $config->update([
            'm365_config_json' => $normalized,
            'status'           => 'in_progress'
        ]);

        // We stay on step 4 after saving connection. User may click complete or navigate.
        $this->setActiveOverride(4);
    }
}