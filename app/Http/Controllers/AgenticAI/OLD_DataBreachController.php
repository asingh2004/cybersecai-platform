<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ComplianceStandard;

class DataBreachController extends Controller
{
    private $docsApiUrl = 'http://localhost:8300/agentic/databreach_docs';
    private $contentApiUrl = 'http://localhost:8301/agentic/databreach_doc_content';

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

    public function generateBreachDocuments(Request $request)
    {
        $jurisdictions = ComplianceStandard::select('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->toArray();

        $jurisdiction = $request->input('jurisdiction');
        $organisation = $request->input('organisation_name', 'Cybersecai Pty Ltd');

        // Ideally use logged-in user's TRUE ID
        $user_id = auth()->check() ? auth()->user()->id : uniqid('user_');

        // === Step 1: Call /agentic/databreach_docs ===
        $docsResp = Http::timeout(600)
            ->post($this->docsApiUrl, [
                'jurisdiction' => $jurisdiction,
                'organisation_name' => $organisation,
                'user_id' => strval($user_id),
            ]);

        if (!$docsResp->successful() || !isset($docsResp['documents'])) {
            return view('agentic_ai.databreach_report', [
                'jurisdictions' => $jurisdictions,
                'error' => 'Failed to fetch documents list: ' . $docsResp->body(),
                'documents' => null,
                'organisation' => $organisation,
                'selectedJurisdiction' => $jurisdiction,
            ]);
        }

        $docsList = $docsResp['documents'];
        $docsJsonFile = isset($docsResp['file']) ? $docsResp['file'] : null; // <-- New: get the file output from first endpoint

        if (!is_array($docsList) || empty($docsList)) {
            return view('agentic_ai.databreach_report', [
                'jurisdictions' => $jurisdictions,
                'error' => 'No documents returned for this jurisdiction/organisation.',
                'documents' => null,
                'organisation' => $organisation,
                'selectedJurisdiction' => $jurisdiction,
            ]);
        }

        // === Step 2: Call /agentic/databreach_doc_content ===
        $contentResp = Http::timeout(600)
            ->post($this->contentApiUrl, [
                'jurisdiction' => $jurisdiction,
                'organisation_name' => $organisation,
                'documents' => $docsList,
                'user_id' => strval($user_id),
            ]);

        if (!$contentResp->successful() || !isset($contentResp['documents'])) {
            return view('agentic_ai.databreach_report', [
                'jurisdictions' => $jurisdictions,
                'error' => 'Failed to fetch document templates: ' . $contentResp->body(),
                'documents' => null,
                'organisation' => $organisation,
                'selectedJurisdiction' => $jurisdiction,
            ]);
        }

        $finalDocs = $contentResp['documents'];
        $finalJsonFile = isset($contentResp['file']) ? $contentResp['file'] : null;

        // You may want to return BOTH JSON file paths to the blade, if you wish
        return view('agentic_ai.databreach_report', [
            'jurisdictions' => $jurisdictions,
            'documents' => $finalDocs,
            'organisation' => $organisation,
            'selectedJurisdiction' => $jurisdiction,
            'user_id' => $user_id,
            'json_file_first' => $docsJsonFile,
            'json_file' => $finalJsonFile, // (for compatibility with any old view code)
        ]);
    }
}