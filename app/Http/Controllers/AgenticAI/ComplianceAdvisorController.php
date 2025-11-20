<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use League\CommonMark\CommonMarkConverter;
use App\Models\ComplianceStandard;
use App\Models\AgenticAiLog;

class ComplianceAdvisorController extends Controller
{
    public function index()
    {
        $standards = ComplianceStandard::query()->distinct()->pluck('standard');
        $jurisdictions = ComplianceStandard::query()->distinct()->pluck('jurisdiction');
        $data_sources = $this->getAllMonitoredDataSources();

        $pairs = ComplianceStandard::select('standard', 'jurisdiction')->get();
        $standardJurisdictionMap = [];
        $jurisdictionStandardMap = [];
        foreach ($pairs as $record) {
            $standardJurisdictionMap[$record->standard][] = $record->jurisdiction;
            $jurisdictionStandardMap[$record->jurisdiction][] = $record->standard;
        }

        return view('agentic_ai.compliance', [
            'standards'                  => $standards,
            'jurisdictions'              => $jurisdictions,
            'data_sources'               => $data_sources,
            'standardJurisdictionMap'    => $standardJurisdictionMap,
            'jurisdictionStandardMap'    => $jurisdictionStandardMap,
            'disable_button'             => false,
        ]);
    }

    public function run(Request $request)
    {
        $userId = auth()->id();
        // Business ID, update to match your actual method
        $businessId = optional(auth()->user())->business_id ?? null;
        $input = $request->validate([
            'standard'      => 'required|string',
            'jurisdiction'  => 'required|string',
            'event_type'    => 'required|string',
            'incident_info' => 'required|string',
            'data_sources'  => 'sometimes|array',
        ]);

        $ai_data = [
            'incident_info' => $input['incident_info'],
            'data_sources'  => $input['data_sources'] ?? [],
        ];

        $apiURL = rtrim(env('AGENTIC_URL', 'http://127.0.0.1:8222'), '/') . '/agentic/compliance_advisor';

        $request_data = array_merge($input, ['user_id' => $userId, 'business_id' => $businessId]);
        $response_data = [];
        $status = 'pending';
        $error_message = null;
        $markdown_html = null;
        $markdown = null;

        try {
            $response = Http::timeout(600)->post($apiURL, [
                'standard'         => $input['standard'],
                'jurisdiction'     => $input['jurisdiction'],
                'requirement_notes'=> '',
                'event_type'       => $input['event_type'],
                'data'             => $ai_data,
            ]);
            if (!$response->ok()) {
                $markdown_html = '<pre>Compliance service error: ' . e($response->body()) . '</pre>';
                $status = 'error';
                $error_message = $response->body();
                $response_data = ['code' => $response->status(), 'body' => $response->body()];
            } else {
                $json = $response->json();
                $markdown = $json['markdown'] ?? null;
                $response_data = $json;
                if ($markdown) {
                    $converter = new CommonMarkConverter();
                    $markdown_html = $converter->convertToHtml($markdown);
                } else {
                    $markdown_html = '<pre>' . e(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                }
                $status = 'success';
            }
        } catch (\Throwable $ex) {
            $markdown_html = '<pre>Agent error: ' . e($ex->getMessage()) . '</pre>';
            $status = 'exception';
            $error_message = $ex->getMessage();
        }

        // Log the transaction
        /*AgenticAiLog::create([
            'user_id'       => $userId,
            'business_id'   => $businessId,
            'endpoint'      => 'compliance_advisor',
            'request_data'  => $request_data,
            'response_data' => $response_data,
            'status'        => $status,
            'error_message' => $error_message,
        ]);*/

        // Repopulate form options/maps as on GET
        $standards = ComplianceStandard::query()->distinct()->pluck('standard');
        $jurisdictions = ComplianceStandard::query()->distinct()->pluck('jurisdiction');
        $data_sources = $this->getAllMonitoredDataSources();

        $pairs = ComplianceStandard::select('standard', 'jurisdiction')->get();
        $standardJurisdictionMap = [];
        $jurisdictionStandardMap = [];
        foreach ($pairs as $record) {
            $standardJurisdictionMap[$record->standard][] = $record->jurisdiction;
            $jurisdictionStandardMap[$record->jurisdiction][] = $record->standard;
        }

        // Only disable the button if the submission is "success"
        $disable_button = ($status === 'success');

        return view('agentic_ai.compliance', [
            'markdown_html'               => $markdown_html,
            'standards'                   => $standards,
            'jurisdictions'               => $jurisdictions,
            'data_sources'                => $data_sources,
            'input'                       => $input,
            'disable_button'              => $disable_button,
            'standardJurisdictionMap'     => $standardJurisdictionMap,
            'jurisdictionStandardMap'     => $jurisdictionStandardMap,
        ]);
    }

    private function getAllMonitoredDataSources()
    {
        return [
            'M365 Graph', 'AWS S3', 'SMB Share', 'Other Source 1', 'Other Source 2'
        ];
    }
}