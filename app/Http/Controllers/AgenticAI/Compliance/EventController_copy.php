<?php

namespace App\Http\Controllers\AgenticAI\Compliance;

use App\Http\Controllers\Controller;
use App\Models\ComplianceEvent;
use App\Models\ComplianceStandard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EventController extends Controller
{
    public function index()
    {
        $events = ComplianceEvent::with('standard')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);
        //return view('gdpr.events.index', compact('events'));
      
      	return view('databreach.events.index', compact('events'));

    }

    public function create()
    {
        $standards = ComplianceStandard::orderBy('jurisdiction')->get();
        //return view('gdpr.events.create', compact('standards'));
      
      	return view('databreach.events.create', compact('standards'));
    }

    public function store(Request $request)
    {
        \Log::info("[GDPR Event] Store: received request", [
            'ip' => $request->ip(), 
            'user_id' => auth()->id(),
            'input' => $request->all()
        ]);

        $request->validate([
            'standard_id' => 'required|exists:compliance_standards,id',
            'event_type' => 'required'
        ]);

        $standard = ComplianceStandard::findOrFail($request->input('standard_id'));
        \Log::info("[GDPR Event] Found standard", ['standard' => $standard->toArray()]);

        $fields = is_string($standard->compliance_fields)
            ? json_decode($standard->compliance_fields, true)
            : $standard->compliance_fields;

        $data = [];
        foreach ($fields as $f) {
            $data[$f['name']] = $request->input('data.' . $f['name'], '');
        }
        \Log::info("[GDPR Event] Event data parsed from form", ['data' => $data]);

        $event = ComplianceEvent::create([
            'user_id'     => auth()->id(),
            'event_type'  => $request->input('event_type'),
            'standard_id' => $standard->id,
            'data'        => $data,
        ]);
        \Log::info("[GDPR Event] Created event DB record", ['event_id' => $event->id]);

        try {
            \Log::info("[GDPR Event] Calling Agentic AI endpoint...", [
                'url' => config('gdpr.agentic_ai_url') . '/compliance/agent_decide',
                'payload' => [
                    'standard'         => $standard->standard,
                    'jurisdiction'     => $standard->jurisdiction,
                    'requirement_notes'=> $standard->detailed_jurisdiction_notes,
                    'event_type'       => $event->event_type,
                    'data'             => $data,
                ]
            ]);
            $response = Http::timeout(300)->post(config('gdpr.agentic_ai_url') . '/compliance/agent_decide', [
                'standard'         => $standard->standard,
                'jurisdiction'     => $standard->jurisdiction,
                'requirement_notes'=> $standard->detailed_jurisdiction_notes,
                'event_type'       => $event->event_type,
                'data'             => $data,
            ]);
            \Log::info("[GDPR Event] Agentic AI raw response", ['body' => $response->body(), 'status' => $response->status()]);
            if ($response->failed()) {
                \Log::error("[GDPR Event] AI endpoint failed", ['code' => $response->status(), 'body' => $response->body()]);
                return redirect()->back()->withInput()->with('error', 'AI service error: ' . $response->status());
            }
            $ai = $response->json();
        } catch (\Throwable $e) {
            \Log::error("[GDPR Event] AI call threw exception", ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'AI service is unavailable. Please try again later.');
        }

        $event->update([
            'risk'                => $ai['risk'] ?? null,
            'ai_decision_details' => $ai['decision_reason'] ?? null,
            'notification_letter' => $ai['notification_letter'] ?? null,
            'status'              => is_array($ai['action'] ?? null) ? implode(', ', $ai['action']) : ($ai['action'] ?? null)
        ]);
        
        \Log::info("[GDPR Event] Updated event with AI results", [
            'event_id' => $event->id,
            'ai_response' => $ai
        ]);

        //return redirect()->route('gdpr.events.index')->with('success', 'Event logged and handled.');
      
      	return redirect()->route('databreach.events.index')->with('success', 'Event logged and handled.');
    }

    public function show($id)
    {
        $event = ComplianceEvent::with('standard')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        //return view('gdpr.events.show', compact('event'));
      
      	return view('databreach.events.show', compact('event'));
    }
}