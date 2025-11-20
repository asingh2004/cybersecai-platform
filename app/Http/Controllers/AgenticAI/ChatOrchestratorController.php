<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use App\Models\DataConfig;
use App\Models\User;
use App\Services\ChatCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatOrchestratorController extends Controller
{
    /**
     * Render the chat orchestrator view with personas and use-cases
     * provided by the shared ChatCatalog service.
     */
    public function view(ChatCatalog $catalog)
    {
        $user = Auth::user();

        $useCases = $catalog->useCases($user);
        $personas = $catalog->personas();

        return view('agentic_ai.chatorchestrator', compact('useCases', 'personas'));
    }

    /**
     * Securely download a generated DOCX report.
     */
    public function downloadDocx(Request $request)
    {
        $file = (string) $request->query('file', '');
        if (!$file || !preg_match('/^[A-Za-z0-9._-]+\.docx$/', $file)) {
            abort(403, 'Invalid file request');
        }

        // Prefer config; fallback to your current absolute path
        $basePath = rtrim(config('paths.export_dir', '/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv/'), '/');
        $baseReal = realpath($basePath);
        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $file);

        if ($baseReal === false || $fullPath === false || strpos($fullPath, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            abort(403, 'Invalid file path');
        }
        if (!is_file($fullPath) || !file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        return response()->download(
            $fullPath,
            $file,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    /**
     * Securely download a generated CSV file.
     */
    public function downloadCsv(Request $request)
    {
        $file = (string) $request->query('file', '');
        if (!$file || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $file)) {
            abort(403, 'Invalid file request');
        }

        // Prefer config; fallback to your current absolute path
        $basePath = rtrim(config('paths.export_dir', '/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv/'), '/');
        $baseReal = realpath($basePath);
        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $file);

        if ($baseReal === false || $fullPath === false || strpos($fullPath, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            abort(403, 'Invalid file path');
        }
        if (!is_file($fullPath) || !file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        return response()->download($fullPath, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Main chat orchestrate endpoint.
     * Unifies owner resolution and config_ids via ChatCatalog, keeps your
     * slot-filling and regulation logic, and calls the agentic backend.
     */
    public function orchestrate(Request $request, ChatCatalog $catalog)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 403);
        }

        // Resolve owner account + config IDs from shared service
        $ownerId   = $catalog->resolveOwnerUserId($user);
        $configIds = $catalog->configIdsForUserId($ownerId);

        $query         = (string) $request->input('query', '');
        $prior_context = $request->input('prior_context', []);
        $session_id    = $request->input('session_id');
        $messages      = $request->input('messages', []);

        if (trim($query) === '') {
            return response()->json(['error' => 'Query is required'], 422);
        }

        // Normalize messages to array
        if (!is_array($messages)) {
            try { $messages = json_decode((string) $messages, true) ?: []; } catch (\Throwable $e) { $messages = []; }
        }

        // Pull out pending_field if present
        $pending_field = $prior_context['pending_field'] ?? null;
        if (isset($prior_context['pending_field'])) {
            unset($prior_context['pending_field']);
        }

        // Fetch DataConfig for regulations for the owner
        $dataConfig  = DataConfig::where('user_id', $ownerId)->first();
        $regulations = $this->parseRegulations($dataConfig ? $dataConfig->regulations : []);

        // Helper: pick regulation by query text
        $pick_regulation = function (array $regs, string $q): array {
            if (count($regs) === 0) return [];
            $q = strtolower($q);
            foreach ($regs as $reg) {
                $std = isset($reg['standard']) ? strtolower($reg['standard']) : null;
                $jur = isset($reg['jurisdiction']) ? strtolower($reg['jurisdiction']) : null;

                if (($std && stripos($q, $std) !== false) ||
                    ($jur && stripos($q, $jur) !== false) ||
                    ($std && preg_match('/(gdpr|ccpa|australian privacy|privacy act|california|australia|usa|eu)/i', $q) && stripos($q, $std) !== false) ||
                    ($jur && preg_match('/(australia|california|usa|eu|global)/i', $q) && stripos($q, $jur) !== false)) {
                    return $reg;
                }
            }
            // Fallback to first
            return $regs[0] ?? [];
        };

        // Fill a specific field from regulations/context
        $fill_field = function (string $name) use ($pick_regulation, $regulations, $messages, $query, $configIds) {
            $chosen = $pick_regulation($regulations, $query);
            switch ($name) {
                case 'config_ids':
                    return $configIds;
                case 'standard':
                    return $chosen['standard'] ?? null;
                case 'jurisdiction':
                case 'region':
                    return $chosen['jurisdiction'] ?? null;
                case 'messages':
                    return is_array($messages) ? $messages : [];
            }
            return null;
        };

        // If the agent asked for a specific field previously, fill it now
        if ($pending_field) {
            $filled = $fill_field($pending_field);
            if ($filled !== null) {
                $prior_context[$pending_field] = $filled;
            }
        }

        // Always ensure config_ids and messages
        if (!isset($prior_context['config_ids']) || !is_array($prior_context['config_ids']) || count($prior_context['config_ids']) === 0) {
            $prior_context['config_ids'] = $configIds;
        }
        if (!isset($prior_context['messages']) || !is_array($prior_context['messages'])) {
            $prior_context['messages'] = $messages;
        }

        // Proactive compliance slot filling based on query content
        $lower_query = strtolower($query);
        $looks_compliance = (strpos($lower_query, 'compliance') !== false) ||
                            (strpos($lower_query, 'privacy') !== false) ||
                            (strpos($lower_query, 'data breach') !== false) ||
                            (strpos($lower_query, 'australia') !== false) ||
                            ((isset($prior_context['agent']) && $prior_context['agent'] === 'compliance'));

        if ($looks_compliance) {
            if (empty($prior_context['standard'])) {
                $prior_context['standard'] = $fill_field('standard');
            }
            if (empty($prior_context['jurisdiction'])) {
                $prior_context['jurisdiction'] = $fill_field('jurisdiction');
            }
        }

        // Default domains if not provided
        if (!isset($prior_context['corporate_domains']) || !is_array($prior_context['corporate_domains'])) {
            $prior_context['corporate_domains'] = [
                "ozzieaccomptyltd.onmicrosoft.com",
                "mysubsidiary.com",
                "myuni.edu.au"
            ];
        }

        // Agentic backend endpoint (use config with fallback)
        $endpoint = config('services.agentic.orchestrator_url', 'http://127.0.0.1:8224/agentic/auto_orchestrate');

        try {
            $resp = Http::timeout(1200)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'user_query'   => $query,
                    'prior_context'=> $prior_context,
                    'session_id'   => $session_id,
                    'user_id'      => $ownerId,
                ]);

            if (!$resp->ok()) {
                $status = $resp->status();
                Log::error("[Orchestrator] HTTP $status : " . $resp->body());
                return response()->json([
                    'ai' => [
                        'role'    => 'ai',
                        'content' => "Agentic orchestrator error: {$resp->body()}",
                    ]
                ]);
            }

            $result = $resp->json();

            // If backend requests additional info
            if (!empty($result['pending'])) {
                $field = null;
                if (isset($result['question']) && preg_match('/I need the following: ([^,]+)[\.,]?/i', (string) $result['question'], $m)) {
                    $field = trim($m[1]);
                }
                if ($field) {
                    $prior_context['pending_field'] = $field;
                }

                return response()->json([
                    'ai' => [
                        'role'         => 'ai',
                        'content'      => $result['question'] ?? 'I need more information to process your request...',
                        'pending'      => true,
                        'session_id'   => $result['session_id'] ?? null,
                        'prior_context'=> $prior_context,
                    ]
                ]);
            }

            // Normal successful response
            $answer = '';
            if (isset($result['result']['reply'])) {
                $answer = $result['result']['reply'];
            } elseif (isset($result['result']['markdown'])) {
                $answer = $result['result']['markdown'];
            } else {
                $raw = $result['result'] ?? '';
                $answer = is_string($raw) ? $raw : json_encode($raw);
            }

            return response()->json([
                'ai' => [
                    'role'         => 'ai',
                    'content'      => $answer,
                    'pending'      => false,
                    'session_id'   => $result['session_id'] ?? null,
                    'prior_context'=> [],
                    'followups'    => $result['followups'] ?? [],
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error("Agentic orchestrator exception: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'ai' => [
                    'role'    => 'ai',
                    'content' => "Service unavailable. " . $e->getMessage(),
                ]
            ]);
        }
    }

    /**
     * Robustly parse regulations that may be arrays, JSON strings,
     * or double-encoded JSON strings.
     */
    private function parseRegulations($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];

        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        return is_array($decoded) ? $decoded : [];
    }
}