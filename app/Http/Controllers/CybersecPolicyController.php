<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataConfig;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use App\Models\ComplianceStandard;
use App\Models\MetadataKey;
use App\Models\DataSourceRef;


class CybersecPolicyController extends Controller
{
    public function edit($id)
    {
        // 1. Get data config by ID (passed from blade)
        $dataConfig = DataConfig::findOrFail($id);

        // 2. Decode data_sources from JSON or use as single string
        $sourcesArray = is_array($dataConfig->data_sources)
            ? $dataConfig->data_sources
            : json_decode($dataConfig->data_sources, true);

        if (!is_array($sourcesArray)) {
            // If still not array, treat as single source
            $sourcesArray = [$dataConfig->data_sources];
        }

        // 3. Pick primary source (or handle multi-select if your UI supports)
        $primarySource = $sourcesArray[0];

        // 4. Find matching ref row in data_source_ref using data_source_name
        $refRow = DB::table('data_source_ref')
            ->where('data_source_name', $primarySource)
            ->first();

        $policySchema = $refRow ? json_decode($refRow->restriction_policy, true) : [];

        // 5. Existing policy set by user
        $currentPolicy = $dataConfig->restriction_policy
            ? json_decode($dataConfig->restriction_policy, true) : [];

        return view('cybersec_policy.edit', [
            'dataConfig'    => $dataConfig,
            'sources'       => $sourcesArray,
            'policySchema'  => $policySchema,
            'currentPolicy' => $currentPolicy,
            'primarySource' => $primarySource,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Like before: save selected policy as JSON
        $policy = $request->input('restriction_policy', []);
        $dataConfig = DataConfig::findOrFail($id);
        $dataConfig->restriction_policy = json_encode($policy);
        $dataConfig->save();

        // Optionally: Apply the policy to the external source
        try {
            $this->applyPolicies($dataConfig);
        } catch (\Exception $e) {
            return redirect()->route('cybersec_policy.edit', $id)
                ->with('error', 'Saved policy but failed to apply to data source: ' . $e->getMessage());
        }

        //return redirect()->route('dashboard')->with('success', 'Policy updated and applied.');
      	return redirect()->route('wizard.dashboard')->with('success', 'Policy updated and applied.');
    }

    protected function applyPolicies($dataConfig)
    {
        $cmd = escapeshellcmd('python3 ' . base_path('scripts/apply_policy.py') . ' ' . escapeshellarg($dataConfig->id));
        $output = shell_exec($cmd);
        // You might want to check $output for errors!
    }
}