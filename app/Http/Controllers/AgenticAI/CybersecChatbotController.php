<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use App\Models\DataConfig;
use Illuminate\Support\Str;

/* Pseudo Code
For each of these config IDs, and for each storage type (M365, SMB, S3, NFS):
It looks in:
/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365/22/graph/
/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB/22/graph/
/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3/22/graph/
/home/cybersecai/htdocs/www.cybersecai.io/webhook/NFS/22/graph/
and similarly for 45, etc.
It collects all .json files found in each /graph/ folder for those paths, and loads them for use as 'findings'.
*/

class CybersecChatbotController extends Controller
{
    // Show chat interface with guardrail personas
    public function index()
    {
        $user = auth()->user();
        if (!$user) return redirect('/login');
        $personas = [
            'Risk Auditor' => 'Ask me anything about compliance risk and regulated data across your files.',
            'Cybersecurity' => 'Ask about threats, exposures, access, and automations.',
            'Board Member' => 'Query for executive-level, business impact, and trends.',
        ];

    $useCases = [
                                    [
                                        'persona' => 'Risk Auditor',
                                        'label' => 'Compliance Evidence Report',
                                        'prompt' => 'Generate an audit-ready compliance evidence report for all detected GDPR, PCI DSS, and privacy standard findings across my files.',
                                        'alt' => 'Instantly produce audit-ready compliance reports using AI findings.'
                                    ],
                                    [
                                        'persona' => 'Risk Auditor',
                                        'label' => 'Find Risk Hotspots',
                                        'prompt' => 'Identify files or folders at highest compliance or privacy risk, and note who owns them. Recommend next actions.',
                                        'alt' => 'Surface your high-risk files/folders and recommended actions.'
                                    ],
                                    [
                                        'persona' => 'Risk Auditor',
                                        'label' => 'Continuous Alerts & Monitoring',
                                        'prompt' => 'List any newly detected high-risk or non-compliant files from the last scan, and provide a timestamped summary for ongoing audit evidence.',
                                        'alt' => 'See new risks since last review.'
                                    ],
                                    [
                                        'persona' => 'Cybersecurity',
                                        'label' => 'Sensitive Data Exposure Mapping',
                                        'prompt' => 'Show me files containing sensitive data (like PII or credentials) with excessive or "Everyone" access. Recommend remediations.',
                                        'alt' => 'Map high-risk exposures and suggest remediations.'
                                    ],
                                    [
                                        'persona' => 'Cybersecurity',
                                        'label' => 'Auto-Classification/Enforce',
                                        'prompt' => 'Check for unclassified or weakly protected financial/PII records and simulate auto-classification or triggering auto-encryption/quarantine.',
                                        'alt' => 'Track or auto-classify risky or unclassified data.'
                                    ],
                                    [
                                        'persona' => 'Cybersecurity',
                                        'label' => 'What-if Policy Simulation',
                                        'prompt' => 'Simulate the organizational impact of globally deleting or encrypting a class of files. What risks or rule violations would surface instantly?',
                                        'alt' => 'Model impact of security/policy changes instantly.'
                                    ],
                                    [
                                        'persona' => 'Board Member',
                                        'label' => 'Executive Summary',
                                        'prompt' => 'Provide an executive summary of compliance and cybersecurity trends this month. Quantify exposure and recommend board-level actions.',
                                        'alt' => 'Receive plain-English, executive briefings for the board.'
                                    ],
                                    [
                                        'persona' => 'Board Member',
                                        'label' => 'Incident Simulation',
                                        'prompt' => 'If file/document breach occurred in our cloud storage, what is the predicted business impact (regulatory, financial, reputational) and who is affected? Include penalty that typically applies for data breach.',
                                        'alt' => 'Simulate real breach consequences for decision-makers.'
                                    ],
                                    [
                                        'persona' => 'All',
                                        'label' => 'Regulatory Change Impact',
                                        'prompt' => 'Highlight files or processes impacted by recent changes to GDPR, Cyber Security Act, or CCPA. Where do we need to update compliance or retention policies?',
                                        'alt' => 'Adapt instantly to new data laws and compliance regs.'
                                    ],
                                    [
                                        'persona' => 'All',
                                        'label' => 'Proactive Automated Remediation',
                                        'prompt' => 'Describe how you (the AI agents) would trigger or automate encryption, quarantine, or other controls for newly detected non-compliant files.',
                                        'alt' => 'Show what the platform would automate in a crisis.'
                                    ],
                                    [
                                        'persona' => 'Auditor/Security',
                                        'label' => 'Automated Forensics Trail',
                                        'prompt' => 'Show the forensic audit trail for high-risk files changed in the last 14 days. Include evidence logs for each change.',
                                        'alt' => 'Fast, deep audit forensics mapped in real time.'
                                    ],
                                    [
                                        'persona' => 'Auditor/Security',
                                        'label' => 'Zero-Knowledge Risk Mapping',
                                        'prompt' => 'Give me a universal map of all files with PII/PCI exposure, spanning all clouds and storage types, for zero-knowledge auditing.',
                                        'alt' => 'Get a map of all regulated data risks at once, all systems.'
                                    ]
                                ];

    return view('agentic_ai.cybersecchatbot', [
        'personas' => $personas,
        'useCases' => $useCases,
    ]);
      
    }

  
  	
  
  public function chat(Request $request)
{
    $user = auth()->user();
    if (!$user) return response()->json(['error' => 'Not authenticated'], 403);

    $persona = $request->input('persona', 'Risk Auditor');
    $query = $request->input('query');
    $messages = $request->input('messages', []);
    $configIDs = DataConfig::where('user_id', $user->id)->pluck('id')->toArray();

    $payload = [
        'persona' => $persona,
        'query' => $query,
        'messages' => $messages,
        'config_ids' => $configIDs,
        // (no findings here! Python will gather)
    ];

    $apiURL = env('AGENTIC_URL', 'http://127.0.0.1:8222') . '/agentic/chatbot';

    try {
        $resp = Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($apiURL, $payload);

        if (!$resp->ok()) {
            Log::error("[CybersecChatbot] AI server error", [
                "status" => $resp->status(),
                "body" => $resp->body(),
                "url" => $apiURL
            ]);
            $reply = "Could not reach AI server. HTTP Status={$resp->status()}.";
        } else {
            $reply = $resp->json()['reply'] ?? 'No reply from AI.';
        }
    } catch (\Throwable $e) {
        Log::error("[CybersecChatbot] Exception contacting AI server", ['err' => $e->getMessage()]);
        $reply = "API/AI service error: " . $e->getMessage();
    }

    return response()->json([
        'reply' => $reply,
        'reply_html' => \Illuminate\Support\Str::markdown($reply),
    ]);
}
  
   
}