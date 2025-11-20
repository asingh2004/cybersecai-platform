<?php

namespace App\Http\Controllers\FilesSummary;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FileSummariser extends Controller
{
    // Subquery: latest analysis per file (by max id)
    protected function latestAnalysisSub()
    {
        return DB::table('file_ai_analyses as a')
            ->select('a.file_id', 'a.overall_risk_rating')
            ->whereRaw('a.id = (SELECT MAX(id) FROM file_ai_analyses WHERE file_id = a.file_id)');
    }

    // Get visible data_config IDs for the authenticated user
    protected function getConfigIdsForUser($user): array
    {
        $ids = [];
        try {
            // Primary: use "user_id" as you specified
            $ids = DB::table('data_configs')->where('user_id', $user->id)->pluck('id')->toArray();
            Log::debug('FileSummariser:getConfigIdsForUser using user_id', [
                'user_id' => $user->id,
                'count' => count($ids),
            ]);

            // Fallback: try user_id if user_id not present/empty
            if (empty($ids)) {
                try {
                    $ids = DB::table('data_configs')->where('user_id', $user->id)->pluck('id')->toArray();
                    Log::debug('FileSummariser:getConfigIdsForUser fallback to user_id', [
                        'user_id' => $user->id,
                        'count' => count($ids),
                    ]);
                } catch (\Throwable $e2) {
                    Log::warning('FileSummariser:getConfigIdsForUser fallback query failed', [
                        'user_id' => $user->id,
                        'error' => $e2->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('FileSummariser:getConfigIdsForUser error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        Log::debug('FileSummariser:getConfigIdsForUser final', [
            'user_id' => $user->id,
            'config_ids' => $ids,
        ]);

        return $ids;
    }

    // Apply visibility by data_config IDs (files.user_id holds data_config.id)
    protected function applyConfigVisibility($builder, array $configIds)
    {
        if (empty($configIds)) {
            // Force no results if there are no configs visible for the user
            return $builder->whereRaw('1=0');
        }
        return $builder->whereIn('f.user_id', $configIds);
    }

    // Short signature for caches
    protected function configSignature(array $configIds): string
    {
        if (empty($configIds)) return 'none';
        return substr(sha1(implode(',', $configIds)), 0, 16);
    }

    // Snapshot logging of visibility and totals
    protected function logSnapshot($user, array $configIds, string $context)
    {
        try {
            $totFiles = DB::table('files')->count();
            $visible = DB::table('files as f')->whereIn('f.user_id', $configIds)->distinct('f.id')->count('f.id');
            $totAnalyses = DB::table('file_ai_analyses')->count();
            $analysesForVisible = DB::table('file_ai_analyses as a')
                ->join('files as f', 'f.id', '=', 'a.file_id')
                ->whereIn('f.user_id', $configIds)
                ->distinct('a.file_id')->count('a.file_id');

            Log::debug('FileSummariser:snapshot', [
                'context' => $context,
                'user_id' => $user->id,
                'config_ids_count' => count($configIds),
                'files_total' => $totFiles,
                'files_visible' => $visible,
                'analyses_total' => $totAnalyses,
                'analyses_for_visible' => $analysesForVisible,
            ]);
        } catch (\Throwable $e) {
            Log::error('FileSummariser:snapshot error', [
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
        }
    }

  
  	public function filesListPartial(Request $request)
    {
        // Reuse shared query builder, but render the compact partial view with just a table
        return $this->renderFilesList($request, 'filesummary._files_table');
    }
  
    protected function storageLabels(): array
    {
        return [
            'aws_s3'     => 'AWS S3',
            'smb'        => 'SMB',
            'onedrive'   => 'OneDrive',
            'sharepoint' => 'SharePoint',
        ];
    }

    // Compute storage aggregation (distinct file counts + size + risk buckets)
    protected function computeStorageAgg(array $configIds)
    {
        if (empty($configIds)) return collect();

        $latest = $this->latestAnalysisSub();

        $q = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id');

        $this->applyConfigVisibility($q, $configIds);

        $res = $q->groupBy('f.storage_type')
            ->selectRaw("
                f.storage_type,
                COUNT(DISTINCT f.id) AS total,
                SUM(f.size_bytes) AS total_size,
                SUM(CASE WHEN la.overall_risk_rating='High' THEN 1 ELSE 0 END) AS high_count,
                SUM(CASE WHEN la.overall_risk_rating='Medium' THEN 1 ELSE 0 END) AS medium_count,
                SUM(CASE WHEN la.overall_risk_rating='Low' THEN 1 ELSE 0 END) AS low_count,
                SUM(CASE WHEN la.overall_risk_rating='None' OR la.overall_risk_rating IS NULL THEN 1 ELSE 0 END) AS none_count
            ")
            ->get();

        Log::debug('FileSummariser:computeStorageAgg', [
            'config_ids_count' => count($configIds),
            'rows' => $res->count(),
            'sample' => $res->take(5)->toArray(),
        ]);

        return $res;
    }

    // Compute risk counts across visible files
    protected function computeRiskCounts(array $configIds): array
    {
        if (empty($configIds)) return ['High'=>0,'Medium'=>0,'Low'=>0,'None'=>0];

        $latest = $this->latestAnalysisSub();

        $q = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id');
        $this->applyConfigVisibility($q, $configIds);

        $rows = $q->groupBy('risk')
            ->selectRaw("COALESCE(la.overall_risk_rating,'None') AS risk, COUNT(DISTINCT f.id) AS cnt")
            ->get();

        $out = ['High'=>0,'Medium'=>0,'Low'=>0,'None'=>0];
        foreach ($rows as $r) $out[$r->risk] = (int) $r->cnt;

        Log::debug('FileSummariser:computeRiskCounts', ['data' => $out]);

        return $out;
    }

    // Compute risk counts by storage
    protected function computeRiskByStorage(array $configIds): array
    {
        if (empty($configIds)) return [];

        $latest = $this->latestAnalysisSub();

        $q = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id');
        $this->applyConfigVisibility($q, $configIds);

        $rows = $q->groupBy('f.storage_type', 'risk')
            ->selectRaw("f.storage_type, COALESCE(la.overall_risk_rating,'None') AS risk, COUNT(DISTINCT f.id) AS cnt")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (!isset($out[$r->storage_type])) {
                $out[$r->storage_type] = ['High'=>0,'Medium'=>0,'Low'=>0,'None'=>0];
            }
            $out[$r->storage_type][$r->risk] = (int) $r->cnt;
        }

        Log::debug('FileSummariser:computeRiskByStorage', [
            'storage_types' => array_keys($out),
            'sample' => array_slice($out, 0, 3),
        ]);

        return $out;
    }

    // Dashboard
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);

            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);
            Log::debug('FileSummariser:index start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'index');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:index cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            $donut = ['labels' => [], 'data' => [], 'keys' => []];
            foreach ($storageAgg as $row) {
                $donut['labels'][] = $storageLabels[$row->storage_type] ?? $row->storage_type;
                $donut['data'][]   = (int) ($row->total ?? 0);
                $donut['keys'][]  = $row->storage_type;
            }
            Log::debug('FileSummariser:index donut', ['labels' => $donut['labels'], 'total' => array_sum($donut['data'])]);

            return view('filesummary.index', compact('storageAgg', 'storageLabels', 'donut'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:index exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    // Risk Pyramid
    public function riskPyramid(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);

            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:riskPyramid start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'riskPyramid');

            // Risk counts
            $riskCountsKey = "filesummary:riskCounts:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:riskCounts cache', ['key' => $riskCountsKey, 'hit' => Cache::has($riskCountsKey)]);
            $riskCounts = Cache::remember($riskCountsKey, 60, fn() => $this->computeRiskCounts($configIds));

            // Risk by storage
            $byStorageKey = "filesummary:riskByStorage:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:riskByStorage cache', ['key' => $byStorageKey, 'hit' => Cache::has($byStorageKey)]);
            $byStorage = Cache::remember($byStorageKey, 60, fn() => $this->computeRiskByStorage($configIds));

            // Storage agg (reuse cards + donut)
            $storageAggKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:storageAgg (pyramid) cache', ['key' => $storageAggKey, 'hit' => Cache::has($storageAggKey)]);
            $storageAgg = Cache::remember($storageAggKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            $donut = ['labels' => [], 'data' => [], 'keys' => []];
            foreach ($storageAgg as $row) {
                $donut['labels'][] = $storageLabels[$row->storage_type] ?? $row->storage_type;
                $donut['data'][]   = (int) ($row->total ?? 0);
                $donut['keys'][]  = $row->storage_type;
            }
            $risksOrder = ['High','Medium','Low','None'];

            Log::debug('FileSummariser:riskPyramid final', [
                'riskCounts' => $riskCounts,
                'byStorage_keys' => array_keys($byStorage),
                'donut_total' => array_sum($donut['data']),
            ]);

            return view('filesummary.risk_pyramid', compact('riskCounts', 'byStorage', 'storageLabels', 'risksOrder', 'storageAgg', 'donut'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:riskPyramid exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    // Advanced table view
   public function table(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user) abort(401);

        $configIds = $this->getConfigIdsForUser($user);
        $cfgSig = $this->configSignature($configIds);

        $storage = $request->get('storage');
        $risk    = $request->get('risk');
        $q       = trim($request->get('q', ''));
        $ext     = trim($request->get('ext', ''));
        $sort    = $request->get('sort', 'modified');
        $dir     = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int)$request->get('per_page', 50), 10), 200);

        // New permission filters
        $role            = $request->get('role');
        $principal       = trim($request->get('principal', ''));
        $principalType   = $request->get('principal_type');

        Log::debug('FileSummariser:table start', [
            'user_id' => $user->id,
            'cfg_sig' => $cfgSig,
            'filters' => compact('storage', 'risk', 'q', 'ext', 'sort', 'dir', 'perPage','role','principal','principalType'),
        ]);

        $latest = $this->latestAnalysisSub();

        $query = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id')
            ->leftJoin('s3_files as s3', 's3.file_id', '=', 'f.id')
            ->leftJoin('smb_files as smb', 'smb.file_id', '=', 'f.id')
            ->leftJoin('onedrive_files as od', 'od.file_id', '=', 'f.id')
            ->leftJoin('sharepoint_files as sp', 'sp.file_id', '=', 'f.id');

        $this->applyConfigVisibility($query, $configIds);

        if ($storage) $query->where('f.storage_type', $storage);

        if ($risk) {
            if ($risk === 'None') {
                $query->where(function($w){
                    $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating', 'None');
                });
            } else {
                $query->where('la.overall_risk_rating', $risk);
            }
        }

        if ($q !== '') $query->where('f.file_name', 'like', '%'.$q.'%');
        if ($ext !== '') {
            $normExt = ltrim(strtolower($ext), '.');
            $query->whereRaw('LOWER(f.file_extension) = ?', [$normExt]);
        }

        // Permission filters via EXISTS
        if ($role || $principal !== '' || $principalType) {
            $query->whereExists(function($w) use ($role, $principal, $principalType) {
                $w->from('file_permissions as fp')->whereColumn('fp.file_id', 'f.id');
                if ($role) $w->where('fp.role', $role);
                if ($principal !== '') {
                    $w->where(function($wh) use ($principal) {
                        $wh->where('fp.principal_email', 'like', '%'.$principal.'%')
                           ->orWhere('fp.principal_display_name', 'like', '%'.$principal.'%');
                    });
                }
                if ($principalType) $w->where('fp.principal_type', $principalType);
            });
        }

        switch ($sort) {
            case 'name': $query->orderBy('f.file_name', $dir); break;
            case 'size': $query->orderBy('f.size_bytes', $dir); break;
            case 'risk': $query->orderBy('la.overall_risk_rating', $dir); break;
            case 'modified':
            default:     $query->orderBy('f.last_modified', $dir); break;
        }

        $countQuery = clone $query;
        $estimatedTotal = $countQuery->distinct('f.id')->count('f.id');
        Log::debug('FileSummariser:table estimated total', ['estimated_distinct_files' => $estimatedTotal]);

        $files = $query->select([
                'f.id','f.file_name','f.storage_type','f.size_bytes','f.last_modified','f.file_extension',
                DB::raw("COALESCE(la.overall_risk_rating,'None') as risk"),
                DB::raw("
                    CASE f.storage_type
                        WHEN 'onedrive'   THEN od.parent_reference
                        WHEN 'sharepoint' THEN sp.parent_reference
                        WHEN 'smb'        THEN COALESCE(smb.full_path, CONCAT('//', COALESCE(smb.server,''),'/',COALESCE(smb.share,''),'/',COALESCE(smb.file_path,'')))
                        WHEN 'aws_s3'     THEN COALESCE(s3.full_path, CONCAT(COALESCE(s3.bucket,''),'/',COALESCE(s3.s3_key,'')))
                        ELSE NULL
                    END as location
                "),
                DB::raw("
                    CASE f.storage_type
                        WHEN 'onedrive' THEN od.owner_display_name
                        ELSE NULL
                    END as owner_name
                "),
                DB::raw("
                    CASE f.storage_type
                        WHEN 'onedrive' THEN od.owner_email
                        ELSE NULL
                    END as owner_email
                "),
                DB::raw("
                    CASE f.storage_type
                        WHEN 'sharepoint' THEN sp.site_id
                        ELSE NULL
                    END as site_id
                "),
                DB::raw("COALESCE(od.web_url, sp.web_url, f.web_url) as web_url"),
                DB::raw("COALESCE(od.download_url, sp.download_url, f.download_url) as download_url"),
                DB::raw("(SELECT COUNT(*) FROM file_permissions fp WHERE fp.file_id = f.id) as perm_count"),
            ])
            ->distinct('f.id')
            ->paginate($perPage)
            ->appends($request->query());

        Log::debug('FileSummariser:table page', [
            'returned' => $files->count(),
            'current_page' => $files->currentPage(),
            'last_page' => $files->lastPage(),
        ]);

        $storageLabels = $this->storageLabels();

        return view('filesummary.table', compact('files','storageLabels','storage','risk','q','ext','sort','dir','perPage','role','principal','principalType'));
    } catch (\Throwable $e) {
        Log::error('FileSummariser:table exception', ['error' => $e->getMessage()]);
        abort(500, 'An error occurred. Check logs.');
    }
}

    // Drilldown list
    public function filesList(Request $request)
    {
        return $this->renderFilesList($request, 'filesummary.files_list');
    }

    protected function renderFilesList(Request $request, string $view)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);

            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            $storage = $request->get('storage');
            $risk    = $request->get('risk');
            $q       = trim($request->get('q', ''));
            $sort    = $request->get('sort', 'modified');
            $dir     = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $perPage = min(max((int)$request->get('per_page', 50), 10), 200);

            // New optional filters for permissions
            $role            = $request->get('role'); // owner/read/write/etc.
            $principal       = trim($request->get('principal', '')); // email or display name match
            $principalType   = $request->get('principal_type'); // user/group/siteGroup/etc.

            Log::debug('FileSummariser:filesList start', [
                'user_id' => $user->id,
                'cfg_sig' => $cfgSig,
                'filters' => compact('storage','risk','q','sort','dir','perPage','role','principal','principalType'),
            ]);

            $latest = $this->latestAnalysisSub();

            $query = DB::table('files as f')
                ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id')

                // Provider-specific joins to expose location/owner/site/etc.
                ->leftJoin('s3_files as s3', 's3.file_id', '=', 'f.id')
                ->leftJoin('smb_files as smb', 'smb.file_id', '=', 'f.id')
                ->leftJoin('onedrive_files as od', 'od.file_id', '=', 'f.id')
                ->leftJoin('sharepoint_files as sp', 'sp.file_id', '=', 'f.id');

            $this->applyConfigVisibility($query, $configIds);

            if ($storage) $query->where('f.storage_type', $storage);

            if ($risk) {
                if ($risk === 'None') {
                    $query->where(function($w){
                        $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating', 'None');
                    });
                } else {
                    $query->where('la.overall_risk_rating', $risk);
                }
            }

            if ($q !== '') $query->where('f.file_name', 'like', '%'.$q.'%');

            // Permission filters without joining file_permissions (avoid duplication)
            if ($role || $principal !== '' || $principalType) {
                $query->whereExists(function($w) use ($role, $principal, $principalType) {
                    $w->from('file_permissions as fp')->whereColumn('fp.file_id', 'f.id');
                    if ($role) $w->where('fp.role', $role);
                    if ($principal !== '') {
                        $w->where(function($wh) use ($principal) {
                            $wh->where('fp.principal_email', 'like', '%'.$principal.'%')
                               ->orWhere('fp.principal_display_name', 'like', '%'.$principal.'%');
                        });
                    }
                    if ($principalType) $w->where('fp.principal_type', $principalType);
                });
            }

            switch ($sort) {
                case 'name':    $query->orderBy('f.file_name', $dir); break;
                case 'size':    $query->orderBy('f.size_bytes', $dir); break;
                case 'risk':    $query->orderBy('la.overall_risk_rating', $dir); break;
                case 'modified':
                default:        $query->orderBy('f.last_modified', $dir); break;
            }

            $countQuery = clone $query;
            $estimatedTotal = $countQuery->distinct('f.id')->count('f.id');
            Log::debug('FileSummariser:filesList estimated total', ['estimated_distinct_files' => $estimatedTotal]);

            $files = $query->select([
                    'f.id','f.file_name','f.storage_type','f.size_bytes','f.last_modified','f.file_extension',
                    DB::raw("COALESCE(la.overall_risk_rating,'None') as risk"),

                    // Unified location/owner/site fields
                    DB::raw("
                        CASE f.storage_type
                            WHEN 'onedrive'   THEN od.parent_reference
                            WHEN 'sharepoint' THEN sp.parent_reference
                            WHEN 'smb'        THEN COALESCE(smb.full_path, CONCAT('//', COALESCE(smb.server,''),'/',COALESCE(smb.share,''),'/',COALESCE(smb.file_path,'')))
                            WHEN 'aws_s3'     THEN COALESCE(s3.full_path, CONCAT(COALESCE(s3.bucket,''),'/',COALESCE(s3.s3_key,'')))
                            ELSE NULL
                        END as location
                    "),
                    DB::raw("
                        CASE f.storage_type
                            WHEN 'onedrive' THEN od.owner_display_name
                            ELSE NULL
                        END as owner_name
                    "),
                    DB::raw("
                        CASE f.storage_type
                            WHEN 'onedrive' THEN od.owner_email
                            ELSE NULL
                        END as owner_email
                    "),
                    DB::raw("
                        CASE f.storage_type
                            WHEN 'sharepoint' THEN sp.site_id
                            ELSE NULL
                        END as site_id
                    "),
                    DB::raw("COALESCE(od.web_url, sp.web_url, f.web_url) as web_url"),
                    DB::raw("COALESCE(od.download_url, sp.download_url, f.download_url) as download_url"),
                    DB::raw("(SELECT COUNT(*) FROM file_permissions fp WHERE fp.file_id = f.id) as perm_count"),
                ])
                ->distinct('f.id')
                ->paginate($perPage)
                ->appends($request->query());

            Log::debug('FileSummariser:filesList page', [
                'returned' => $files->count(),
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
            ]);

            $storageLabels = $this->storageLabels();

            return view($view, compact('files','storageLabels','storage','risk','q','sort','dir','perPage','role','principal','principalType'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:filesList exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function duplicates(Request $request)
{
    try {
        $user = Auth::user(); if (!$user) abort(401);
        $configIds = $this->getConfigIdsForUser($user);
        $latest = $this->latestAnalysisSub();

        // Filters
        $q              = trim((string)$request->get('q', ''));
        $ext            = trim((string)$request->get('ext', ''));
        $risk           = trim((string)$request->get('risk', '')); // High/Medium/Low/None
        $storagesParam  = $request->get('storage', []);
        $storages       = is_array($storagesParam) ? array_values(array_filter($storagesParam)) : (empty($storagesParam) ? [] : [$storagesParam]);
        $dateFrom       = $request->get('date_from'); // yyyy-mm-dd
        $dateTo         = $request->get('date_to');   // yyyy-mm-dd
        $minCopies      = $request->get('min_copies');           // int or null
        $minStorages    = $request->get('min_storages');         // int or null
        $sort           = $request->get('sort', 'copies_desc');  // copies_desc|storages_desc|size_desc|size_asc|name_asc|name_desc
        $perPage        = min(max((int)$request->get('per_page', 50), 10), 200);

        $qBuilder = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id');

        $this->applyConfigVisibility($qBuilder, $configIds);

        if (!empty($storages)) $qBuilder->whereIn('f.storage_type', $storages);

        if ($risk !== '') {
            if ($risk === 'None') {
                $qBuilder->where(function($w){
                    $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating', 'None');
                });
            } else {
                $qBuilder->where('la.overall_risk_rating', $risk);
            }
        }

        if ($q !== '')   $qBuilder->where('f.file_name', 'like', '%'.$q.'%');
        if ($ext !== '') {
            $normExt = ltrim(strtolower($ext), '.');
            $qBuilder->whereRaw('LOWER(f.file_extension) = ?', [$normExt]);
        }

        if ($dateFrom) $qBuilder->where('f.last_modified', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        if ($dateTo)   $qBuilder->where('f.last_modified', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());

        $qBuilder->select([
            'f.file_name',
            'f.size_bytes',
            DB::raw('COUNT(*) as cnt'),
            DB::raw('COUNT(DISTINCT f.storage_type) as storages'),
        ])->groupBy('f.file_name', 'f.size_bytes');

        if ($minCopies || $minStorages) {
            if ($minCopies)   $qBuilder->havingRaw('COUNT(*) >= ?', [(int)$minCopies]);
            if ($minStorages) $qBuilder->havingRaw('COUNT(DISTINCT f.storage_type) >= ?', [(int)$minStorages]);
        } else {
            $qBuilder->havingRaw('COUNT(*) > 1 OR COUNT(DISTINCT f.storage_type) > 1');
        }

        switch ($sort) {
            case 'storages_desc': $qBuilder->orderBy('storages', 'desc')->orderBy('cnt','desc'); break;
            case 'size_desc':     $qBuilder->orderBy('f.size_bytes', 'desc'); break;
            case 'size_asc':      $qBuilder->orderBy('f.size_bytes', 'asc'); break;
            case 'name_asc':      $qBuilder->orderBy('f.file_name', 'asc'); break;
            case 'name_desc':     $qBuilder->orderBy('f.file_name', 'desc'); break;
            case 'copies_desc':
            default:              $qBuilder->orderBy('cnt', 'desc')->orderBy('storages','desc'); break;
        }

        $groups = $qBuilder->paginate($perPage)->appends($request->query());
        $storageLabels = $this->storageLabels();

        return view('filesummary.duplicates', compact(
            'groups', 'storageLabels', 'q', 'ext', 'risk', 'storages', 'dateFrom', 'dateTo', 'minCopies', 'minStorages', 'sort', 'perPage'
        ));
    } catch (\Throwable $e) {
        Log::error('duplicates error', ['e'=>$e->getMessage()]);
        abort(500, 'An error occurred.');
    }
}

public function duplicatesCsv(Request $request)
{
    try {
        $user = Auth::user(); if (!$user) abort(401);
        $configIds = $this->getConfigIdsForUser($user);
        $latest = $this->latestAnalysisSub();

        $q        = trim((string)$request->get('q', ''));
        $ext      = trim((string)$request->get('ext', ''));
        $risk     = trim((string)$request->get('risk', ''));
        $storagesParam = $request->get('storage', []);
        $storages = is_array($storagesParam) ? array_values(array_filter($storagesParam)) : (empty($storagesParam) ? [] : [$storagesParam]);
        $dateFrom = $request->get('date_from');
        $dateTo   = $request->get('date_to');
        $minCopies   = $request->get('min_copies');
        $minStorages = $request->get('min_storages');
        $sort     = $request->get('sort', 'copies_desc');

        $base = DB::table('files as f')->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id');
        $this->applyConfigVisibility($base, $configIds);

        if (!empty($storages)) $base->whereIn('f.storage_type', $storages);

        if ($risk !== '') {
            if ($risk === 'None') {
                $base->where(function($w){
                    $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating','None');
                });
            } else {
                $base->where('la.overall_risk_rating', $risk);
            }
        }

        if ($q !== '')   $base->where('f.file_name', 'like', '%'.$q.'%');
        if ($ext !== '') {
            $normExt = ltrim(strtolower($ext), '.');
            $base->whereRaw('LOWER(f.file_extension) = ?', [$normExt]);
        }
        if ($dateFrom) $base->where('f.last_modified', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        if ($dateTo)   $base->where('f.last_modified', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());

        $base->select([
            'f.file_name',
            'f.size_bytes',
            DB::raw('COUNT(*) as cnt'),
            DB::raw('COUNT(DISTINCT f.storage_type) as storages'),
        ])->groupBy('f.file_name', 'f.size_bytes');

        if ($minCopies || $minStorages) {
            if ($minCopies)   $base->havingRaw('COUNT(*) >= ?', [(int)$minCopies]);
            if ($minStorages) $base->havingRaw('COUNT(DISTINCT f.storage_type) >= ?', [(int)$minStorages]);
        } else {
            $base->havingRaw('COUNT(*) > 1 OR COUNT(DISTINCT f.storage_type) > 1');
        }

        switch ($sort) {
            case 'storages_desc': $base->orderBy('storages', 'desc')->orderBy('cnt','desc'); break;
            case 'size_desc':     $base->orderBy('f.size_bytes', 'desc'); break;
            case 'size_asc':      $base->orderBy('f.size_bytes', 'asc'); break;
            case 'name_asc':      $base->orderBy('f.file_name', 'asc'); break;
            case 'name_desc':     $base->orderBy('f.file_name', 'desc'); break;
            case 'copies_desc':
            default:              $base->orderBy('cnt', 'desc')->orderBy('storages','desc'); break;
        }

        $rows = $base->limit(200000)->get(); // safety cap

        $callback = function() use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['file_name', 'size_bytes', 'size_human', 'copies', 'distinct_storages']);
            foreach ($rows as $r) {
                $size = (int)$r->size_bytes;
                $units = ['B','KB','MB','GB','TB','PB'];
                $i = $size > 0 ? (int)floor(log($size, 1024)) : 0; $i = min($i, count($units)-1);
                $human = $size > 0 ? round($size/pow(1024, $i), 2).' '.$units[$i] : '0 B';
                fputcsv($out, [$r->file_name, $size, $human, (int)$r->cnt, (int)$r->storages]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'duplicates.csv', [
            'Content-Type' => 'text/csv'
        ]);
    } catch (\Throwable $e) {
        Log::error('duplicatesCsv error', ['e'=>$e->getMessage()]);
        abort(500, 'CSV export failed.');
    }
}

public function duplicatesGroup(Request $request)
{
    try {
        $user = Auth::user(); if (!$user) abort(401);
        $configIds = $this->getConfigIdsForUser($user);
        $latest = $this->latestAnalysisSub();

        $name   = (string)$request->get('file_name');
        $size   = (int)$request->get('size_bytes');

        // Filters for instances view
        $storagesParam = $request->get('storage', []);
        $storages      = is_array($storagesParam) ? array_values(array_filter($storagesParam)) : (empty($storagesParam) ? [] : [$storagesParam]);
        $risk          = trim((string)$request->get('risk', ''));
        $dateFrom      = $request->get('date_from');
        $dateTo        = $request->get('date_to');
        $p             = trim((string)$request->get('q', '')); // search within location/path
        $sort          = $request->get('sort', 'modified_desc'); // modified_desc|modified_asc|storage_asc

        $qBuilder = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id')
            ->leftJoin('s3_files as s3', 's3.file_id', '=', 'f.id')
            ->leftJoin('smb_files as smb', 'smb.file_id', '=', 'f.id')
            ->leftJoin('onedrive_files as od', 'od.file_id', '=', 'f.id')
            ->leftJoin('sharepoint_files as sp', 'sp.file_id', '=', 'f.id');

        $this->applyConfigVisibility($qBuilder, $configIds);

        $qBuilder->where('f.file_name', $name)->where('f.size_bytes', $size);

        if (!empty($storages)) $qBuilder->whereIn('f.storage_type', $storages);

        if ($risk !== '') {
            if ($risk === 'None') {
                $qBuilder->where(function($w){
                    $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating', 'None');
                });
            } else {
                $qBuilder->where('la.overall_risk_rating', $risk);
            }
        }

        if ($dateFrom) $qBuilder->where('f.last_modified', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        if ($dateTo)   $qBuilder->where('f.last_modified', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());

        if ($p !== '') {
            $like = '%'.$p.'%';
            $qBuilder->where(function($w) use ($like) {
                $w->where('s3.full_path', 'like', $like)
                  ->orWhere('smb.full_path', 'like', $like)
                  ->orWhere('smb.file_path', 'like', $like)
                  ->orWhere('od.parent_reference', 'like', $like)
                  ->orWhere('sp.parent_reference', 'like', $like);
            });
        }

        switch ($sort) {
            case 'modified_asc':  $qBuilder->orderBy('f.last_modified', 'asc'); break;
            case 'storage_asc':   $qBuilder->orderBy('f.storage_type', 'asc')->orderBy('f.last_modified','desc'); break;
            case 'modified_desc':
            default:              $qBuilder->orderBy('f.last_modified', 'desc'); break;
        }

        $files = $qBuilder->select([
            'f.id','f.file_name','f.storage_type','f.size_bytes','f.last_modified','f.file_extension',
            DB::raw("COALESCE(la.overall_risk_rating,'None') as risk"),
            DB::raw("
                CASE f.storage_type
                    WHEN 'onedrive'   THEN od.parent_reference
                    WHEN 'sharepoint' THEN sp.parent_reference
                    WHEN 'smb'        THEN COALESCE(smb.full_path, CONCAT('//', COALESCE(smb.server,''),'/',COALESCE(smb.share,''),'/',COALESCE(smb.file_path,'')))
                    WHEN 'aws_s3'     THEN COALESCE(s3.full_path, CONCAT(COALESCE(s3.bucket,''),'/',COALESCE(s3.s3_key,'')))
                    ELSE NULL
                END as location
            "),
            DB::raw("COALESCE(od.web_url, sp.web_url, f.web_url) as web_url"),
        ])->get();

        $storageLabels = $this->storageLabels();

        return view('filesummary.duplicates_group', [
            'files' => $files,
            'name' => $name,
            'size' => $size,
            'storageLabels' => $storageLabels,
            'storages' => $storages,
            'risk' => $risk,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'p' => $p,
            'sort' => $sort,
        ]);
    } catch (\Throwable $e) {
        Log::error('duplicatesGroup error', ['e'=>$e->getMessage()]);
        abort(500, 'An error occurred.');
    }
}

public function duplicatesGroupCsv(Request $request)
{
    try {
        $user = Auth::user(); if (!$user) abort(401);
        $configIds = $this->getConfigIdsForUser($user);
        $latest = $this->latestAnalysisSub();

        $name = (string)$request->get('file_name');
        $size = (int)$request->get('size_bytes');

        $storagesParam = $request->get('storage', []);
        $storages      = is_array($storagesParam) ? array_values(array_filter($storagesParam)) : (empty($storagesParam) ? [] : [$storagesParam]);
        $risk          = trim((string)$request->get('risk', ''));
        $dateFrom      = $request->get('date_from');
        $dateTo        = $request->get('date_to');
        $p             = trim((string)$request->get('q', ''));
        $sort          = $request->get('sort', 'modified_desc');

        $qBuilder = DB::table('files as f')
            ->leftJoinSub($latest, 'la', 'la.file_id', '=', 'f.id')
            ->leftJoin('s3_files as s3', 's3.file_id', '=', 'f.id')
            ->leftJoin('smb_files as smb', 'smb.file_id', '=', 'f.id')
            ->leftJoin('onedrive_files as od', 'od.file_id', '=', 'f.id')
            ->leftJoin('sharepoint_files as sp', 'sp.file_id', '=', 'f.id');

        $this->applyConfigVisibility($qBuilder, $configIds);

        $qBuilder->where('f.file_name', $name)->where('f.size_bytes', $size);

        if (!empty($storages)) $qBuilder->whereIn('f.storage_type', $storages);

        if ($risk !== '') {
            if ($risk === 'None') {
                $qBuilder->where(function($w){
                    $w->whereNull('la.overall_risk_rating')->orWhere('la.overall_risk_rating', 'None');
                });
            } else {
                $qBuilder->where('la.overall_risk_rating', $risk);
            }
        }
        if ($dateFrom) $qBuilder->where('f.last_modified', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
        if ($dateTo)   $qBuilder->where('f.last_modified', '<=', \Carbon\Carbon::parse($dateTo)->endOfDay());

        if ($p !== '') {
            $like = '%'.$p.'%';
            $qBuilder->where(function($w) use ($like) {
                $w->where('s3.full_path', 'like', $like)
                  ->orWhere('smb.full_path', 'like', $like)
                  ->orWhere('smb.file_path', 'like', $like)
                  ->orWhere('od.parent_reference', 'like', $like)
                  ->orWhere('sp.parent_reference', 'like', $like);
            });
        }

        switch ($sort) {
            case 'modified_asc':  $qBuilder->orderBy('f.last_modified', 'asc'); break;
            case 'storage_asc':   $qBuilder->orderBy('f.storage_type', 'asc')->orderBy('f.last_modified','desc'); break;
            case 'modified_desc':
            default:              $qBuilder->orderBy('f.last_modified', 'desc'); break;
        }

        $rows = $qBuilder->select([
            'f.id','f.file_name','f.storage_type','f.size_bytes','f.last_modified','f.file_extension',
            DB::raw("COALESCE(la.overall_risk_rating,'None') as risk"),
            DB::raw("
                CASE f.storage_type
                    WHEN 'onedrive'   THEN od.parent_reference
                    WHEN 'sharepoint' THEN sp.parent_reference
                    WHEN 'smb'        THEN COALESCE(smb.full_path, CONCAT('//', COALESCE(smb.server,''),'/',COALESCE(smb.share,''),'/',COALESCE(smb.file_path,'')))
                    WHEN 'aws_s3'     THEN COALESCE(s3.full_path, CONCAT(COALESCE(s3.bucket,''),'/',COALESCE(s3.s3_key,'')))
                    ELSE NULL
                END as location
            "),
            DB::raw("COALESCE(od.web_url, sp.web_url, f.web_url) as web_url"),
        ])->limit(200000)->get();

        $storageLabels = $this->storageLabels();

        $callback = function() use ($rows, $storageLabels) {
            $out = fopen('php://output','w');
            fputcsv($out, ['file_name','storage','location','risk','last_modified','size_bytes','web_url']);
            foreach ($rows as $r) {
                $lbl = $storageLabels[$r->storage_type] ?? $r->storage_type;
                fputcsv($out, [
                    $r->file_name,
                    $lbl,
                    $r->location,
                    $r->risk,
                    $r->last_modified,
                    (int)$r->size_bytes,
                    $r->web_url,
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'duplicate_group.csv', ['Content-Type' => 'text/csv']);
    } catch (\Throwable $e) {
        Log::error('duplicatesGroupCsv error', ['e'=>$e->getMessage()]);
        abort(500, 'CSV export failed.');
    }
}
  
    // File detail
    public function fileDetail(File $file)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);

            $configIds = $this->getConfigIdsForUser($user);
            $allowed = in_array((int)$file->user_id, $configIds, true);

            if (!$allowed) {
                Log::warning('FileSummariser:fileDetail forbidden', [
                    'user_id' => $user->id,
                    'file_id' => $file->id,
                    'file_user_id_as_config_id' => $file->user_id,
                    'config_ids' => $configIds,
                ]);
                abort(403);
            }

            $file->load([
                'latestAiAnalysis.findings.detectedFields',
                'latestAiAnalysis.controls',
            ]);

            Log::debug('FileSummariser:fileDetail loaded', [
                'file_id' => $file->id,
                'config_id' => $file->user_id,
                'latest_risk' => optional($file->latestAiAnalysis)->overall_risk_rating,
            ]);

            $storageLabels = $this->storageLabels();

            return view('filesummary.file_detail', compact('file','storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:fileDetail exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    // ============ New Chart Pages ============

    public function treemap(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:treemap start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'treemap');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:treemap cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.treemap', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:treemap exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function sunburst(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:sunburst start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'sunburst');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:sunburst cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.sunburst', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:sunburst exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function stackedBar(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:stackedBar start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'stackedBar');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:stackedBar cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.stacked_bar', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:stackedBar exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function heatmap(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:heatmap start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'heatmap');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:heatmap cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.heatmap', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:heatmap exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function bubble(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:bubble start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'bubble');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:bubble cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.bubble', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:bubble exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }

    public function sankey(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) abort(401);
            $configIds = $this->getConfigIdsForUser($user);
            $cfgSig = $this->configSignature($configIds);

            Log::debug('FileSummariser:sankey start', ['user_id' => $user->id, 'cfg_sig' => $cfgSig]);
            $this->logSnapshot($user, $configIds, 'sankey');

            $cacheKey = "filesummary:storageAgg:{$user->id}:{$cfgSig}";
            Log::debug('FileSummariser:sankey cache check', ['key' => $cacheKey, 'hit' => Cache::has($cacheKey)]);
            $storageAgg = Cache::remember($cacheKey, 60, fn() => $this->computeStorageAgg($configIds));

            $storageLabels = $this->storageLabels();

            return view('filesummary.sankey', compact('storageAgg', 'storageLabels'));
        } catch (\Throwable $e) {
            Log::error('FileSummariser:sankey exception', ['error' => $e->getMessage()]);
            abort(500, 'An error occurred. Check logs.');
        }
    }
}