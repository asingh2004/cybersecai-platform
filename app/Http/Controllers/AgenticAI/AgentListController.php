<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AgenticAiLog;

class AgentListController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        // List of available agents. Add more as needed.
        $agents = [
    [
        'endpoint'   => 'compliance_advisor',
        'label'      => 'Compliance Advisor AI Agent',
        'description'=> 'AI-powered agent provides live, adaptive compliance assessments—far beyond pre-set workflows.',
        'route'      => 'agentic_ai.compliance',
    ],
    [
        'endpoint'   => 'audit',
        'label'      => 'Internal Auditor AI Agent',
        'description'=> 'Self-directed audit agent delivers dynamic, context-aware reports for your boardroom.',
        'route'      => 'agentic_ai.auditor',
    ],
    [
        'endpoint'   => 'policy_enforce',
        'label'      => 'Protector AI Agent (Coming up Soon - Jul 25!!!)',
        'description'=> 'Policy enforcement & Events Notification by agentic AI— based on data sensitivity, always context smart.',
        'route'      => 'agentic_ai.policy',
    ]
];

        // Attach user's own logs for each agent (most recent first)
        foreach ($agents as &$agent) {
            $agent['user_logs'] = AgenticAiLog::where('user_id', $userId)
                ->where('endpoint', $agent['endpoint'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return view('agentic_ai.agents', [
            'agents' => $agents,
        ]);
    }
}