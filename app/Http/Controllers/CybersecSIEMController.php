<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Auth;
use App\Models\ComplianceStandard;
use App\Models\MetadataKey;
use App\Models\DataSourceRef;
use Illuminate\Support\Facades\File;

class CybersecSIEMController extends Controller
{
    public function edit($id)
{
    $dataConfig = DataConfig::findOrFail($id);
    $sourcesArray = is_array($dataConfig->data_sources) ? $dataConfig->data_sources : json_decode($dataConfig->data_sources, true);
    if (!is_array($sourcesArray)) $sourcesArray = [$dataConfig->data_sources];

    $primarySource = $sourcesArray[0] ?? null;
    $dsRef = null;
    $siemDataMapping = [];

    if ($primarySource) {
        $dsRef = \DB::table('data_source_ref')
            ->where('data_source_name', $primarySource)
            ->first();
        if ($dsRef && $dsRef->siem_data_mapping) {
            $siemDataMapping = json_decode($dsRef->siem_data_mapping, true);
        }
    }

    // Get all siem_refs from DB
    $siemRefs = \DB::table('siem_refs')->get();
    // Build siemTypes array for dropdown
    $siemTypes = [];
    $siemProfiles = [];
    foreach ($siemRefs as $ref) {
        $siemTypes[$ref->id] = $ref->name . " ({$ref->format})";
        $siemProfiles[$ref->id] = [
            'format' => $ref->format,
            'template_field_map' => json_decode($ref->template_field_map, true)
        ];
    }

    // Detect current siem ref (if any)
    $currentProfile = $dataConfig->siem_export_profile ? json_decode($dataConfig->siem_export_profile, true) : [];
    $currentSiemRefId = $currentProfile['siem_ref_id'] ?? null;
    // Pick field map suggestion: (chosen SIEM → template), else fallbacks
    $suggestedFieldMap = [];
    if ($currentSiemRefId && isset($siemProfiles[$currentSiemRefId]['template_field_map'])) {
        $suggestedFieldMap = $siemProfiles[$currentSiemRefId]['template_field_map'];
    } elseif ($siemDataMapping) {
        $suggestedFieldMap = $siemDataMapping;
    } else {
        $suggestedFieldMap = [
            "file_path" => "filePath",
            "risk_level" => "risk",
            "compliance_tags" => "comp",
        ];
    }

    return view('cybersecai_siem.edit', [
        'dataConfig'        => $dataConfig,
        'sources'           => $sourcesArray,
        'siemTypes'         => $siemTypes, // dropdown: id => name
        'siemProfiles'      => $siemProfiles, // js var for JS use
        'suggestedFieldMap' => $suggestedFieldMap,
        'currentProfile'    => $currentProfile,
        'currentSiemRefId'  => $currentSiemRefId,
        'siemDataMapping'   => $siemDataMapping
    ]);
}

    public function update(Request $request, $id)
    {
        $dataConfig = DataConfig::findOrFail($id);
        $profile = $request->input('siem', []);
        $dataConfig->siem_export_profile = json_encode($profile);
        $dataConfig->save();
        return redirect()->route('wizard.dashboard')->with('success', 'SIEM Export Profile saved.');
    }

public function sample(Request $request, $id)
{
    // Always get from database
    $dataConfig = DataConfig::findOrFail($id);
    $profile = $dataConfig->siem_export_profile ? json_decode($dataConfig->siem_export_profile, true) : [];

    $map = $profile['field_map'] ?? [];
    if (is_string($map)) {
        $map = @json_decode($map, true);
    }
    if (!is_array($map)) $map = [];

    $format = strtolower($profile['format'] ?? 'json');

    if(empty($map)) {
        return response("Field mapping not provided or empty.", 400)
            ->header('Content-Type', 'text/plain');
    }

    // Build sample using all fields in map
    $sample = [];
    foreach($map as $src=>$dst) $sample[$dst] = "sample_" . $dst;
    $txt = '';

    switch($format) {
        case 'cef':
            $txt = "CEF:0|CybersecAI|Export|1.0|x|High Risk File|10|";
            foreach($sample as $k=>$v) $txt.="$k=$v ";
            break;
        case 'leef':
            $txt = "LEEF:2.0|CybersecAI|Export|1.0|highfile|";
            foreach($sample as $k=>$v) $txt.="$k=$v\t";
            break;
        case 'csv':
            $txt = implode(",",array_keys($sample))."\n".implode(",",$sample)."\n";
            break;
        default:
            $txt = json_encode($sample, JSON_PRETTY_PRINT);
    }

    return response($txt, 200, [
        'Content-Type' => 'text/plain',
        'Content-Disposition' => 'attachment;filename=siem_sample.txt'
    ]);
}
    
  
  public function test(Request $request, $id)
{
    $dataConfig = DataConfig::findOrFail($id);

    // 1. Get SIEM export config (posted or from saved config)
    $profile = $request->input('siem') ?? 
               ($dataConfig->siem_export_profile ? json_decode($dataConfig->siem_export_profile, true) : []);
    if (is_string($profile)) {
        $profile = json_decode($profile, true);
    }
    if (!$profile) {
        return back()->with('error', 'SIEM profile could not be loaded or decoded.');
    }

    // 2. Get current mapping list to create a test event using field_map (using user fields as sample values)
    $mapping = $profile['field_map'] ?? [];
    if (is_string($mapping)) {
        $mapping = json_decode($mapping, true);
    }
    $fileDetails = [];
    foreach($mapping as $src=>$dst) {
        $fileDetails[$src] = "test_{$dst}";
    }

    // 3. Create a safe work folder for this config (or use storage path)
    
    ////CHANGE THIS LATER - The test event is also appended to a file in storage/siem_test_output/
    $folder = storage_path('siem_test_output');
    if (!file_exists($folder)) {
        @mkdir($folder, 0775, true);
    }

    // 4. Build command
    // (Update below path if your python file has a different path!)
    $pythonScript = base_path('/home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/test_siem_connection.py'); // adjust as needed!
    $args = [
        escapeshellarg(json_encode($fileDetails)),
        escapeshellarg(json_encode($profile)),
        escapeshellarg($folder)
    ];
    $cmd = "python3 $pythonScript {$args[0]} {$args[1]} {$args[2]}";

    // 5. Execute and capture output
    try {
        $descriptorspec = [
            0 => ["pipe", "r"],   // stdin
            1 => ["pipe", "w"],   // stdout
            2 => ["pipe", "w"]    // stderr
        ];
        $process = proc_open($cmd, $descriptorspec, $pipes, null, null);
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $fullLog = trim($output . " " . $err);

            if (stripos($fullLog, 'error') !== false) {
                return back()->with('error', 'Failed to send test event: ' . $fullLog);
            } else {
                return back()->with('success', 'Test event sent: ' . $fullLog);
            }
        } else {
            return back()->with('error', 'Could not start SIEM Python tester.');
        }
    } catch (\Throwable $e) {
        return back()->with('error', 'Test script exception: '.$e->getMessage());
    }
}

    // Optionally: live export trigger for new high risk file; call Python script/queue here.
}
