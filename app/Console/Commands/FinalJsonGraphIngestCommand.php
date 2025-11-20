<?php

namespace App\Console\Commands;


/**

- Traverses all webhook/{Storage}/{user_id}/graph/*.json files
- Supports mixed M365 JSONs (OneDrive and SharePoint records together in the same M365/graph folder)
- Performs delta processing per record (only updates when fields/permissions/LLM changed)
- Soft-deletes missing records (mark deleted) per user_id + storage_type
Notes
- Mixed M365: Each record is inspected; SharePoint items (site_id + drive_id) go to sharepoint tables; all other M365 items go to onedrive tables.
- Deltas: The command
  - Skips writing file metadata unless values changed.
  - Only rewrites permissions when permissions_json changed.
  - Only rewrites LLM children when raw_json changed.
  - Still records “seen” for deletion reconciliation without re-writing unchanged rows.
- Deletion: After the run, any DB file not “seen” in the current JSON input for the same user_id + storage_type is soft-deleted (deleted_at set).
- Ensure File model uses SoftDeletes and files table has deleted_at.
**/

use App\Models\File;
use App\Models\S3File;
use App\Models\SMBFile;
use App\Models\OneDriveFile;
use App\Models\SharePointFile;
use App\Models\FilePermission;
use App\Models\FileAIAnalysis;
use App\Models\FileAIFinding;
use App\Models\FileAIFindingDetectedField;
use App\Models\FileAIControl;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FinalJsonGraphIngestCommand extends Command
{
    protected $signature = 'FinalJsonGraphIngestCommand
        {--basePath=/home/cybersecai/htdocs/www.cybersecai.io/webhook}
        {--onlyStorage= : Optional filter (M365|S3|SMB)}
        {--onlyUser=* : Optional filter one or more user_ids}
        {--dry-run : Parse and log only, no DB writes}
        {--no-cache : Disable file fingerprint cache and process all JSON files}';

    protected $description = 'Traverse graph folders, read *.json, and upsert file metadata, permissions, and LLM analysis. Soft-delete missing records for processed users/storage pairs.';

  
    // Track seen files per (user_id, storage_type) to soft-delete absent ones after ingest
    protected array $seen = []; // [ "user|storage_type" => [source_file_id => true] ]

    public function handle(): int
    {
        $basePathOption = $this->option('basePath');
        $basePath = $this->normalizeBasePath($basePathOption);

        $this->info("Ingest starting. Base path: {$basePath}");

        // Pattern: {basePath}/{Storage}/{user_id}/graph/*.json
        $pattern = rtrim($basePath, '/').'/*/*/graph/*.json';
        $jsonFiles = glob($pattern, GLOB_NOSORT);

        if (!$jsonFiles) {
            $this->info('No JSON files found.');
            return self::SUCCESS;
        }

        $onlyStorage = $this->option('onlyStorage');
        $onlyUsersOpt = $this->option('onlyUser');
        $onlyUsers = [];
        if (!empty($onlyUsersOpt)) {
            $onlyUsers = is_array($onlyUsersOpt) ? array_map('intval', $onlyUsersOpt) : [ (int)$onlyUsersOpt ];
        }
        $useCache = !$this->option('no-cache');

        foreach ($jsonFiles as $jsonFile) {
            $jsonFile = str_replace('\\', '/', $jsonFile);

            // Derive storage group and user id relative to basePath (no required CLI args)
            $rel = ltrim(str_replace(rtrim($basePath, '/').'/', '', $jsonFile), '/');
            $parts = explode('/', $rel);

            // Expect: {Storage}/{user_id}/graph/{file}.json
            if (count($parts) < 4 || strtolower($parts[2]) !== 'graph') {
                Log::warning("Skipping file; unexpected folder structure: {$jsonFile}");
                continue;
            }

            $storageGroup = $parts[0]; // e.g., M365, S3, SMB
            $userIdRaw = $parts[1];

            if ($onlyStorage && strcasecmp($onlyStorage, $storageGroup) !== 0) {
                continue;
            }

            if (!ctype_digit($userIdRaw)) {
                Log::warning("Skipping file, user_id folder not numeric: {$jsonFile}");
                continue;
            }
            $userId = (int)$userIdRaw;

            if (!empty($onlyUsers) && !in_array($userId, $onlyUsers, true)) {
                continue;
            }

            // Skip unchanged JSON files using fingerprint cache (mtime + size)
            if ($useCache) {
                $fpKey = $this->fingerprintCacheKey($jsonFile);
                $fpCur = $this->fileFingerprint($jsonFile);
                $fpPrev = Cache::get($fpKey);
                if ($fpPrev === $fpCur) {
                    $this->line("Unchanged, skipped: {$jsonFile}");
                    // Not updating $seen means we won’t soft-delete for this user/storage pair in this run (safe).
                    continue;
                }
                Cache::put($fpKey, $fpCur, now()->addDays(7));
            }

            $this->line("Processing: {$jsonFile}");

            $data = @json_decode(@file_get_contents($jsonFile), true);
            if (!is_array($data)) {
                Log::warning("Invalid JSON in {$jsonFile}");
                continue;
            }
            if (empty($data)) {
                Log::info("Empty JSON array in {$jsonFile}");
                continue;
            }

            foreach ($data as $item) {
                try {
                    $storageType = $this->determineItemStorageType($storageGroup, $item);
                    if (!$storageType) {
                        Log::warning("Could not determine storage type for record in {$jsonFile}");
                        continue;
                    }

                    $sourceId = $this->getSourceFileId($storageType, $item);
                    if (!$sourceId) {
                        Log::warning("Skipping record without source id in {$jsonFile}");
                        continue;
                    }

                    // Remember seen for deletion reconciliation for this processed file
                    $this->rememberSeen($userId, $storageType, $sourceId);

                    if ($this->option('dry-run')) {
                        $this->logPreview($storageType, $userId, $item);
                        continue;
                    }

                    DB::transaction(function () use ($storageType, $userId, $item, $sourceId) {
                        [$file, $metaChanged, $wasRestored] = $this->upsertCommonFile($storageType, $userId, $item, $sourceId);

                        $permissionsChanged = false;
                        switch ($storageType) {
                            case 'aws_s3':
                                $this->upsertS3($file, $item);
                                break;
                            case 'smb':
                                $this->upsertSMB($file, $item);
                                break;
                            case 'onedrive':
                                [, $permissionsChanged] = $this->upsertOneDrive($file, $item);
                                break;
                            case 'sharepoint':
                                [, $permissionsChanged] = $this->upsertSharePoint($file, $item);
                                break;
                        }

                        // Permissions sync only if changed and present
                        if (!empty($item['permissions']) && $permissionsChanged) {
                            $this->syncPermissions($file->id, $item['permissions'], $storageType);
                        }

                        // LLM Analysis (delta-aware)
                        if (isset($item['llm_response'])) {
                            $llm = $this->parseLlmResponse($item['llm_response']);
                            if ($llm) {
                                $this->upsertLLM($file->id, $llm);
                            }
                        }
                    });

                } catch (\Throwable $e) {
                    Log::error("Error processing record in {$jsonFile}: {$e->getMessage()}");
                }
            }
        }

        if (!$this->option('dry-run')) {
            $this->softDeleteMissing();
        }

        $this->info('Ingest complete.');
        return self::SUCCESS;
    }

    protected function normalizeBasePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/')) {
            return $path; // absolute
        }
        return base_path($path);
    }

    // Determine per-record storage type. Supports mixed M365 JSONs.
    protected function determineItemStorageType(string $storageGroup, array $item): ?string
    {
        $group = strtolower($storageGroup);

        if ($group === 's3' || $group === 'aws') {
            return 'aws_s3';
        }
        if ($group === 'smb') {
            return 'smb';
        }
        if ($group === 'm365') {
            // SharePoint if both site_id and drive_id exist; otherwise OneDrive
            if (isset($item['site_id'], $item['drive_id'])) {
                return 'sharepoint';
            }
            return 'onedrive';
        }
        return null;
    }

    protected function getSourceFileId(string $storageType, array $item): ?string
    {
        return match ($storageType) {
            'aws_s3'     => $item['key'] ?? ($item['full_path'] ?? null),
            'smb'        => $item['full_path'] ?? null,
            'onedrive'   => $item['file_id'] ?? null,
            'sharepoint' => $item['file_id'] ?? null,
            default      => null,
        };
    }

    protected function rememberSeen(int $userId, string $storageType, string $sourceId): void
    {
        $k = $this->seenKey($userId, $storageType);
        $this->seen[$k] ??= [];
        $this->seen[$k][$sourceId] = true;
    }

    protected function seenKey(int $userId, string $storageType): string
    {
        return $userId.'|'.$storageType;
    }

    protected function logPreview(string $storageType, int $userId, array $item): void
    {
        $name = $item['file_name'] ?? 'unknown';
        $size = (int)($item['size_bytes'] ?? 0);
        Log::info("[DRY RUN] {$storageType} user={$userId} file={$name} size={$size}");
    }

    // Delta-aware upsert for common file row
    protected function upsertCommonFile(string $storageType, int $userId, array $item, string $sourceId): array
    {
        $fileName     = (string)($item['file_name'] ?? '');
        $fileType     = $item['file_type'] ?? null;
        $sizeBytes    = (int)($item['size_bytes'] ?? 0);
        $lastModified = $this->parseDate($item['last_modified'] ?? null);
        $webUrl       = $item['web_url'] ?? null;
        $downloadUrl  = $item['download_url'] ?? null;
        $fullPath     = $item['full_path'] ?? null;
        $fileExtension= $this->detectExtension($fileName, $fileType);

        $file = File::withTrashed()
            ->where('user_id', $userId)
            ->where('storage_type', $storageType)
            ->where('source_file_id', $sourceId)
            ->first();

        $wasRestored = false;
        if (!$file) {
            $file = new File();
            $file->user_id = $userId;
            $file->business_id = null; // set if mapping exists
            $file->storage_type = $storageType;
            $file->source_file_id = $sourceId;
        } elseif (method_exists($file, 'trashed') && $file->trashed()) {
            $file->restore();
            $wasRestored = true;
        }

        $file->fill([
            'file_name'      => $fileName,
            'file_type'      => $fileType,
            'file_extension' => $fileExtension,
            'size_bytes'     => $sizeBytes,
            'last_modified'  => $lastModified,
            'full_path'      => $fullPath,
            'web_url'        => $webUrl,
            'download_url'   => $downloadUrl,
        ]);

        $metaChanged = $file->isDirty();
        if ($metaChanged || !$file->exists) {
            $file->save();
        }

        return [$file, $metaChanged, $wasRestored];
    }

    protected function upsertS3(File $file, array $item): void
    {
        $key = $item['key'] ?? ($item['full_path'] ?? $file->file_name);
        $fullPath = $item['full_path'] ?? null;

        S3File::updateOrCreate(
            ['file_id' => $file->id],
            [
                'bucket' => $item['bucket'] ?? null,
                's3_key' => $key,
                'full_path' => $fullPath,
                'last_modified' => $this->parseDate($item['last_modified'] ?? null),
            ]
        );
    }

    protected function upsertSMB(File $file, array $item): void
    {
        $fullPath = $item['full_path'] ?? null;
        [$server, $share] = $this->parseSmbServerShare($fullPath);

        SMBFile::updateOrCreate(
            ['file_id' => $file->id],
            [
                'server' => $server,
                'share' => $share,
                'file_path' => $item['file_path'] ?? null,
                'full_path' => $fullPath,
                'created' => $this->parseDate($item['created'] ?? null),
                'last_modified' => $this->parseDate($item['last_modified'] ?? null),
                'acls' => $item['acls'] ?? null,
            ]
        );
    }

    // Returns [row, permissionsChanged]
    protected function upsertOneDrive(File $file, array $item): array
    {
        $row = OneDriveFile::firstOrNew(['file_id' => $file->id]);

        $oldPermHash = $this->jsonHash($row->permissions_json);
        $newPermHash = $this->jsonHash($item['permissions'] ?? null);
        $permissionsChanged = $oldPermHash !== $newPermHash;

        [$ownerId, $ownerName, $ownerEmail] = $this->extractOwnerFromPermissions($item['permissions'] ?? []);

        $row->fill([
            'drive_file_id' => $item['file_id'] ?? null,
            'owner_user_object_id' => $ownerId,
            'owner_display_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'parent_reference' => $item['parent_reference'] ?? null,
            'web_url' => $item['web_url'] ?? null,
            'download_url' => $item['download_url'] ?? null,
            'permissions_json' => $item['permissions'] ?? null,
        ]);

        if ($row->isDirty()) {
            $row->save();
        }

        return [$row, $permissionsChanged];
    }

    // Returns [row, permissionsChanged]
    protected function upsertSharePoint(File $file, array $item): array
    {
        $row = SharePointFile::firstOrNew(['file_id' => $file->id]);

        $oldPermHash = $this->jsonHash($row->permissions_json);
        $newPermHash = $this->jsonHash($item['permissions'] ?? null);
        $permissionsChanged = $oldPermHash !== $newPermHash;

        $row->fill([
            'site_id' => $item['site_id'] ?? null,
            'drive_id' => $item['drive_id'] ?? null,
            'drive_file_id' => $item['file_id'] ?? null,
            'parent_reference' => $item['parent_reference'] ?? null,
            'web_url' => $item['web_url'] ?? null,
            'download_url' => $item['download_url'] ?? null,
            'permissions_json' => $item['permissions'] ?? null,
        ]);

        if ($row->isDirty()) {
            $row->save();
        }

        return [$row, $permissionsChanged];
    }

    protected function syncPermissions(int $fileId, array $permissions, string $source): void
    {
        FilePermission::where('file_id', $fileId)->delete();

        $rows = [];
        foreach ($permissions as $perm) {
            $roles = $perm['roles'] ?? [];
            if (!is_array($roles)) {
                $roles = [$roles];
            }
            $providerPermissionId = $perm['id'] ?? null;
            $providerShareId = $perm['shareId'] ?? null;

            $principals = $this->extractPrincipals($perm['grantedToV2'] ?? $perm['grantedTo'] ?? null);
            if (empty($principals)) {
                $principals[] = [ 'type' => null, 'displayName' => null, 'email' => null, 'id' => null ];
            }

            foreach ($roles as $role) {
                foreach ($principals as $p) {
                    $rows[] = [
                        'file_id' => $fileId,
                        'role' => (string)$role,
                        'principal_type' => $p['type'],
                        'principal_display_name' => $p['displayName'],
                        'principal_email' => $p['email'],
                        'principal_id' => $p['id'],
                        'provider_permission_id' => $providerPermissionId,
                        'provider_share_id' => $providerShareId,
                        'source' => $source,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($rows)) {
            FilePermission::insert($rows);
        }
    }

    protected function parseLlmResponse($raw): ?array
    {
        if (is_null($raw) || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $decoded['results'] = isset($decoded['results']) && is_array($decoded['results'])
            ? $decoded['results'] : [];

        return $decoded;
    }

    protected function upsertLLM(int $fileId, array $llm): void
    {
        $classification = $this->sanitizeEnum($llm['data_classification'] ?? null, ['Public','Internal Use','Highly Sensitive'], null);
        $risk = $this->sanitizeEnum($llm['overall_risk_rating'] ?? null, ['None','Low','Medium','High'], null);

        $existing = FileAIAnalysis::where('file_id', $fileId)->first();

        $update = [
            'auditor_agent_view'      => $llm['auditor_agent_view'] ?? null,
            'likely_data_subject_area'=> $llm['likely_data_subject_area'] ?? null,
            'data_classification'     => $classification,
            'overall_risk_rating'     => $risk,
            'hacker_interest'         => $llm['hacker_interest'] ?? null,
            'auditor_proposed_action' => $llm['auditor_proposed_action'] ?? null,
            'raw_json'                => $llm,
        ];

        $shouldRewriteChildren = true;

        if ($existing) {
            $before = json_encode($existing->raw_json ?? []);
            $after  = json_encode($llm);
            if ($before === $after) {
                $shouldRewriteChildren = false;
            }
            $existing->fill($update)->save();
            $analysis = $existing;
        } else {
            $analysis = FileAIAnalysis::create(array_merge($update, ['file_id' => $fileId]));
        }

        if ($shouldRewriteChildren) {
            FileAIFinding::where('analysis_id', $analysis->id)->delete();
            FileAIControl::where('analysis_id', $analysis->id)->delete();

            foreach ($llm['results'] as $r) {
                $finding = FileAIFinding::create([
                    'analysis_id' => $analysis->id,
                    'standard' => $r['standard'] ?? 'Unknown',
                    'jurisdiction' => $r['jurisdiction'] ?? null,
                    'risk' => $this->sanitizeEnum($r['risk'] ?? null, ['None','Low','Medium','High'], null),
                ]);

                $fields = $r['detected_fields'] ?? [];
                if (is_string($fields)) $fields = [$fields];
                if (is_array($fields)) {
                    $rows = [];
                    foreach ($fields as $f) {
                        $rows[] = [
                            'finding_id' => $finding->id,
                            'field_name' => (string)$f,
                        ];
                    }
                    if (!empty($rows)) {
                        FileAIFindingDetectedField::insert($rows);
                    }
                }
            }

            $controls = $llm['cyber_proposed_controls'] ?? [];
            if (is_string($controls)) {
                $controls = [$controls];
            }
            if (is_array($controls)) {
                $rows = [];
                foreach ($controls as $ctl) {
                    $rows[] = [
                        'analysis_id' => $analysis->id,
                        'control_text' => is_string($ctl) ? $ctl : json_encode($ctl),
                    ];
                }
                if (!empty($rows)) {
                    FileAIControl::insert($rows);
                }
            }
        }
    }

    protected function softDeleteMissing(): void
    {
        foreach ($this->seen as $key => $map) {
            [$userIdStr, $storageType] = explode('|', $key);
            $userId = (int)$userIdStr;
            $seenSourceIds = array_keys($map);

            $existing = File::withTrashed()
                ->where('user_id', $userId)
                ->where('storage_type', $storageType)
                ->get(['id','source_file_id','deleted_at']);

            $toDelete = [];
            foreach ($existing as $f) {
                if (!in_array($f->source_file_id, $seenSourceIds, true)) {
                    if (!$f->deleted_at) {
                        $toDelete[] = $f->id;
                    }
                }
            }

            if (!empty($toDelete)) {
                File::whereIn('id', $toDelete)->update(['deleted_at' => now()]);
                Log::info("Soft-deleted ".count($toDelete)." {$storageType} files for user {$userId} not present in JSON.");
            }
        }
    }

    protected function parseDate(?string $s): ?Carbon
    {
        if (!$s) return null;
        try {
            return Carbon::parse($s);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function detectExtension(string $fileName, $fileType): ?string
    {
        if (is_string($fileType) && strlen($fileType) > 0 && $fileType[0] === '.') {
            return strtolower($fileType);
        }
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        return $ext ? '.'.strtolower($ext) : null;
    }

    protected function parseSmbServerShare(?string $fullPath): array
    {
        if (!$fullPath) return [null, null];
        $trim = preg_replace('#^//#', '', $fullPath);
        $parts = explode('/', $trim, 3); // server, share, rest
        $server = $parts[0] ?? null;
        $share = $parts[1] ?? null;
        return [$server, $share];
    }

    protected function extractOwnerFromPermissions(array $permissions): array
    {
        foreach ($permissions as $perm) {
            $roles = $perm['roles'] ?? [];
            if (!in_array('owner', $roles, true)) {
                continue;
            }
            $gt = $perm['grantedToV2']['user'] ?? $perm['grantedTo']['user'] ?? null;
            if ($gt) {
                return [
                    $gt['id'] ?? null,
                    $gt['displayName'] ?? null,
                    $gt['email'] ?? null
                ];
            }
        }
        return [null, null, null];
    }

    protected function extractPrincipals($grantedTo): array
    {
        $out = [];
        if (!is_array($grantedTo)) return $out;

        foreach (['user','siteUser','group','siteGroup'] as $type) {
            if (!empty($grantedTo[$type]) && is_array($grantedTo[$type])) {
                $o = $grantedTo[$type];
                $out[] = [
                    'type' => $type,
                    'displayName' => $o['displayName'] ?? null,
                    'email' => $o['email'] ?? null,
                    'id' => $o['id'] ?? null,
                ];
            }
        }
        return $out;
    }

    protected function sanitizeEnum(?string $value, array $allowed, $default)
    {
        if (!$value) return $default;
        foreach ($allowed as $opt) {
            if (strcasecmp($opt, $value) === 0) {
                return $opt;
            }
        }
        return $default;
    }

    protected function jsonHash($value): ?string
    {
        if ($value === null) return null;
        if (is_string($value)) return md5($value);
        return md5(json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    protected function fileFingerprint(string $path): string
    {
        return @filemtime($path).':'.@filesize($path);
    }

    protected function fingerprintCacheKey(string $path): string
    {
        return 'files:ingest:fp:'.md5($path);
    }
}