<?php

namespace App\Http\Controllers\AgenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class AiComplianceBotController extends Controller
{
    public function scan(Request $request)
    {
       $request->validate([
    	'file' => 'required|mimes:txt,csv,doc,docx,pdf,json,xls,xlsx,xml|max:16384'
		]);
        $tempPath = $request->file('file')->storeAs('ai_scans', uniqid().'_'.$request->file('file')->getClientOriginalName());
        $localPath = storage_path('app/' . $tempPath);

        // Path to your python script (see below)
        $pyScript = base_path('webhook/freemium_scan_file.py');
        $pyApiKey = env('OPENAI_API_KEY'); // set this in your .env
        $cli = ['python3', $pyScript, $localPath, $pyApiKey];

        try {
            $process = new Process($cli);
            $process->setTimeout(90);
            $process->mustRun();
            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (!$result || !isset($result['summary'])) {
                throw new \Exception('AI did not return a valid result.');
            }

            $highlight = "<b>Detected Regulation:</b> ".e($result['regulation'] ?? 'Unknown')."<br><b>Risk:</b> ".e($result['risk'])."<br>";
            $list = $result['fields'] ? "<ul style='text-align:left'>" . collect($result['fields'])->map(fn($f)=>"<li>".e($f)."</li>")->join('') . "</ul>" : "";
            $summary = $highlight . "<b>AI Report:</b><br>" . e($result['summary']) . $list;

            return back()->with('ai_scan_summary', $summary);
        } catch (\Exception $ex) {
            Log::error('AI Compliance Scan error: '.$ex->getMessage());
            return back()->with('ai_scan_error', 'Sorry, scan failed. Please try again or contact us for a demo.');
        }
    }
}