<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use App\Models\AgenticAiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocsGeneratorController extends Controller
{
    protected string $docsListEndpoint;
    protected string $docsContentEndpoint;
    protected string $sensitiveDocsEndpoint;
    protected string $storageRoot;

    public function __construct()
    {
        $this->docsListEndpoint      = rtrim(env('AGENTIC_DOCS_LIST_URL', 'http://127.0.0.1:8300'), '/');
        $this->docsContentEndpoint   = rtrim(env('AGENTIC_DOCS_CONTENT_URL', 'http://127.0.0.1:8301'), '/');
        $this->sensitiveDocsEndpoint = rtrim(env('AGENTIC_SENSITIVE_DOCS_URL', 'http://127.0.0.1:8302'), '/');
        $this->storageRoot           = base_path('databreachmgmt');
    }

    public function index(Request $request)
    {
        $user       = $request->user();
        $userId     = (string) ($user->id ?? '');
        $businessId = $user?->business_id;

        $profile = $businessId
            ? DB::table('business_profile')
                ->select('industry', 'country', 'about_company')
                ->where('business_id', $businessId)
                ->first()
            : null;

        $docs   = $this->collectGeneratedDocs($userId);
        $status = session('status', 'success');
        $error  = session('error_message');

        return view('agentic_ai.docs_generator_agent', [
            'businessId'  => $businessId,
            'profile'     => $profile,
            'groupedDocs' => $docs['grouped'],
            'results'     => $docs['flat'],
            'status'      => $status,
            'error_message' => $error,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'organisation_name' => 'required|string|max:255',
        ]);

        $user       = $request->user();
        $userId     = (string) ($user->id ?? 0);
        $businessId = $user?->business_id;

        if (!$userId) {
            return back()->with('error', 'You must be logged in to generate documents.');
        }

        $profile = $businessId
            ? DB::table('business_profile')
                ->select('industry', 'country', 'about_company')
                ->where('business_id', $businessId)
                ->first()
            : null;

        if (!$profile) {
            return back()->with('error', 'Complete Essential Setup before generating documents.');
        }

        if (!$profile->industry || !$profile->country) {
            return back()->with('error', 'Industry and Country must be set in Essential Setup to tailor documents.');
        }

        $organisationName = $request->input('organisation_name');
        session()->put('last_org_name', $organisationName);

        $context = [
            'industry'       => $profile->industry,
            'country'        => $profile->country,
            'about_company'  => $profile->about_company ?? '',
        ];

        $classifiedData = $this->collectLlmResponsesForCurrentUser();

        $docsResponse = Http::timeout(1200)
            ->acceptJson()
            ->asJson()
            ->post($this->docsListEndpoint . '/agentic/databreach_docs', [
                'organisation_name' => $organisationName,
                'industry'          => $context['industry'],
                'country'           => $context['country'],
                'about_business'    => $context['about_company'], // legacy prompt still expects this key
                'business_id'       => (string) $businessId,
                'user_id'           => $userId,
            ]);

        if (!$docsResponse->successful()) {
            return back()->with('error', 'Unable to retrieve document list: ' . $docsResponse->body());
        }

        $docsList = $docsResponse->json('documents', []);
        if (empty($docsList)) {
            return back()->with('error', 'AI did not return any recommended documents.');
        }

        $contentResponse = Http::timeout(1200)
            ->acceptJson()
            ->asJson()
            ->post($this->docsContentEndpoint . '/agentic/databreach_doc_content', [
                'organisation_name' => $organisationName,
                'user_id'           => $userId,
                'documents'         => $docsList,
                'business_context'  => [
                    'industry' => $context['industry'],
                    'country'  => $context['country'],
                    'about_business' => $context['about_company'],
                ],
            ]);

        if (!$contentResponse->successful()) {
            return back()->with('error', 'Unable to create document templates: ' . $contentResponse->body());
        }

        $baseTemplates = $contentResponse->json('documents', []);
        if (empty($baseTemplates)) {
            return back()->with('error', 'Template service returned no documents.');
        }

        $requestData = [
            'user_id'           => $userId,
            'organisation_name' => $organisationName,
            'json_data'         => $classifiedData,
            'business_context'  => [
                'industry'       => $context['industry'],
                'country'        => $context['country'],
                'about_business' => $context['about_company'],
            ],
        ];

        $finalResponse = Http::timeout(1200)
            ->acceptJson()
            ->asJson()
            ->post($this->sensitiveDocsEndpoint . '/agentic/generate_sensitive_docs', $requestData);

        $status = 'success';
        $errorMessage = null;
        $results = [];

        if ($finalResponse->failed()) {
            $status = 'error';
            $errorMessage = 'Final document generation failed: ' . $finalResponse->body();
        } else {
            $payload = $finalResponse->json();
            if (isset($payload['detail'])) {
                $status = 'empty';
                $errorMessage = $payload['detail'];
            } else {
                $results = $payload['documents'] ?? [];
                if (empty($results)) {
                    $status = 'empty';
                    $errorMessage = 'No relevant documents created.';
                }
            }
        }

        AgenticAiLog::create([
            'user_id'       => $userId,
            'business_id'   => $businessId,
            'endpoint'      => 'docs_generate',
            'request_data'  => $requestData,
            'response_data' => $results,
            'status'        => $status,
            'error_message' => $errorMessage,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return redirect()
            ->route('agenticai.docs_agent.index')
            ->with('status', $status)
            ->with('error_message', $errorMessage);
    }

    public function deleteDocument(Request $request)
    {
        $jsonFile = $this->resolveDownloadPath($request->input('json_path'));
        $docxFile = $this->resolveDownloadPath($request->input('docx_path'));

        try {
            if ($jsonFile && file_exists($jsonFile)) unlink($jsonFile);
            if ($docxFile && file_exists($docxFile)) unlink($docxFile);
        } catch (\Throwable $e) {
            Log::error('[DocsGenerator] Delete error: ' . $e->getMessage());
            return redirect()->route('agenticai.docs_agent.index')
                ->with('error_message', 'Unable to delete file(s): ' . $e->getMessage());
        }

        return redirect()->route('agenticai.docs_agent.index')
            ->with('success', 'Document deleted.');
    }

    public function jsonDownload($user_id, $filename)
    {
        $url = $this->sensitiveDocsEndpoint . "/agentic/download_json/{$user_id}/{$filename}";
        try {
            $stream = Http::timeout(1200)->get($url)->body();
            if (!$stream) abort(404);
            return response($stream)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="'.basename($filename).'"');
        } catch (\Throwable $e) {
            Log::error('[DocsGenerator] jsonDownload: ' . $e->getMessage());
            abort(500, 'Error downloading JSON.');
        }
    }

    public function docxDownload($user_id, $filename)
    {
        $url = $this->sensitiveDocsEndpoint . "/agentic/download_docx/{$user_id}/{$filename}";
        try {
            $stream = Http::timeout(1200)->get($url)->body();
            if (!$stream) abort(404);
            return response($stream)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->header('Content-Disposition', 'attachment; filename="'.basename($filename).'"');
        } catch (\Throwable $e) {
            Log::error('[DocsGenerator] docxDownload: ' . $e->getMessage());
            abort(500, 'Error downloading DOCX.');
        }
    }

    protected function resolveDownloadPath(?string $downloadUrl): ?string
    {
        if (!$downloadUrl) return null;
        if (!preg_match('~/agenticai/docs/(json|docx)_download/([^/]+)/(.+)$~', $downloadUrl, $matches)) {
            return null;
        }
        return base_path("databreachmgmt/{$matches[2]}/{$matches[3]}");
    }

    protected function collectGeneratedDocs(string $userId): array
{
    $flat = [];
    $grouped = [];
    if (!$userId) return compact('flat', 'grouped');

    $userDir = $this->storageRoot . '/' . $userId;
    if (!is_dir($userDir)) return compact('flat', 'grouped');

    $groupOrder = ['Policy', 'Plan', 'Procedure', 'Register/Log', 'Other'];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($userDir, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') continue;
        if (strpos($file->getPathname(), 'generated') === false) continue;

        $data = json_decode(file_get_contents($file->getPathname()), true);
        $docs = [];

        if (isset($data['documents'])) {
            $docs = $data['documents'];
        } elseif (isset($data['DocumentType'])) {
            $docs = [$data];
        }

        foreach ($docs as $doc) {
            $display = $doc['file_display_name']
                ?? ucwords(str_replace(['_generated', '.json', '_'], ['','',' '], $file->getBasename()));
            $docGroup = $doc['doc_group'] ?? $this->inferDocGroup($doc['DocumentType'] ?? '');

            $isMandatory = $doc['is_mandatory'] ?? null;
            if ($isMandatory === null) {
                $lob = strtolower($doc['LegalOrBestPractice'] ?? '');
                $isMandatory = str_contains($lob, 'mandatory');
            }

            $relDir = basename($file->getPath());
            $jsonDownload = route('agenticai.docs.json_download', [
                'user_id' => $userId,
                'filename' => "{$relDir}/{$file->getBasename()}",
            ]);
            $docxName = preg_replace('/\.json$/', '.docx', $file->getBasename());
            $docxPath = $file->getPath() . '/' . $docxName;

            $docxDownload = file_exists($docxPath)
                ? route('agenticai.docs.docx_download', [
                    'user_id' => $userId,
                    'filename' => "{$relDir}/{$docxName}",
                ])
                : null;

            $entry = [
                'file_display_name' => $display,
                'DocumentType'      => $doc['DocumentType'] ?? '',
                'doc_group'         => $docGroup,
                'is_mandatory'      => $isMandatory,
                'json_download_url' => $jsonDownload,
                'docx_download_url' => $docxDownload,
                'organisation_name' => $doc['organisation_name'] ?? ($doc['OrganisationName'] ?? 'Unknown Organisation'),
                'markdown'          => $doc['markdown'] ?? '',
            ];

            $flat[] = $entry;
            $org = $entry['organisation_name'];

            if (!isset($grouped[$org])) $grouped[$org] = [];
            if (!isset($grouped[$org][$docGroup])) $grouped[$org][$docGroup] = [];
            $grouped[$org][$docGroup][] = $entry;
        }
    }

    foreach ($grouped as $org => $groups) {
        $ordered = [];
        foreach ($groupOrder as $groupName) {
            if (isset($groups[$groupName])) {
                $ordered[$groupName] = $groups[$groupName];
            }
        }
        foreach ($groups as $groupName => $docs) {
            if (!isset($ordered[$groupName])) {
                $ordered[$groupName] = $docs;
            }
        }
        $grouped[$org] = $ordered;
    }

    return ['flat' => $flat, 'grouped' => $grouped];
}

    protected function inferDocGroup(string $type): string
    {
        $t = strtolower($type);
        return match (true) {
            str_contains($t, 'policy')    => 'Policy',
            str_contains($t, 'plan')      => 'Plan',
            str_contains($t, 'procedure'),
            str_contains($t, 'process')   => 'Procedure',
            str_contains($t, 'register'),
            str_contains($t, 'log'),
            str_contains($t, 'record')    => 'Register/Log',
            default                       => 'Other',
        };
    }

    private function collectLlmResponsesForCurrentUser(): array
    {
        $user = auth()->user();
        if (!$user) return [];

        $configIDs = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
        $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/';
        $sources = ['M365', 'SMB', 'S3'];
        $infoList = [];

        foreach ($configIDs as $id) {
            foreach ($sources as $type) {
                $graphPath = "{$basePath}{$type}/{$id}/graph/";
                if (!is_dir($graphPath)) continue;

                foreach (glob($graphPath . '*.json') as $jsonFile) {
                    try {
                        $records = json_decode(file_get_contents($jsonFile), true);
                        if (!$records) continue;
                        if (!is_array($records)) $records = [$records];

                        foreach ($records as $record) {
                            if (!isset($record['llm_response'])) continue;
                            $llmJson = json_decode($record['llm_response'], true);
                            if (
                                is_array($llmJson) &&
                                isset($llmJson['overall_risk_rating']) &&
                                strtoupper($llmJson['overall_risk_rating']) === 'HIGH'
                            ) {
                                $infoList[] = [
                                    'llm_markdown' => $record['llm_response'],
                                    'source'       => $this->detectSourceFromPath($jsonFile),
                                    'source_file'  => $jsonFile,
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[DocsGenerator] Failed to parse ' . $jsonFile . ': ' . $e->getMessage());
                    }
                }
            }
        }

        Log::info('[DocsGenerator] Collected ' . count($infoList) . ' high-risk snippets for doc personalization.');
        return $infoList;
    }

    private function detectSourceFromPath(string $path): string
    {
        return match (true) {
            str_contains($path, '/SMB/') => 'SMB',
            str_contains($path, '/M365/') => 'M365',
            str_contains($path, '/S3/') => 'AWS S3',
            default => 'unknown',
        };
    }
}