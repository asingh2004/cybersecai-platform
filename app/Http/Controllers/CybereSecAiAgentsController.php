<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataSourceRef;
use App\Models\SensitiveDataPolicy;
use Illuminate\Support\Facades\Auth;

/**
 * Ultra-modern, streaming, chat-style AI agent controller.
 */
class CybereSecAiAgentsController extends Controller
{
    /** Utility to push a message to the chat history session. */
    public static function pushMessage($role, $content, $meta = [])
    {
        $messages = session('chat_messages', []);
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'time' => now()->format('H:i'),
            'meta' => $meta,
        ];
        session(['chat_messages' => $messages]);
    }

    /** Show animated, clickable SVG welcome/process map. */
    public function welcome()
    {
        // On load, reset chat!
        session()->forget('chat_messages');
        self::pushMessage('agent', "👋 Welcome! I'm your <b>CyberSecAI.io Sensitive Data Agent</b>.<br>Click a step to begin or just hit <b>Get started</b>.");
        return view('cybersecaiagents.wizard.welcome');
    }

    /** Step 1: Data source (shows as next 'agent' message). */
    public function step1()
    {

      
      	$sources = DataSourceRef::select('id', 'data_source_name', 'description')->get();
        self::pushMessage('agent', "Let's connect your first <b>data store</b>. What type is it?");
        return view('cybersecaiagents.wizard.step1', compact('sources'));
    }

    public function step1Post(Request $request)
    {
        $d = $request->validate(['data_source_id'=>'required|integer']);
        $ds = DataSourceRef::findOrFail($d['data_source_id']);
        session([
            'wizard' => [
                'data_source_id' => $ds->id,
                'data_source_name' => $ds->data_source_name
            ]
        ]);
        self::pushMessage('user', "Data source selected: <b>{$ds->data_source_name}</b>");
        return redirect()->route('cybersecaiagents.policyForm');
    }

    /** Step 2: Policy upload/link. */
    public function policyForm()
    {
        self::pushMessage('agent', "Upload your <b>policy document</b> or share a link. I will analyze its sensitive data requirements and compliance mandates automatically.");
        return view('cybersecaiagents.wizard.policy');
    }

    public function policySubmit(Request $request)
    {
        $data = $request->validate([
            'policy_name' => 'required|string|max:255',
            'policy_url'  => 'nullable|url|max:2000',
            'policy_file' => 'nullable|file|max:20480',
        ]);
        $user = Auth::user();
        $path = $request->hasFile('policy_file') ? $request->file('policy_file')->store('policies', 'private') : null;
        SensitiveDataPolicy::create([
            'user_id'      => $user->id,
            'policy_name'  => $data['policy_name'],
            'policy_url'   => $data['policy_url'] ?? null,
            'policy_file'  => $path,
        ]);
        session(['wizard.policy_url' => $data['policy_url'] ?? null]);
        self::pushMessage('user', ($data['policy_url'] ? "Policy link: <a href='{$data['policy_url']}'>{$data['policy_url']}</a>" : "Policy file uploaded."));
        return redirect()->route('cybersecaiagents.agentStep');
    }

    // Main agent step interface (chat interface w/ AJAX for streaming)
    public function agentStep(Request $request)
    {
      	        $wizard = session('wizard', []);
    	if (empty($wizard['data_source_id'])) {
        // User skipped a step; redirect or show error
        return redirect()->route('cybersecaiagents.step1')
            ->with('error', 'Please add a data source before continuing.');
    	}
      
        $step = $request->input('step') ?? 'policy';
        $wizard = session('wizard', []);
        $messages = session('chat_messages', []);
        // After posting user message, push it to chat
        if ($request->has('chat_input')) {
            $msg = $request->input('chat_input');
            self::pushMessage('user', e($msg));
        }

        // If this is AJAX for streaming, just call Python and output JSON
        if($request->ajax()) {
            $resp = $this->callPythonAgent($step, $wizard);
            // Add streamed part to chat history and output as JSON
            self::pushMessage('agent', $resp['agent_reply']);
            return response()->json([
                'reply' => $resp['agent_reply'],
                'messages' => session('chat_messages', [])
            ]);
        }

        // Default: render chat interface
        return view('cybersecaiagents.wizard.agentstep', [
            'messages' => session('chat_messages', []),
            'step' => $step,
            'wizard' => $wizard,
            'typing' => false
        ]);
    }

    // AJAX agent interface: POST /cybersecaiagents/agentchat (returns next agent message)
    public function agentChat(Request $request)
    {
        $wizard = session('wizard', []);
        $step = $request->input('step');
        $msg = $request->input('chat_input');
        self::pushMessage('user', e($msg));
        $resp = $this->callPythonAgent($step, $wizard + ['latest_input' => $msg]);
        self::pushMessage('agent', $resp['agent_reply']);
        return response()->json([
            'reply' => $resp['agent_reply'],
            'messages' => session('chat_messages', [])
        ]);
    }

    // Call agent orchestrator (Python API) and return structured reply.
    public function callPythonAgent($step, $state)
    {
        // Make Python call to orchestrator, pass wizard step & state
        $resp = \Http::post("http://localhost:5005/agent/orchestrate", [
            "user_id" => Auth::id(),
            "step"    => $step,
            "state"   => $state,
        ])->json();

        return [
            'agent_reply' => $resp['next_prompt'] ?? ($resp['reply'] ?? '[Error: No agent reply]')
            // You could pass more e.g. risk, graphs, etc.
        ];
    }

    // (Optional) New step for visuals/risk dashboard
    public function visualsDashboard() // classified visual
    {
        $visual = session('wizard.visual', []);
        return view('cybersecaiagents.wizard.visuals-dashboard', compact('visual'));
    }
}