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
    // Usual validation
    $request->validate([
        'standard_id' => 'required|exists:compliance_standards,id',
        'event_type' => 'required'
    ]);

    $standard = ComplianceStandard::findOrFail($request->input('standard_id'));
    $fields = is_string($standard->compliance_fields)
        ? json_decode($standard->compliance_fields, true)
        : $standard->compliance_fields;

    $data = [];
    foreach ($fields as $f) {
        $data[$f['name']] = $request->input('data.' . $f['name'], '');
    }

    $event = ComplianceEvent::create([
        'user_id'     => auth()->id(),
        'event_type'  => $request->input('event_type'),
        'standard_id' => $standard->id,
        'data'        => $data,
    ]);

    try {
        $response = \Http::timeout(600)->post(
            config('gdpr.agentic_ai_url') . '/compliance/agent_decide',
            [
                'standard'         => $standard->standard,
                'jurisdiction'     => $standard->jurisdiction,
                'requirement_notes'=> $standard->detailed_jurisdiction_notes,
                'event_type'       => $event->event_type,
                'data'             => $data,
            ]
        );

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service error: ' . $response->status(),
                'errors' => $response->json() ?? [],
                'event_id' => $event->id,
            ], 500);
        }
        $ai = $response->json();

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'AI service is unavailable. Please try again later.',
            'errors' => [$e->getMessage()],
            'event_id' => $event->id,
        ], 500);
    }

    $event->update([
        'risk'                => $ai['risk'] ?? null,
        'ai_decision_details' => $ai['decision_reason'] ?? null,
        'notification_letter' => $ai['notification_letter'] ?? null,
        'status'              => is_array($ai['action'] ?? null) ? implode(', ', $ai['action']) : ($ai['action'] ?? null)
    ]);

    return response()->json([
        'status' => 'success',
        'markdown' => $ai['markdown'] ?? '',
        'event_id' => $event->id,
        'ai_data'  => $ai,
        'message'  => 'Event logged and handled. See AI result below.'
    ]);
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