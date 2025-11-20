<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PolicyEnforcerController extends Controller
{
    public function index()
    {
        return view('agentic_ai.policy');
    }

    public function run(Request $request)
    {
        // ==== Example of detecting changes: You must implement your own detection logic ====
        // Each key is a filename, value is info required by PolicyEnforceReq in python
        $files = [
            'sensitive1.json' => [
                'changed' => true,
                'risk' => 'HIGH',
                'delta' => ['added_field' => 'some_value', 'removed_field' => null],
                'policy_required' => true
            ],
            'sensitive2.json' => [
                'changed' => false,
                'risk' => 'LOW'
            ]
        ];

        $policies = [
            'standard' => 'GDPR',
            'jurisdiction' => 'Europe',
            'notes' => 'Handle all high-risk deltas with strict notification.',
            'enforce_type' => 'Lock'
        ];
        $siem_url = config('agentic_ai.siem_url', '');

        $apiURL = env('AGENTIC_URL', 'http://localhost:8000') . '/agentic/policy_enforce';
        $response = Http::post($apiURL, [
            'files' => $files,
            'policies' => $policies,
            'siem_url' => $siem_url
        ]);

        $result = $response->json();
        return view('agentic_ai.policy', [
            'changes'      => $result['changed_files'] ?? [],
            'policyResults'=> $result['policy_actions'] ?? [],
            'siemEvents'   => $result['siem_events'] ?? []
        ]);
    }
}