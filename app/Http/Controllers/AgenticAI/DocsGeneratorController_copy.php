<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ComplianceStandard;
use App\Models\AgenticAiLog;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class DocsGeneratorController extends Controller
{
    private $docsApiUrl = 'http://localhost:8300/agentic/databreach_docs';
    private $contentApiUrl = 'http://localhost:8301/agentic/databreach_doc_content';
    private $agenticDocsUrl = 'http://127.0.0.1:8302';

    // ---------- Step 1: Show Generation Form ----------
    public function showForm()
    {
        $jurisdictions = ComplianceStandard::select('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->toArray();

        return view('agentic_ai.databreach_report', [
            'jurisdictions' => $jurisdictions,
            'documents' => null,
            'organisation' => null,
            'selectedJurisdiction' => null,
        ]);
    }

    // ---------- Step 2: Process Form and RUN APIs then handoff to Listing ----------
    public function generate(Request $request)
    {
        // Get classified data for user if desired for use below
        $classifiedData = $this->collectLlmResponsesForCurrentUser();

        $jurisdictions = ComplianceStandard::select('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->toArray();

        $jurisdiction = $request->input('jurisdiction');
        $organisation = $request->input('organisation_name', 'Cybersecai Pty Ltd');
        $userId = auth()->check() ? auth()->user()->id : uniqid('user_');
        $businessId = optional(auth()->user())->business_id ?? null;

        // ========== FIRST CONTROLLER 1 LOGIC ==========
        // Step 1: Call /agentic/databreach_docs
        $docsResp = Http::timeout(600)
            ->post($this->docsApiUrl, [
                'jurisdiction' => $jurisdiction,
                'organisation_name' => $organisation,
                'user_id' => strval($userId),
            ]);

        if (!$docsResp->successful() || !isset($docsResp['documents'])) {
            return back()->with('error', 'Failed to fetch documents list: ' . $docsResp->body());
        }

        $docsList = $docsResp['documents'];

        if (!is_array($docsList) || empty($docsList)) {
            return back()->with('error', 'No documents returned for this jurisdiction/organisation.');
        }

        // Step 2: Call /agentic/databreach_doc_content
        $contentResp = Http::timeout(600)
            ->post($this->contentApiUrl, [
                'jurisdiction' => $jurisdiction,
                'organisation_name' => $organisation,
                'documents' => $docsList,
                'user_id' => strval($userId),
            ]);

        if (!$contentResp->successful() || !isset($contentResp['documents'])) {
            return back()->with('error', 'Failed to fetch document templates: ' . $contentResp->body());
        }

        $finalDocs = $contentResp['documents'];

        // ========== NOW BEGIN CONTROLLER 2's LOGIC ==========
        // Step 3: Call Agentic /generate_sensitive_docs API using classifiedData if available
        $request_data = [
    		'user_id' => strval($userId),
    		'organisation_name' => $organisation,  // Add this key!
    		'json_data' => $classifiedData,
		];

        $agentic_url = $this->agenticDocsUrl . '/agentic/generate_sensitive_docs';

        $status = 'empty';
        $error_message = null;
        $results = [];
        $groupedDocs = [];

        try {
            $response = Http::timeout(1200)->post($agentic_url, $request_data);
            $status_code = $response->status();
            $raw = $response->body();

            if (!$response->ok()) {
                $status = 'error';
                $error_message = "Agentic AI backend error (HTTP $status_code): $raw";
                $results = [];
            } else {
                $json = json_decode($raw, true);
                if (isset($json['documents']) && count($json['documents']) > 0) {
                    $docs = $json['documents'];
                    // Attach download URLs for table view
                    foreach ($docs as &$doc) {
                        if (isset($doc['output_json'])) {
                            $subfolder = basename(dirname($doc['output_json']));
                            $doc['json_download_url'] = route('agenticai.docs.json_download', [
                                'user_id' => $userId,
                                'filename' => $subfolder . '/' . basename($doc['output_json']),
                            ]);
                        }
                        if (isset($doc['output_docx'])) {
                            $subfolder = basename(dirname($doc['output_docx']));
                            $doc['docx_download_url'] = route('agenticai.docs.docx_download', [
                                'user_id' => $userId,
                                'filename' => $subfolder . '/' . basename($doc['output_docx']),
                            ]);
                        }
                        // Prettify display name etc as per table group logic
                        if (empty($doc['file_display_name']) && !empty($doc['output_docx'])) {
                            $filename = basename($doc['output_docx']);
                            $filename = str_replace('_generated', '', $filename);
                            $filename = preg_replace('/\.(docx|json)$/i', '', $filename);
                            $filename = str_replace('_', ' ', $filename);
                            $doc['file_display_name'] = ucwords(trim($filename));
                        }
                        if (!isset($doc['doc_group'])) {
                            $doc_type = strtolower($doc['DocumentType'] ?? '');
                            if (strpos($doc_type, 'policy') !== false) {
                                $doc['doc_group'] = 'Policy';
                            } elseif (strpos($doc_type, 'plan') !== false) {
                                $doc['doc_group'] = 'Plan';
                            } elseif (strpos($doc_type, 'procedure') !== false || strpos($doc_type, 'process') !== false) {
                                $doc['doc_group'] = 'Procedure';
                            } elseif (preg_match('/register|log|record/', $doc_type)) {
                                $doc['doc_group'] = 'Register/Log';
                            } else {
                                $doc['doc_group'] = 'Other';
                            }
                        }
                        if (!isset($doc['is_mandatory'])) {
                            $lob = strtolower($doc['LegalOrBestPractice'] ?? '');
                            $doc['is_mandatory'] = (strpos($lob, 'mandatory') !== false);
                        }
                    }
                    $results = $docs;
                    $status = 'success';
                } else {
                    $status = 'empty';
                    $results = [];
                    $error_message = $json['detail'] ?? 'No relevant documents were generated.';
                }
            }
        } catch (\Throwable $ex) {
            Log::error("[AgenticAI][DocsGenerator] Exception: " . $ex->getMessage());
            $status = 'exception';
            $results = [];
            $error_message = $ex->getMessage();
        }

        // ---- Group and order results for output table ----
        $groupOrder = ['Policy', 'Plan', 'Procedure', 'Register/Log', 'Other'];
        if (!empty($results)) {
            foreach ($results as $doc) {
                $org = $doc['organisation_name'] ?? 'Unknown Organisation';
                $group = $doc['doc_group'] ?? 'Other';
                if (!isset($groupedDocs[$org])) $groupedDocs[$org] = [];
                if (!isset($groupedDocs[$org][$group])) $groupedDocs[$org][$group] = [];
                $groupedDocs[$org][$group][] = $doc;
            }
            foreach ($groupedDocs as $org => $groupdocs) {
                $orderedGroups = [];
                foreach ($groupOrder as $g) {
                    if (isset($groupdocs[$g])) $orderedGroups[$g] = $groupdocs[$g];
                }
                foreach ($groupdocs as $g => $docs) {
                    if (!isset($orderedGroups[$g])) $orderedGroups[$g] = $docs;
                }
                $groupedDocs[$org] = $orderedGroups;
            }
        }

        AgenticAiLog::create([
            'user_id'     => $userId,
            'business_id' => $businessId,
            'endpoint'    => 'docs_generate',
            'request_data'=> $request_data,
            'response_data'=> $results,
            'status'      => $status,
            'error_message' => $error_message ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // === FINISH BY REDIRECTING TO THE INDEX/LISTING PAGE ===
        return redirect()->route('agenticai.docs_agent.index')->with([
            'groupedDocs' => $groupedDocs,
            'results' => $results,
            'status' => $status,
            'error_message' => $error_message,
        ]);
    }


  
  // ---------- The listing/index method (displays grouped docs) ----------
  public function index()
{
    $userId = (string)auth()->id();
    $groupedDocs = [];
    $results = [];
    $user_docs_dir = base_path('databreachmgmt/' . $userId);

    Log::info("[DocsGenerator][index] userId: $userId, user_docs_dir: $user_docs_dir");

    $groupOrder = ['Policy', 'Plan', 'Procedure', 'Register/Log', 'Other'];

    if (is_dir($user_docs_dir)) {
        Log::info("[DocsGenerator][index] Directory exists: $user_docs_dir");

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($user_docs_dir)) as $file) {
            if (
                $file->isFile() &&
                strtolower($file->getExtension()) === 'json' &&
                strpos($file->getPath(), 'generated') !== false
            ) {
                $doc_data = json_decode(file_get_contents($file->getPathname()), true);

                // Support old/new formats
                if (isset($doc_data['DocumentType'])) {
                    $docs_out = [$doc_data];
                } elseif (isset($doc_data['documents'])) {
                    $docs_out = $doc_data['documents'];
                } else {
                    continue;
                }

                foreach ($docs_out as $doc) {
                    $rel_dir = basename(dirname($file->getPathname()));
                    $json_name = $file->getBasename();
                    $docx_name = str_replace('.json', '.docx', $json_name);
                    $docx_path = $file->getPath() . DIRECTORY_SEPARATOR . $docx_name;
                    $has_docx = file_exists($docx_path);

                    $file_display_name = $doc['file_display_name'] ?? null;
                    if (!$file_display_name) {
                        $tmp = $json_name;
                        $tmp = str_replace('_generated', '', $tmp);
                        $tmp = str_replace('.json', '', $tmp);
                        $tmp = str_replace('_', ' ', $tmp);
                        $file_display_name = ucwords(trim($tmp));
                    }

                    $doc_group = $doc['doc_group'] ?? null;
                    if (!$doc_group) {
                        $dt = strtolower($doc['DocumentType'] ?? '');
                        if (strpos($dt, 'policy') !== false) $doc_group = 'Policy';
                        elseif (strpos($dt, 'plan') !== false) $doc_group = 'Plan';
                        elseif (strpos($dt, 'procedure') !== false || strpos($dt, 'process') !== false) $doc_group = 'Procedure';
                        elseif (preg_match('/register|log|record/', $dt)) $doc_group = 'Register/Log';
                        else $doc_group = 'Other';
                    }

                    $is_mandatory = $doc['is_mandatory'] ?? null;
                    if ($is_mandatory === null) {
                        $lob = strtolower($doc['LegalOrBestPractice'] ?? '');
                        $is_mandatory = (strpos($lob, 'mandatory') !== false);
                    }

                    // --- Extract organisation_name robustly ---
                    if (!empty($doc['organisation_name'])) {
                        $org = $doc['organisation_name'];
                    } elseif (!empty($doc['OrganisationName'])) {
                        $org = $doc['OrganisationName'];
                    } else {
                        // Fallback: try from filename
                        $base = preg_replace('/\.json$/i', '', $json_name);
                        if (preg_match('/^(.*)__\d+$/', $base, $m)) {
                            $org_str = $m[1];
                            $org = trim(str_replace('_', ' ', $org_str));
                        } else {
                            $org = trim(str_replace('_', ' ', $base));
                        }
                        if(!$org) $org = 'Unknown Organisation';
                    }

                    $json_download_url = route('agenticai.docs.json_download', [
                        'user_id' => $userId,
                        'filename' => $rel_dir . '/' . $json_name,
                    ]);
                    $docx_download_url = $has_docx ? route('agenticai.docs.docx_download', [
                        'user_id' => $userId,
                        'filename' => $rel_dir . '/' . $docx_name,
                    ]) : '';

                    $doc_entry = [
                        'file_display_name' => $file_display_name,
                        'DocumentType' => $doc['DocumentType'] ?? '',
                        'is_mandatory' => $is_mandatory,
                        'doc_group' => $doc_group,
                        'organisation_name' => $org,
                        'json_download_url' => $json_download_url,
                        'docx_download_url' => $docx_download_url,
                        'markdown' => $doc['markdown'] ?? '',
                    ];

                    $results[] = $doc_entry;

                    if (!isset($groupedDocs[$org])) $groupedDocs[$org] = [];
                    if (!isset($groupedDocs[$org][$doc_group])) $groupedDocs[$org][$doc_group] = [];
                    $groupedDocs[$org][$doc_group][] = $doc_entry;
                }
            }
        }
        // Reorder grouping
        foreach ($groupedDocs as $org => $groupdocs) {
            $orderedGroups = [];
            foreach ($groupOrder as $g) {
                if (isset($groupdocs[$g])) $orderedGroups[$g] = $groupdocs[$g];
            }
            foreach ($groupdocs as $g => $docs) {
                if (!isset($orderedGroups[$g])) $orderedGroups[$g] = $docs;
            }
            $groupedDocs[$org] = $orderedGroups;
        }
    } else {
        Log::warning("[DocsGenerator][index] Directory does NOT exist: $user_docs_dir");
    }

    // Accept flash from redirect for possible status messages
    $flash = session()->all();
    $status = $flash['status'] ?? 'success';
    $error_message = $flash['error_message'] ?? null;

    return view('agentic_ai.docs_generator_agent', [
        'groupedDocs' => $groupedDocs,
        'results' => $results,
        'status' => $status,
        'error_message' => $error_message,
    ]);
}
  
  
/*public function index()
{
    $userId = (string)auth()->id();
    $groupedDocs = [];
    $results = [];
    $user_docs_dir = base_path('databreachmgmt/' . $userId);

    Log::info("[DocsGenerator][index] userId: $userId, user_docs_dir: $user_docs_dir");

    $groupOrder = ['Policy', 'Plan', 'Procedure', 'Register/Log', 'Other'];
    //$orgNameForAllDocs = null;   // <-- This will store the organization name, once found.

    if (is_dir($user_docs_dir)) {
        Log::info("[DocsGenerator][index] Directory exists: $user_docs_dir");

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($user_docs_dir)) as $file) {
            if (
                $file->isFile() &&
                strtolower($file->getExtension()) === 'json' &&
                strpos($file->getPath(), 'generated') !== false
            ) {
                $doc_data = json_decode(file_get_contents($file->getPathname()), true);

                // Support old/new formats
                if (isset($doc_data['DocumentType'])) {
                    $docs_out = [$doc_data];
                } elseif (isset($doc_data['documents'])) {
                    $docs_out = $doc_data['documents'];
                } else {
                    continue;
                }

                foreach ($docs_out as $doc) {
                    $rel_dir = basename(dirname($file->getPathname()));
                    $json_name = $file->getBasename();
                    $docx_name = str_replace('.json', '.docx', $json_name);
                    $docx_path = $file->getPath() . DIRECTORY_SEPARATOR . $docx_name;
                    $has_docx = file_exists($docx_path);

                    $file_display_name = $doc['file_display_name'] ?? null;
                    if (!$file_display_name) {
                        $tmp = $json_name;
                        $tmp = str_replace('_generated', '', $tmp);
                        $tmp = str_replace('.json', '', $tmp);
                        $tmp = str_replace('_', ' ', $tmp);
                        $file_display_name = ucwords(trim($tmp));
                    }

                    $doc_group = $doc['doc_group'] ?? null;
                    if (!$doc_group) {
                        $dt = strtolower($doc['DocumentType'] ?? '');
                        if (strpos($dt, 'policy') !== false) $doc_group = 'Policy';
                        elseif (strpos($dt, 'plan') !== false) $doc_group = 'Plan';
                        elseif (strpos($dt, 'procedure') !== false || strpos($dt, 'process') !== false) $doc_group = 'Procedure';
                        elseif (preg_match('/register|log|record/', $dt)) $doc_group = 'Register/Log';
                        else $doc_group = 'Other';
                    }

                    $is_mandatory = $doc['is_mandatory'] ?? null;
                    if ($is_mandatory === null) {
                        $lob = strtolower($doc['LegalOrBestPractice'] ?? '');
                        $is_mandatory = (strpos($lob, 'mandatory') !== false);
                    }

                    // --- Extract org name from first file's filename only ---
                    if ($orgNameForAllDocs === null) {
                        // Extract from filename, e.g. "Australia_CybersecAI_Pty_Ltd_Australia__22.json"
                        $base = preg_replace('/\.json$/i', '', $json_name);
                        if (preg_match('/^(.*)__\d+$/', $base, $m)) {
                            $org_str = $m[1];
                            $organisation_name = trim(str_replace('_', ' ', $org_str));
                        } else {
                            $organisation_name = trim(str_replace('_', ' ', $base));
                        }
                        $orgNameForAllDocs = $organisation_name ?: 'Unknown Organisation';
                    }
                    $org = $orgNameForAllDocs;

                    $json_download_url = route('agenticai.docs.json_download', [
                        'user_id' => $userId,
                        'filename' => $rel_dir . '/' . $json_name,
                    ]);
                    $docx_download_url = $has_docx ? route('agenticai.docs.docx_download', [
                        'user_id' => $userId,
                        'filename' => $rel_dir . '/' . $docx_name,
                    ]) : '';

                    $doc_entry = [
                        'file_display_name' => $file_display_name,
                        'DocumentType' => $doc['DocumentType'] ?? '',
                        'is_mandatory' => $is_mandatory,
                        'doc_group' => $doc_group,
                        'organisation_name' => $org,
                        'json_download_url' => $json_download_url,
                        'docx_download_url' => $docx_download_url,
                        'markdown' => $doc['markdown'] ?? '',
                    ];

                    $results[] = $doc_entry;

                    if (!isset($groupedDocs[$org])) $groupedDocs[$org] = [];
                    if (!isset($groupedDocs[$org][$doc_group])) $groupedDocs[$org][$doc_group] = [];
                    $groupedDocs[$org][$doc_group][] = $doc_entry;
                }
            }
        }
        // Reorder grouping
        foreach ($groupedDocs as $org => $groupdocs) {
            $orderedGroups = [];
            foreach ($groupOrder as $g) {
                if (isset($groupdocs[$g])) $orderedGroups[$g] = $groupdocs[$g];
            }
            foreach ($groupdocs as $g => $docs) {
                if (!isset($orderedGroups[$g])) $orderedGroups[$g] = $docs;
            }
            $groupedDocs[$org] = $orderedGroups;
        }
    } else {
        Log::warning("[DocsGenerator][index] Directory does NOT exist: $user_docs_dir");
    }

    // Accept flash from redirect for possible status messages
    $flash = session()->all();
    $status = $flash['status'] ?? 'success';
    $error_message = $flash['error_message'] ?? null;

    return view('agentic_ai.docs_generator_agent', [
        'groupedDocs' => $groupedDocs,
        'results' => $results,
        'status' => $status,
        'error_message' => $error_message,
    ]);
}*/
  
  	public function deleteDocument(Request $request)
{
    $userId = (string)auth()->id();

    $jsonUrl = $request->input('json_path');
    $docxUrl = $request->input('docx_path');
    $error = null;

    // Convert download URLs into file system paths (VERY IMPORTANT: secure the path)
    $match = [];
    // Parse e.g. /agenticai/docs/json_download/{user_id}/generated/Foo.json
    if (preg_match('~/json_download/([^/]+)/(.+)$~', $jsonUrl, $match)) {
        $jsonFile = base_path('databreachmgmt/' . $match[1] . '/' . $match[2]);
    } else {
        $jsonFile = null;
    }
    if (preg_match('~/docx_download/([^/]+)/(.+)$~', $docxUrl, $match)) {
        $docxFile = base_path('databreachmgmt/' . $match[1] . '/' . $match[2]);
    } else {
        $docxFile = null;
    }

    try {
        if ($jsonFile && file_exists($jsonFile)) unlink($jsonFile);
        if ($docxFile && file_exists($docxFile)) unlink($docxFile);
    } catch (\Throwable $ex) {
        Log::error("[AgenticAI][DocsGenerator] Delete error: " . $ex->getMessage());
        $error = $ex->getMessage();
    }

    return redirect()->route('agenticai.docs_agent.index')->with('error_message', $error ?: 'Document deleted');    
}
  

    // ---------- Download endpoint wrappers ----------
    public function jsonDownload($user_id, $filename)
    {
        $base_url = env('AGENTIC_DOCS_URL', $this->agenticDocsUrl);
        $url = "{$base_url}/agentic/download_json/{$user_id}/{$filename}";
        try {
            $stream = Http::timeout(1200)->get($url)->body();
            if (!$stream) {
                abort(404, "File not found or inaccessible.");
            }
            return response($stream)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="'.basename($filename).'"');
        } catch (\Throwable $ex) {
            Log::error("[AgenticAI][DocsGenerator][jsonDownload] " . $ex->getMessage());
            abort(500, 'Error downloading the generated JSON.');
        }
    }
    public function docxDownload($user_id, $filename)
    {
        $base_url = env('AGENTIC_DOCS_URL', $this->agenticDocsUrl);
        $url = "{$base_url}/agentic/download_docx/{$user_id}/{$filename}";
        try {
            $stream = Http::timeout(1200)->get($url)->body();
            if (!$stream) {
                abort(404, "File not found or inaccessible.");
            }
            return response($stream)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->header('Content-Disposition', 'attachment; filename="'.basename($filename).'"');
        } catch (\Throwable $ex) {
            Log::error("[AgenticAI][DocsGenerator][docxDownload] " . $ex->getMessage());
            abort(500, 'Error downloading the generated Word file.');
        }
    }

    // --- Existing auxiliary helpers ---
    private function collectLlmResponsesForCurrentUser()
    {
        $user = auth()->user();
        if (!$user) {
            Log::warning("[AgenticAI][DocsGenerator] No user available in auth()->user().");
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
                $json_array = [];
                if (is_array($json) && array_keys($json) === range(0, count($json)-1)) {
                    $json_array = $json;
                } elseif (is_array($json)) {
                    $json_array = [$json];
                } else {
                    continue;
                }
                foreach ($json_array as $dataEntry) {
                    if (
                        isset($dataEntry['llm_response']) &&
                        is_string($dataEntry['llm_response']) &&
                        strlen(trim($dataEntry['llm_response'])) > 0
                    ) {
                        $llmJson = json_decode($dataEntry['llm_response'], true);
                        if (
                            is_array($llmJson) &&
                            isset($llmJson['overall_risk_rating']) &&
                            strtoupper($llmJson['overall_risk_rating']) === 'HIGH'
                        ) {
                            $infoList[] = [
                                'llm_markdown' => $dataEntry['llm_response'],
                                'source'       => $source,
                                'source_file'  => $filename,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("[AgenticAI][DocsGenerator] Could not process $filename: {$e->getMessage()}");
                continue;
            }
        }
        Log::info("[AgenticAI][DocsGenerator] Finished collecting llm_response as markdown for HIGH risk", [
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