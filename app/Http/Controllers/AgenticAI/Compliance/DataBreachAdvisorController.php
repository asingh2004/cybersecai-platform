<?php

namespace App\Http\Controllers\AgenticAI\Compliance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser as PdfParser;
use Spatie\PdfToText\Pdf as PdfToText;

class DataBreachAdvisorController extends Controller
{
    public function index(Request $request)
    {
        $user       = $request->user();
        $businessId = $user?->business_id;

        $profile = $businessId
            ? DB::table('business_profile')->where('business_id', $businessId)->first()
            : null;

        $selectedRegulations = $this->normalizeRegulations($profile->selected_regulations ?? null);

        $result      = session()->pull('breach_result');
        $breachInput = session()->pull('breach_input');

        return view('agentic_ai.data_breach_advisor', compact(
            'businessId',
            'selectedRegulations',
            'result',
            'breachInput'
        ));
    }

    public function analyze(Request $request)
    {
        $user       = $request->user();
        $businessId = $user?->business_id;

        Log::info('DataBreachAdvisor: analyze invoked', [
            'user_id'     => $user->id ?? null,
            'business_id' => $businessId,
            'payload'     => $request->all(),
        ]);

        if (!$businessId) {
            Log::warning('DataBreachAdvisor: missing business_id.');
            return back()->withErrors(['event_title' => 'Business ID missing on your profile.'])
                         ->withInput();
        }

        $profile = DB::table('business_profile')->where('business_id', $businessId)->first();
        $selectedRegulations = $this->normalizeRegulations($profile->selected_regulations ?? null);

        Log::info('DataBreachAdvisor: normalized regulations', [
            'count' => is_countable($selectedRegulations) ? count($selectedRegulations) : 0,
            'type'  => gettype($selectedRegulations),
        ]);

        if (empty($selectedRegulations)) {
            return back()->withErrors(['event_title' => 'No regulations saved in Essential Setup.'])
                         ->withInput();
        }

        $validated = $request->validate([
            'event_title'   => 'required|string|max:255',
            'incident_text' => 'nullable|string',
            'evidence_file' => 'nullable|file|max:6144|mimetypes:application/pdf,text/plain,message/rfc822,text/rtf,text/html,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], [
            'evidence_file.mimetypes' => 'Allowed file types: PDF, DOCX, TXT, EML, RTF, HTML.',
        ]);

        Log::info('DataBreachAdvisor: validated fields', $validated);

        if (empty($validated['incident_text']) && !$request->hasFile('evidence_file')) {
            Log::warning('DataBreachAdvisor: no incident text or file supplied.');
            throw ValidationException::withMessages([
                'incident_text' => 'Provide incident text or upload a document.',
            ]);
        }

        $fileText = '';
        $fileMeta = null;

        if ($request->hasFile('evidence_file')) {
            $file = $request->file('evidence_file');
            $fileMeta = [
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size_kb'       => round($file->getSize() / 1024, 1),
            ];
            Log::info('DataBreachAdvisor: received file', $fileMeta);

            $ext = strtolower($file->getClientOriginalExtension());
            if ($file->getMimeType() === 'application/pdf' || $ext === 'pdf') {
                $fileText = $this->extractPdfText($file->getRealPath());
                if ($fileText === '') {
                    Log::error('DataBreachAdvisor: PDF unreadable after both parsers.');
                    throw ValidationException::withMessages([
                        'evidence_file' => 'We could not read text from this PDF (possibly encrypted or scanned).',
                    ]);
                }
            } elseif ($ext === 'docx' || $file->getMimeType() === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                $fileText = $this->extractDocxText($file->getRealPath());
                if ($fileText === '') {
                    Log::error('DataBreachAdvisor: DOCX unreadable.');
                    throw ValidationException::withMessages([
                        'evidence_file' => 'We could not read text from this DOCX. Please upload a text-based copy.',
                    ]);
                }
            } else {
                $fileText = trim(file_get_contents($file->getRealPath()) ?: '');
            }
            Log::info('DataBreachAdvisor: extracted text length', ['len' => strlen($fileText)]);
        }

        $incidentText = trim(($validated['incident_text'] ?? '') . "\n" . $fileText);
        Log::info('DataBreachAdvisor: compiled incident text', ['length' => strlen($incidentText)]);

        if ($incidentText === '') {
            Log::warning('DataBreachAdvisor: incident text empty after compile.');
            throw ValidationException::withMessages([
                'incident_text' => 'No readable text was found in your upload. Please add content.',
            ]);
        }

        $payload = [
            'business_id'          => (string) $businessId,
            'user_id'              => $user->id ?? null,
            'event_title'          => $validated['event_title'],
            'incident_text'        => $incidentText,
            'selected_regulations' => $selectedRegulations,
            'evidence_meta'        => $fileMeta,
        ];

        $endpoint = config('services.agentic_ai.data_breach_url');
        if (!$endpoint) {
            Log::critical('DataBreachAdvisor: endpoint not configured.');
            return back()->with('error', 'Agentic AI endpoint is not configured.')->withInput();
        }

        $url = rtrim($endpoint, '/') . '/databreach/analyze';

        Log::info('DataBreachAdvisor: HTTP request prepared', [
            'url'          => $url,
            'payload_meta' => [
                'title'     => $payload['event_title'],
                'text_len'  => strlen($payload['incident_text']),
                'reg_count' => count($payload['selected_regulations']),
                'has_file'  => (bool) $fileMeta,
            ],
        ]);

        try {
            $response = Http::timeout(180)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('DataBreachAdvisor: HTTP exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'AI service unavailable: ' . $e->getMessage())->withInput();
        }

        Log::info('DataBreachAdvisor: response received', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if ($response->failed()) {
            $detail = $response->json()['detail'] ?? $response->body();
            $detailString = is_array($detail) ? json_encode($detail) : (string) $detail;

            Log::warning('DataBreachAdvisor: AI failure', [
                'status' => $response->status(),
                'detail' => $detailString,
            ]);

            return back()->with('error', 'AI service error: ' . $response->status() . ' — ' . $detailString)
                ->withInput();
        }

        $body = $response->json();
        Log::info('DataBreachAdvisor: AI success decoded', [
            'has_assessment' => isset($body['assessment']),
            'keys'           => array_keys($body ?? []),
        ]);

        if (!$body || !isset($body['assessment'])) {
            Log::error('DataBreachAdvisor: assessment missing or malformed.', ['body' => $body]);
            return back()->with('error', 'Unexpected AI response.')->withInput();
        }

        return redirect()
            ->route('agentic_ai.compliance.breach.index')
            ->with('success', 'Assessment completed.')
            ->with('breach_result', $body)
            ->with('breach_input', [
                'event_title'   => $validated['event_title'],
                'incident_text' => $request->input('incident_text'),
                'file_meta'     => $fileMeta,
            ]);
    }

    protected function normalizeRegulations($raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $value = $raw;
        $depth = 0;

        while ($depth < 3 && is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $value = $decoded;
            $depth++;
        }

        return is_array($value) ? $value : [];
    }

    protected function extractPdfText(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($path);
            $text   = trim($pdf->getText());
            if ($text !== '') {
                return $text;
            }
        } catch (\Throwable $e) {
            Log::warning('DataBreachAdvisor: Smalot PDF parse failed', ['error' => $e->getMessage()]);
        }

        try {
            $binary = config('services.pdf_to_text.binary', env('PDFTOTEXT_PATH', '/usr/bin/pdftotext'));
            if ($binary && is_file($binary)) {
                $text = trim(PdfToText::create($binary)->setPdf($path)->text());
                if ($text !== '') {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DataBreachAdvisor: pdftotext fallback failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    protected function extractDocxText(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) === true) {
            $index = $zip->locateName('word/document.xml');
            if ($index !== false) {
                $xml = $zip->getFromIndex($index);
                $zip->close();

                $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml);
                $text = strip_tags($xml);
                return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
            $zip->close();
        }
        return '';
    }
}