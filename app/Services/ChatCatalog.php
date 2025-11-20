<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatCatalog
{
    // Resolve the “owner” account (handles your business_id mapping)
    public function resolveOwnerUserId(User $current): int
    {
        $userId = $current->id;
        if ($current && intval($current->business_id) !== 0 && $current->business_id != $current->id) {
            $bizId = (int) DB::table('users')->where('id', $current->business_id)->value('id');
            if ($bizId) $userId = $bizId;
        }
        return $userId;
    }

    // Get config_ids for a user (with safe fallback)
    public function configIdsForUserId(int $userId): array
    {
        $ids = DB::table('data_configs')->where('user_id', $userId)->pluck('id')->toArray();
        if (empty($ids)) $ids = [1];
        return array_map('intval', $ids);
    }

    // Personas list (could also come from config/db)
    public function personas(): array
    {
        return [
            'Risk Auditor'  => 'Compliance/audit focus',
            'Cybersecurity' => 'Security operations',
            'Board Member'  => 'Executive summary',
            'All'           => 'Integrated compliance + cyber',
        ];
    }

    // Use-cases list with user-specific injection (user_id, config_ids, etc.)
    public function useCases(?User $user = null): array
    {
        $user = $user ?: Auth::user();
        $userId = $user ? $this->resolveOwnerUserId($user) : null;
        $configIds = $userId ? $this->configIdsForUserId($userId) : [1];

        $cases = [
            [
                'persona'    => 'All',
                'label'      => 'Summarizer: Complete Statistics',
                'prompt'     => 'Show a complete statistics dashboard for my environment.',
                'alt'        => 'All: One-click stats across key risk and inventory dimensions.',
                'operation'  => 'summarizer_stats',
                'args'       => ['days' => 7, 'data_source' => null, 'limit' => 100],
            ],
            [
                'persona'   => 'Cybersecurity',
                'label'     => 'Cybersecurity Overview & Actions',
                'prompt'    => 'Give me a cybersecurity overview of all files and show next best actions.',
                'alt'       => 'Quick cybersecurity dashboard with breakdown and actionable follow-ups.',
                'operation' => 'cybersec',
            ],
            [
                'persona'      => 'All',
                'label'        => 'All: Compliance Evidence & Audit',
                'prompt'       => 'Create a full compliance, evidence, and audit report for all files with current risk, sharing, and location status as per latest compliance standards.',
                'alt'          => 'Create a full compliance, evidence, and audit report for all files with current risk, sharing, and location status as per latest compliance standards.',
                'operation'    => 'm365_compliance_auto',
                'args'         => [],
                'config_ids'   => [],     // will inject below
                'corporate_domains' => ["cybersecai.io"],
            ],
            [
                'persona' => 'Cybersecurity',
                'label'   => 'Cybersecurity: What-if Policy Simulation',
                'prompt'  => 'Simulate the impact of deleting or encrypting all files in a given class and show what issues or violations may arise.',
                'alt'     => 'Cybersecurity: Scenario/impact analysis tool for SecOps and governance.',
            ],
            [
                'persona'   => 'Cybersecurity',
                'label'     => 'Pentest: Web App Security Assessment',
                'prompt'    => 'Perform an autonomous penetration test ',
                'alt'       => 'Performs a world-class, standards-based external pentest and risk report for any public domain.',
                'operation' => 'pentest_auto',
                'args'      => ['domain' => ''],
            ],
            [
                'persona'   => 'Board Member',
                'label'     => 'Board Member: Executive Summary',
                'prompt'    => 'Summarize key compliance and cyber risk trends system-wide this month, with clear recommendations.',
                'alt'       => 'Board Member: Executive/board face-ready summary and advice.',
                'operation' => 'audit_board_summary',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Board Member',
                'label'     => 'Board Member: Board-Level Audit Report',
                'prompt'    => 'Provide a concise board-level summary and recommendations based on current file risk status and trends.',
                'alt'       => 'Board Member: Easy-to-read executive report for board/leadership.',
                'operation' => 'audit_full',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Risk Auditor',
                'label'     => 'Risk Auditor: Compliance Evidence Report',
                'prompt'    => 'Generate an audit-ready compliance evidence report for all detected privacy standard findings.',
                'alt'       => 'Risk Auditor: For audit/assurance, produces exportable reports from all findings.',
                'operation' => 'audit_full',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Board Member',
                'label'     => 'Board Member: Audit Evidence Only',
                'prompt'    => 'Show detailed evidence tables of detected high-risk file exposures for review.',
                'alt'       => 'Board Member: Direct evidence tables for board review or compliance sampling.',
                'operation' => 'audit_evidence',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Board Member',
                'label'     => 'Board Member: No More Questions',
                'prompt'    => 'Thank you, no further questions.',
                'alt'       => 'End board session',
                'operation' => 'audit_no_action',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Risk Auditor',
                'label'     => 'Risk Auditor: Compliance Advisory',
                'prompt'    => 'Provide compliance advisory including urgent actions for any new or high risk files.',
                'alt'       => 'Risk Auditor: Prioritized compliance/legal advice for privacy and legal teams.',
                'operation' => 'audit_compliance_advisory',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Risk Auditor',
                'label'     => 'Risk Auditor: Find Risk Hotspots',
                'prompt'    => 'Identify the files or folders with the highest current risk and recommend next actions.',
                'alt'       => 'Risk Auditor: Identifies riskiest items systemwide and suggests priorities.',
                'operation' => 'audit_find_risk_hotspots',
                'agent'     => 'audit',
            ],
            [
                'persona'   => 'Risk Auditor',
                'label'     => 'Risk Auditor: Continuous Alerts & Monitoring',
                'prompt'    => 'List any newly detected high-risk or non-compliant files since the last system scan.',
                'alt'       => 'Risk Auditor: Continuous risk monitoring for ongoing assurance.',
                'operation' => 'audit_continuous_alerts',
                'agent'     => 'audit',
            ],
            [
                'persona' => 'Board Member',
                'label'   => 'Board Member: Incident Simulation',
                'prompt'  => 'If a major data breach happened, what would be the predicted business impact and who would be most affected?',
                'alt'     => 'Board Member: Business and risk estimation simulation.',
            ],
            [
                'persona' => 'Auditor/Security',
                'label'   => 'Auditor/Security: Automated Forensics Trail',
                'prompt'  => 'Show a full forensic audit trail for all high-risk files changed recently, with supporting evidence logs.',
                'alt'     => 'Auditor/Security: Evidence-oriented, in-depth log/audit demonstrations.',
            ],
            [
                'persona' => 'Auditor/Security',
                'label'   => 'Auditor/Security: Zero-Knowledge Risk Mapping',
                'prompt'  => 'Generate a universal map of files with regulated data exposure, regardless of storage location, for zero-knowledge auditing.',
                'alt'     => 'Auditor/Security: For full visibility data audits, even with minimal access.',
            ],
        ];

        // Inject per-user fields
        $cases = array_map(function ($c) use ($configIds, $userId) {
            $c['user_id'] = $userId;
            if (!isset($c['args'])) $c['args'] = [];
            if (($c['operation'] ?? null) === 'm365_compliance_auto') {
                $c['config_ids'] = $configIds;
            }
            return $c;
        }, $cases);

        return $cases;
    }
}