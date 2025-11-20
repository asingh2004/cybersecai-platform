<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DataConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\ChatCatalog;


class ChatOrchestratorController extends Controller
{
public function view()
{

  	$user_id = Auth::id(); // Get the current user's ID

	$user = \App\Models\User::find($user_id);

	if ($user && intval($user->business_id) !== 0 && $user->business_id != $user_id) {
    	$businessUser = \App\Models\User::where('id', $user->business_id)->first();
    	if($businessUser) {
        	$user_id = $businessUser->id; // or $businessUser->Id if your column is actually 'Id'
    	}
	}
  
    // REPLACE THIS WITH YOUR LOGIC to get user config_ids (e.g., [1], or from DB, or session, etc)
    //$configIds = \DB::table('data_configs')
    //->where('user_id', Auth::id())
    //->pluck('id')
    //->toArray();
	//if (empty($configIds)) $configIds = [1]; // fallback if user has no configs (optional)
  
  	$configIds = \DB::table('data_configs')
    	->where('user_id', $user_id)
    	->pluck('id')
    	->toArray();

	if (empty($configIds)) $configIds = [1];

   $useCases = [
    [
'persona'    => 'All',
'label'      => 'Summarizer: Complete Statistics',
'prompt'     => 'Show a complete statistics dashboard for my environment.',
'alt'        => 'All: One-click stats across key risk and inventory dimensions.',
'operation'  => 'summarizer_stats',
'args'       => (object)['days' => 7, 'data_source' => null, 'limit' => 100],
'user_id'    => $user_id
],
     
    
     
     [
    'persona' => 'Cybersecurity',
    'label'   => 'Cybersecurity Overview & Actions',
    'prompt'  => 'Give me a cybersecurity overview of all files and show next best actions.',
    'alt'     => 'Quick cybersecurity dashboard with breakdown and actionable follow-ups.',
    'operation' => 'cybersec',      // <--- This is the key!
    
    'user_id' => $user_id, 
],
     
     [
    	'persona' => 'All',
    	'label'   => 'All: Compliance Evidence & Audit',
    	'prompt'  => 'Create a full compliance, evidence, and audit report for all files with current risk, sharing, and location status as per latest compliance standards.',
    	'alt'     => 'Create a full compliance, evidence, and audit report for all files with current risk, sharing, and location status as per latest compliance standards.',
       	'operation' => 'm365_compliance_auto', // this triggers the agent
    	'args' => (object)[],
    	'config_ids' => $configIds,
    	// optionally, you could allow user/admin to provide/modify domains here:
    	'corporate_domains' => ["cybersecai.io"]
	],
          [
            'persona' => 'Cybersecurity',
            'label'   => 'Cybersecurity: What-if Policy Simulation',
            'prompt'  => 'Simulate the impact of deleting or encrypting all files in a given class and show what issues or violations may arise.',
            'alt'     => 'Cybersecurity: Scenario/impact analysis tool for SecOps and governance.'
        ],
      [
    'persona' => 'Cybersecurity',
    'label'   => 'Pentest: Web App Security Assessment',
    'prompt'  => 'Perform an autonomous penetration test ',
    'alt'     => 'Performs a world-class, standards-based external pentest and risk report for any public domain.',
    'operation' => 'pentest_auto',
    'args' => ['domain' => ''],
    // Add other keys if required by your UI conventions
],
     
    [
        'persona'   => 'Board Member',
        'label'     => 'Board Member: Executive Summary',
        'prompt'    => 'Summarize key compliance and cyber risk trends system-wide this month, with clear recommendations.',
        'alt'       => 'Board Member: Executive/board face-ready summary and advice.',
        'operation' => 'audit_board_summary',  // <-- matches new agent function
        'agent'     => 'audit',                // <-- always set for proper routing
        'user_id'   => $user_id,
    ],
    [
        'persona'   => 'Board Member',
        'label'     => 'Board Member: Board-Level Audit Report',
        'prompt'    => 'Provide a concise board-level summary and recommendations based on current file risk status and trends.',
        'alt'       => 'Board Member: Easy-to-read executive report for board/leadership.',
        'operation' => 'audit_full',           // <-- matches new agent function
        'agent'     => 'audit',
        'user_id'   => $user_id,
    ],
    [
        'persona'   => 'Risk Auditor',
        'label'     => 'Risk Auditor: Compliance Evidence Report',
        'prompt'    => 'Generate an audit-ready compliance evidence report for all detected privacy standard findings.',
        'alt'       => 'Risk Auditor: For audit/assurance, produces exportable reports from all findings.',
        'operation' => 'audit_full',           // <-- for risk auditor, full report
        'agent'     => 'audit',
        'user_id'   => $user_id,
    ],
    [
        'persona'   => 'Board Member',
        'label'     => 'Board Member: Audit Evidence Only',
        'prompt'    => 'Show detailed evidence tables of detected high-risk file exposures for review.',
        'alt'       => 'Board Member: Direct evidence tables for board review or compliance sampling.',
        'operation' => 'audit_evidence',       // <-- operation for direct evidence
        'agent'     => 'audit',
        'user_id'   => $user_id,
    ],
    [
        'persona'   => 'Board Member',
        'label'     => 'No More Questions',
        'prompt'    => 'Thank you, no further questions.',
        'alt'       => 'End board session',
        'operation' => 'audit_no_action',
        'agent'     => 'audit',
        'user_id'   => $user_id,
    ],

[
    'persona'   => 'Risk Auditor',
    'label'     => 'Risk Auditor: Compliance Advisory',
    'prompt'    => 'Provide compliance advisory including urgent actions for any new or high risk files.',
    'alt'       => 'Risk Auditor: Prioritized compliance/legal advice for privacy and legal teams.',
    'operation' => 'audit_compliance_advisory',
    'agent'     => 'audit',
    'user_id'   => $user_id,
],
[
    'persona'   => 'Risk Auditor',
    'label'     => 'Risk Auditor: Find Risk Hotspots',
    'prompt'    => 'Identify the files or folders with the highest current risk and recommend next actions.',
    'alt'       => 'Risk Auditor: Identifies riskiest items systemwide and suggests priorities.',
    'operation' => 'audit_find_risk_hotspots',
    'agent'     => 'audit',
    'user_id'   => $user_id,
],
[
    'persona'   => 'Risk Auditor',
    'label'     => 'Risk Auditor: Continuous Alerts & Monitoring',
    'prompt'    => 'List any newly detected high-risk or non-compliant files since the last system scan.',
    'alt'       => 'Risk Auditor: Continuous risk monitoring for ongoing assurance.',
    'operation' => 'audit_continuous_alerts',
    'agent'     => 'audit',
    'user_id'   => $user_id,
],
   
     
     
   
        [
            'persona' => 'Board Member',
            'label'   => 'Board Member: Incident Simulation',
            'prompt'  => 'If a major data breach happened, what would be the predicted business impact and who would be most affected?',
            'alt'     => 'Board Member: Business and risk estimation simulation.'
        ],
        
        [
            'persona' => 'Auditor/Security',
            'label'   => 'Auditor/Security: Automated Forensics Trail',
            'prompt'  => 'Show a full forensic audit trail for all high-risk files changed recently, with supporting evidence logs.',
            'alt'     => 'Auditor/Security: Evidence-oriented, in-depth log/audit demonstrations.'
        ],
        [
            'persona' => 'Auditor/Security',
            'label'   => 'Auditor/Security: Zero-Knowledge Risk Mapping',
            'prompt'  => 'Generate a universal map of files with regulated data exposure, regardless of storage location, for zero-knowledge auditing.',
            'alt'     => 'Auditor/Security: For full visibility data audits, even with minimal access.'
        ]
    ];

    $personas = [
        'Risk Auditor' => 'Compliance/audit focus',
        'Cybersecurity' => 'Security operations',
        'Board Member' => 'Executive summary'
    ];
    return view('agentic_ai.chatorchestrator', compact('useCases', 'personas'));
}

public function downloadDocx(Request $request)
{
    $file = $request->query('file');
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv/';
    $fullPath = $basePath . $file;

    // Security check: no path traversal and only allow .docx files
    if (
        strpos($file, '..') !== false ||
        !preg_match('/^[a-zA-Z0-9_\-\.]+\.docx$/', $file)
    ) {
        abort(403, 'Invalid file request');
    }
    if (!file_exists($fullPath)) {
        abort(404, 'File not found');
    }
    // Laravel will set the correct headers for Word docs
    return response()->download($fullPath, $file, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ]);
}
  
public function downloadCsv(Request $request)
{
    $file = $request->query('file');
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv/';
    $fullPath = $basePath . $file;

    // Security check: no path traversal allowed
    if (strpos($file, '..') !== false || !preg_match('/^[a-zA-Z0-9_\-\.]+\.csv$/', $file)) {
        abort(403, 'Invalid file request');
    }
    if (!file_exists($fullPath)) {
        abort(404, 'File not found');
    }
    return response()->download($fullPath);
}  
  
public function orchestrate(Request $request)
{
    $user = auth()->user();
    if (!$user) return response()->json(['error' => 'Not authenticated'], 403);

    // ==== NEW LOGIC FOR USER_ID AND CONFIG_IDS ==== //
    $user_id = Auth::id(); // Get the current user's ID

    $currentUser = \App\Models\User::find($user_id);

    if ($currentUser && intval($currentUser->business_id) !== 0 && $currentUser->business_id != $user_id) {
        $businessUser = \App\Models\User::where('id', $currentUser->business_id)->first();
        if ($businessUser) {
            $user_id = $businessUser->id;
        }
    }

    $configIds = \DB::table('data_configs')
        ->where('user_id', $user_id)
        ->pluck('id')
        ->toArray();
    if (empty($configIds)) $configIds = [1]; // fallback

    // ==== REST OF THE ORIGINAL LOGIC ==== //

    $query = $request->input('query');
    $prior_context = $request->input('prior_context', []);
    $session_id = $request->input('session_id');
    $messages = $request->input('messages', []);

    // ENSURE $messages is always an array
    if (!is_array($messages)) {
        try { $messages = json_decode($messages, true); } catch (\Throwable $e) {}
        if (!is_array($messages)) $messages = [];
    }

    $pending_field = isset($prior_context['pending_field']) ? $prior_context['pending_field'] : null;
    if ($pending_field) unset($prior_context['pending_field']);

    // Always get the user for dataConfig by $user_id (not $user->id alone)
    $dataConfig = \App\Models\DataConfig::where('user_id', $user_id)->first();
    $regulations = $dataConfig ? $dataConfig->regulations : [];
    Log::info("DEBUG-raw-regulations", ['value' => $regulations]);

    // FIX double-encoded JSON
    if (is_string($regulations)) {
        $decoded = json_decode($regulations, true);
        Log::info("DEBUG-decoded-once", ['value' => $decoded]);

        // If first decode is still a string (common with double encoding), decode again
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
            Log::info("DEBUG-decoded-twice", ['value' => $decoded]);
        }
        $regulations = is_array($decoded) ? $decoded : [];
        Log::info("DEBUG-final-regulations", ['value' => $regulations]);
    }

    // Find best matching regulation block by scanning for law/region in query. Fallback: first.
    $pick_regulation = function ($regulations, $query) {
        if (!is_array($regulations) || count($regulations) === 0) return [];
        $query_lc = strtolower($query);
        foreach ($regulations as $reg) {
            if (
                (isset($reg['standard']) && stripos($query_lc, strtolower($reg['standard'])) !== false) ||
                (isset($reg['jurisdiction']) && stripos($query_lc, strtolower($reg['jurisdiction'])) !== false) ||
                (isset($reg['standard']) && preg_match('/(gdpr|ccpa|australian privacy|privacy act|california|australia|usa|eu)/i', $query_lc) && stripos($query_lc, strtolower($reg['standard'])) !== false) ||
                (isset($reg['jurisdiction']) && preg_match('/(australia|california|usa|eu|global)/i', $query_lc) && stripos($query_lc, strtolower($reg['jurisdiction'])) !== false)
            ) {
                return $reg;
            }
        }
        return $regulations[0]; // fallback
    };

    $fill_field = function ($name, $regulations, $dataConfig, $user, $request, $messages, $query) use ($pick_regulation, $configIds) {
        $chosen = $pick_regulation($regulations, $query);
        switch ($name) {
            case 'config_ids':
                // Use updated $configIds for filling
                return $configIds;
            case 'standard':
                return isset($chosen['standard']) ? $chosen['standard'] : null;
            case 'jurisdiction':
            case 'region':
                return isset($chosen['jurisdiction']) ? $chosen['jurisdiction'] : null;
            case 'messages':
                return is_array($messages) ? $messages : [];
        }
        return null;
    };

    // Slot-fill if pending field present
    if ($pending_field) {
        $filled = $fill_field($pending_field, $regulations, $dataConfig, $user, $request, $messages, $query);
        if ($filled !== null) {
            $prior_context[$pending_field] = $filled;
        }
    }

    // Always include config_ids
    if (!isset($prior_context['config_ids'])) {
        $prior_context['config_ids'] = $configIds;
    }
    // Always include messages as array
    if (!isset($prior_context['messages']) || !is_array($prior_context['messages'])) {
        $prior_context['messages'] = $messages;
    }

    // PROACTIVE compliance slotfilling based on query content, not just pending_field!
    $lower_query = strtolower($query);
    if (
        (strpos($lower_query, 'compliance') !== false || strpos($lower_query, 'privacy') !== false ||
        strpos($lower_query, 'data breach') !== false || strpos($lower_query, 'australia') !== false) ||
        (isset($prior_context['agent']) && $prior_context['agent'] === 'compliance')
    ) {
        if (empty($prior_context['standard'])) {
            $prior_context['standard'] = $fill_field('standard', $regulations, $dataConfig, $user, $request, $messages, $query);
        }
        if (empty($prior_context['jurisdiction'])) {
            $prior_context['jurisdiction'] = $fill_field('jurisdiction', $regulations, $dataConfig, $user, $request, $messages, $query);
        }
    }

    if (!isset($prior_context['corporate_domains']) || !is_array($prior_context['corporate_domains'])) {
        $prior_context['corporate_domains'] = [
            "ozzieaccomptyltd.onmicrosoft.com",
            "mysubsidiary.com",
            "myuni.edu.au"
        ];
    }

    // Ensure config_ids present, fallback if not.
    if (!isset($prior_context['config_ids']) || !is_array($prior_context['config_ids']) || count($prior_context['config_ids']) === 0) {
        $prior_context['config_ids'] = $configIds; // Comes from above (database query)
    }

    $endpoint = "http://127.0.0.1:8224/agentic/auto_orchestrate";
    try {
        $resp = \Illuminate\Support\Facades\Http::timeout(1200)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, [
                'user_query' => $query,
                'prior_context' => $prior_context,
                'session_id' => $session_id,
                'user_id' => $user_id
            ]);
        $status = $resp->status();
        if (!$resp->ok()) {
            \Log::error("[Orchestrator] HTTP $status : " . $resp->body());
            return response()->json([
                'ai' => [
                    'role' => 'ai',
                    'content' => "Agentic orchestrator error: {$resp->body()}"
                ]
            ]);
        }
        $result = $resp->json();

        if (isset($result['pending']) && $result['pending']) {
            $field = null;
            if (preg_match('/I need the following: ([^,]+)[\.,]?/', $result['question'], $m)) {
                $field = trim($m[1]);
            }
            if ($field) $prior_context['pending_field'] = $field;
            return response()->json([
                'ai' => [
                    'role' => 'ai',
                    'content' => $result['question'] ?? 'I need more information to process your request...',
                    'pending' => true,
                    'session_id' => $result['session_id'],
                    'prior_context' => $prior_context
                ]
            ]);
        } else {
            $answer = '';
            if (isset($result['result']['reply'])) $answer = $result['result']['reply'];
            else if (isset($result['result']['markdown'])) $answer = $result['result']['markdown'];
            else $answer = is_string($result['result']) ? $result['result'] : json_encode($result['result']);
            return response()->json([
                'ai' => [
                    'role' => 'ai',
                    'content' => $answer,
                    'pending' => false,
                    'session_id' => $result['session_id'] ?? null,
                    'prior_context' => [],
                    'followups' => $result['followups'] ?? []
                ]
            ]);
        }
    } catch (\Throwable $e) {
        \Log::error("Agentic orchestrator exception: " . $e->getMessage());
        return response()->json([
            'ai' => [
                'role' => 'ai',
                'content' => "Service unavailable. " . $e->getMessage()
            ]
        ]);
    }
}
}