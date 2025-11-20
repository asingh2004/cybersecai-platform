<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataSourceRestrictionPolicySeeder extends Seeder
{
    public function run()
    {
        $policies = [
            'aws_s3' => [
                'block_public_access' => ['type' => 'boolean', 'description' => 'Block all public access'],
                'encryption' => ['type' => 'enum', 'values' => ['SSE-S3', 'SSE-KMS', 'NONE'], 'description' => 'Bucket Encryption'],
                'allowed_roles' => ['type' => 'array', 'description' => 'IAM roles allowed'],
            ],
            'box_drive' => [
                'shared_link_access' => ['type' => 'enum', 'values' => ['open', 'company', 'collaborators'], 'description' => 'Shared link access'],
                'can_download'      => ['type' => 'boolean', 'description' => 'Allow download'],
            ],
            'google_drive' => [
                'sharing' => ['type' => 'enum', 'values' => ['internal', 'domain', 'anyone'], 'description' => 'Who can access'],
                'prevent_download' => ['type' => 'boolean', 'description' => 'Prevent download'],
            ],
            'm365_onedrive' => [
                'share_with_external' => ['type' => 'boolean', 'description' => 'Allow sharing outside org'],
                'block_download'      => ['type' => 'boolean', 'description' => 'Block download'],
            ],
            'sharepoint' => [
                'share_with_external' => ['type' => 'boolean', 'description' => 'Allow sharing outside org'],
                'block_download'      => ['type' => 'boolean', 'description' => 'Block download'],
            ],
            'nfs_fileshare' => [
                'allowed_ips'      => ['type' => 'array', 'description' => 'IP addresses allowed'],
                'read_only' => ['type' => 'boolean', 'description' => 'Read-only mount'],
            ],
            'smb_fileshare' => [
                'allowed_sids' => ['type' => 'array', 'description' => 'Allowed user SIDs'],
                'read_only'    => ['type' => 'boolean', 'description' => 'Read-only share'],
            ],
        ];

        foreach ($policies as $type => $rules) {
            DB::table('data_source_ref')
                ->where('ref_name', $type)
                ->update(['restriction_policy' => json_encode($rules)]);
        }
    }
}