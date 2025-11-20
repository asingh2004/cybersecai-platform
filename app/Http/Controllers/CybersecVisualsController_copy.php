<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\DataConfig;
use Illuminate\Support\Facades\Auth;
use App\Models\ComplianceStandard;
use App\Models\MetadataKey;
use App\Models\DataSourceRef;
use Illuminate\Support\Facades\File;

class CybersecVisualsController extends Controller
{
    /**
     * Show the main visuals page (initial render - charts via AJAX)
     */
    public function index()
    {
        // Fetch dropdown filter groups server-side for initial render
        [$filterGroups, $sourceLabels] = $this->prepareFilterDropdowns();
    		// You may need to fetch "allData" for table visuals too, if you want a risk pyramid.
    	// But for the current chart/visuals blade you only need filterGroups, sourceLabels:
   	 return view('cybersecai_visuals.visuals', [
        	// Do not json_encode!
        	'filterGroups' => $filterGroups,
        	'sourceLabels' => $sourceLabels,
    	]);
    }

    /**
     * AJAX endpoint for paged/filtered .json file data
     */
    public function apiFileData(Request $request)
    {
        [$allData, $sourceLabels] = $this->aggregateData($request->input('entity', null), $request->input('limit', 10000), $request->input('offset', 0));

        // Keep only required fields to avoid heavy browser loads.
        return response()->json([
            'data' => $allData,
            'sourceLabels' => $sourceLabels,
        ]);
    }
  
  
  

    /**
     * Prepare filters for Blade dropdowns: user-site groups, sources, etc.
     */

    
protected function prepareFilterDropdowns()
{
    $user = auth()->user();
    $configIDs = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/';
    $types = ['M365' => 'Microsoft 365', 'SMB' => 'SMB', 'S3' => 'AWS S3'];
    $sourcesFound = [];

    foreach ($configIDs as $id) {
        foreach ($types as $type => $label) {
            $graphPath = "{$basePath}{$type}/{$id}/graph/";
            if (is_dir($graphPath) && glob($graphPath . '*.json')) {
                $sourcesFound[$type] = $label;
            }
        }
    }

    // Add 'ALL' as the first option, always!
    $sourcesWithAll = array_merge(['ALL' => 'All Data'], $sourcesFound);

    $filterGroups = [[
        'label' => 'Data Sources',
        'values' => array_keys($sourcesWithAll)
    ]];
    $sourceLabels = $sourcesWithAll;

    return [$filterGroups, $sourceLabels];
}
  
    /**
     * Efficiently "stream" and chunk millions of JSON rows on demand for eCharts. 
     * Loads only data relevant to filtering or visual (row window)
     */
   protected function aggregateData($entity = null, $limit = 10000, $offset = 0)
{
    $user = auth()->user();

    \Log::info("[cybersecai] aggregateData for user_id [{$user->id}], entity=[".($entity ?? 'NULL')."], limit=$limit, offset=$offset");

    $configIDs = \App\Models\DataConfig::where('user_id', $user->id)->pluck('id')->toArray();
    $basePath = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/';
    $jsonFiles = [];

    foreach ($configIDs as $id) {
        foreach (['M365', 'SMB', 'S3'] as $type) {
            $graphPath = "{$basePath}{$type}/{$id}/graph/";
            if (is_dir($graphPath)) {
                foreach (glob($graphPath . '*.json') as $jsonFile) {
                    $jsonFiles[] = $jsonFile;
                }
            }
        }
    }

    \Log::info("[cybersecai] Found jsonFiles: " . count($jsonFiles));

    $data = [];
    $sourceLabels = [];
    $count = 0;
    $rowcount = 0;
    foreach ($jsonFiles as $file) {
        \Log::info("[cybersecai] Reading file: $file");
        $content = @file_get_contents($file);
        if ($content === false) {
            \Log::warning("Could not read: $file");
            continue;
        }
        $json = @json_decode($content, true);

        if (is_array($json) && isset($json[0]) && is_array($json[0])) {
            foreach ($json as &$record) {
                if (!is_array($record)) continue;
                $record['_datasource'] = $this->detectSourceFromPath($file);

                if (isset($record['user_id'])) {
                    $entityKey = 'User-' . $record['user_id'];
                } elseif (isset($record['site_id'])) {
                    $entityKey = 'Site-' . $record['site_id'];
                } elseif (!empty($record['_datasource'])) {
                    $entityKey = 'SRC_' . $record['_datasource'];
                    $sourceLabels[$entityKey] = ucfirst($record['_datasource']) . ' Files';
                } else {
                    $entityKey = '__UNASSIGNED__';
                }
                $record['_entity'] = $entityKey;

                // FILTER BY ENTITY
                if (!$entity || $entity == 'ALL' || $entityKey == $entity) {
                    if ($count++ < $offset) continue;
                    if (count($data) >= $limit) break; // Only break one foreach
                    $data[] = $record;
                    $rowcount++;
                    if ($rowcount <= 3) \Log::info("[cybersecai] Sample record: " . json_encode($record)); // log a few samples
                }
            }
            unset($record);
        } elseif (is_array($json)) {
            $json['_datasource'] = $this->detectSourceFromPath($file);
            if (isset($json['user_id'])) {
                $entityKey = 'User-' . $json['user_id'];
            } elseif (isset($json['site_id'])) {
                $entityKey = 'Site-' . $json['site_id'];
            } elseif (!empty($json['_datasource'])) {
                $entityKey = 'SRC_' . $json['_datasource'];
                $sourceLabels[$entityKey] = ucfirst($json['_datasource']) . ' Files';
            } else {
                $entityKey = '__UNASSIGNED__';
            }
            $json['_entity'] = $entityKey;

            if (!$entity || $entity == 'ALL' || $entityKey == $entity) {
                if ($count++ < $offset) continue;
                if (count($data) >= $limit) break;
                $data[] = $json;
                $rowcount++;
                if ($rowcount <= 3) \Log::info("[cybersecai] Sample record2: " . json_encode($json)); // log a few samples
            }
        } else {
            \Log::warning("Invalid or empty JSON in: $file");
        }
    }

    \Log::info("[cybersecai] aggregateData: Fetched " . count($data) . " records for user_id [{$user->id}]");
    return [$data, $sourceLabels];
}
    /**
     * Detect data source type from file path
     */
    protected function detectSourceFromPath($path)
    {
        if (stripos($path, '/SMB/') !== false) return 'SMB';
        if (stripos($path, '/M365/') !== false) return 'M365';
        if (stripos($path, '/S3/') !== false) return 'AWS S3';
        return 'unknown';
    }
}