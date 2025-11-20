<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use App\Models\AgenticAiLog;

class InternalAuditorController extends Controller
{
    public function index()
    {
        return view('agentic_ai.auditor');
    }
	
  	public function run(Request $request)
	{
    $region = $request->input('region', 'USA');
    $userId = auth()->id();
    $businessId = optional(auth()->user())->business_id ?? null; // Change if you have a different logic
    $request_data = [];

    $json_data = $this->collectLlmResponsesForCurrentUser();

    // Logging the outgoing request
    $request_data = [
        'region' => $region,
        'user_id' => $userId,
        'records_count' => count($json_data),
        'record_sources' => array_column($json_data, 'source'),
    ];

    if (empty($json_data)) {
        $markdown = "No relevant data found for audit for your account.";
        $status = 'empty';
        $error_message = null;
    } else {
        $all_markdown = implode("\n---\n", array_column($json_data, 'llm_markdown'));
        try {
            $apiURL = env('AGENTIC_URL', 'http://127.0.0.1:8223') . '/agentic/audit';
            $response = Http::timeout(600)->post($apiURL, [
                'region' => $region,
                'json_data' => $all_markdown,
            ]);
            $status_code = $response->status();
            $raw = $response->body();

            if (!$response->ok()) {
                $markdown = "Could not contact backend AI service.\n\nError detail: $raw";
                $status = 'error';
                $error_message = "HTTP $status_code: $raw";
                $response_data = ['raw' => $raw, 'status' => $status_code];
            } else {
                $json = json_decode($raw, true);
                if (is_array($json) && isset($json['markdown'])) {
                    $markdown = $json['markdown'];
                    $response_data = $json;
                    $status = 'success';
                    $error_message = null;
                } else {
                    $markdown = "No response from LLM AI agent.";
                    $status = 'api_no_markdown';
                    $error_message = 'No "markdown" in agentic response';
                    $response_data = $json ?: ['raw' => $raw];
                }
            }
        } catch (\Throwable $ex) {
            $markdown = "Error during audit: {$ex->getMessage()}";
            $status = 'exception';
            $error_message = $ex->getMessage();
            $response_data = [];
        }
    }

    // ==== Ensure $markdown is a string before convertToHtml ====
    if (is_array($markdown)) $markdown = implode("\n", $markdown);
    if (!is_string($markdown)) $markdown = strval($markdown);

    // Store the log
    /*AgenticAiLog::create([
        'user_id'      => $userId,
        'business_id'  => $businessId,
        'endpoint'     => 'audit',
        'request_data' => $request_data,
        'response_data'=> isset($response_data) ? $response_data : [],
        'status'       => $status,
        'error_message'=> $error_message ?? null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);*/

    // Render result
    $converter = new CommonMarkConverter();
    $html = $converter->convertToHtml($markdown);

    return view('agentic_ai.auditor', [
        'markdown_html' => $html,
        'region' => $region,
        'disable_button' => true,
    ]);
	}
    
  
private function collectLlmResponsesForCurrentUser()
{
    $user = auth()->user();
    if (!$user) {
        Log::warning("[Audit] No user available in auth()->user().");
        return [];
    }

    $configIDs = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/';
    $jsonFiles = [];

    foreach ($configIDs as $id) {
        foreach (['M365', 'SMB', 'S3'] as $type) {
            $graphPath = "{$basePath}{$type}/{$id}/graph/";
            if (is_dir($graphPath)) {
                $files = glob($graphPath . '*.json');
                if ($files) {
                    foreach ($files as $jsonFile) {
                        $jsonFiles[] = [
                            'path' => $jsonFile,
                            'source' => $this->detectSourceFromPath($jsonFile),
                        ];
                    }
                }
            }
        }
    }

    $infoList = [];
    foreach ($jsonFiles as $entry) {
        $filename = $entry['path'];
        $source   = $entry['source'];
        try {
            $content = file_get_contents($filename);
            $json = json_decode($content, true);

            // Accept a single object or array-of-objects as root
            $json_array = [];
            if (is_array($json) && array_keys($json) === range(0, count($json)-1)) {
                $json_array = $json; // Already array of objects
            } elseif (is_array($json)) {
                $json_array = [$json]; // Single object
            } else {
                continue;
            }

            foreach ($json_array as $dataEntry) {
                if (
                    isset($dataEntry['llm_response']) &&
                    is_string($dataEntry['llm_response']) &&
                    strlen(trim($dataEntry['llm_response'])) > 0
                ) {
                    // Try to decode llm_response as JSON
                    $llmJson = json_decode($dataEntry['llm_response'], true);
                    if (
                        is_array($llmJson) &&
                        isset($llmJson['overall_risk_rating']) &&
                        strtoupper($llmJson['overall_risk_rating']) === 'HIGH'
                    ) {
                        $infoList[] = [
                            // Send the original llm_response, which is JSON, so the LLM audit prompt sees all the JSON fields
                            'llm_markdown' => $dataEntry['llm_response'],
                            'source'       => $source,
                            'source_file'  => $filename,
                            // Optionally, pass other identifiers
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[Audit] Could not process $filename: {$e->getMessage()}");
            continue;
        }
    }
    Log::info("[Audit] Finished collecting llm_response as markdown for HIGH risk", [
        'total_files' => count($jsonFiles),
        'high_risk_records' => count($infoList)
    ]);
    return $infoList;
}

        private function detectSourceFromPath($path)
    {
        if (stripos($path, '/SMB/') !== false) return 'SMB';
        if (stripos($path, '/M365/') !== false) return 'M365';
        if (stripos($path, '/S3/') !== false) return 'AWS S3';
        return 'unknown';
    }
}