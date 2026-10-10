<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;
use PDO;
use RuntimeException;

/** Reports current effective grants, not a reconstruction of acceptance-time policy. */
final class ApprovalAudit
{
    public function collect(PDO $pdo, string $repositoryRoot): array
    {
        $metadata = ReadModelMetadata::readMetadata($pdo);
        $recordedRoot = $metadata['repository_root'] ?? '';
        if ($recordedRoot !== '' && realpath($recordedRoot) !== realpath($repositoryRoot)) {
            throw new RuntimeException('Read-model repository_root does not match the requested repository.');
        }

        $warnings = [];
        if ($recordedRoot === '') {
            $warnings[] = 'Read-model repository association is unavailable.';
        }
        $head = ReadModelMetadata::repositoryHead($repositoryRoot);
        if (!isset($metadata['repository_head']) || $head === 'git-error'
            || $head !== $metadata['repository_head']) {
            $warnings[] = 'Read-model freshness is unverified or its repository head differs; run ./v3 status.';
        }
        if (!ReadModelMetadata::hasExpectedSchemaIdentity($metadata)) {
            $warnings[] = 'Read-model schema metadata differs from this checkout; results use the available profile fields.';
        }

        // Read canonical seed identity IDs, never an approver's display label (which can be "root").
        $seeds = [];
        $repository = new CanonicalRecordRepository($repositoryRoot);
        foreach (glob($repositoryRoot . '/records/approval-seeds/*.txt') ?: [] as $path) {
            $seed = $repository->loadApprovalSeed('records/approval-seeds/' . basename($path));
            $seeds[$seed->approvedIdentityId] = true;
        }

        $profiles = [];
        $usernameCounts = [];
        foreach ($pdo->query('SELECT identity_id, profile_slug, username, username_token,
                is_approved, approved_by_identity_id FROM profiles
                ORDER BY username_token, identity_id')->fetchAll(PDO::FETCH_ASSOC) as $profile) {
            $profiles[$profile['identity_id']] = $profile;
            if ((int) $profile['is_approved'] === 1) {
                $token = $profile['username_token'];
                $usernameCounts[$token] = ($usernameCounts[$token] ?? 0) + 1;
            }
        }

        $counts = array_fill_keys([
            'root_seed', 'operator_approval', 'same_username',
            'cross_username_single_key', 'cross_username_multiple_keys', 'unknown',
        ], 0);
        $rows = [];
        foreach ($profiles as $identityId => $target) {
            if ((int) $target['is_approved'] !== 1) {
                continue;
            }
            $approverId = $target['approved_by_identity_id'];
            $approver = $profiles[$approverId ?? ''] ?? null;
            $keyCount = $usernameCounts[$target['username_token']];
            if (isset($seeds[$identityId]) && $approverId === null) {
                $category = 'root_seed';
                $reason = 'Identity is explicitly seeded by root.';
            } elseif ($approverId === null || $approverId === $identityId
                || isset($seeds[$identityId])
                || (!isset($seeds[$approverId]) && ($approver === null || (int) $approver['is_approved'] !== 1))) {
                $category = 'unknown';
                $reason = 'Missing or inconsistent approval attribution; inspect canonical records.';
            } elseif (isset($seeds[$approverId])) {
                $category = 'operator_approval';
                $reason = 'Attributed approver is currently root-seeded; historical operator timing is not established.';
            } elseif ($target['username_token'] === '' || $approver['username_token'] === '') {
                $category = 'unknown';
                $reason = 'A canonical username is missing.';
            } elseif ($target['username_token'] === $approver['username_token']) {
                $category = 'same_username';
                $reason = 'Attributed approver and target share a canonical username.';
            } elseif ($keyCount > 1) {
                $category = 'cross_username_multiple_keys';
                $reason = 'Non-operator cross-username approval on a currently multi-key account; review whether this was its first key.';
            } else {
                $category = 'cross_username_single_key';
                $reason = 'Non-operator cross-username approval; this is the only currently approved key for the username.';
            }
            $counts[$category]++;
            $rows[] = [
                'category' => $category,
                'review_candidate' => in_array($category, ['cross_username_multiple_keys', 'unknown'], true),
                'username' => $target['username'],
                'username_token' => $target['username_token'],
                'identity_id' => $identityId,
                'profile_slug' => $target['profile_slug'],
                'approved_key_count' => $keyCount,
                'approver_username' => $approver['username'] ?? null,
                'approver_identity_id' => $approverId,
                'reason' => $reason,
            ];
        }

        return [
            'scope' => 'Currently approved keys and their read-model attributed approver, including invitation-derived grants. Not every historical approval event.',
            'limitations' => 'Review candidates are not proven violations. First-key status, operator status, and the setting at acceptance time are not reconstructed. Signatures are not reverified. Only current attribution is reported, not all possible approval paths.',
            'repository_root' => $repositoryRoot,
            'read_model_metadata' => $metadata,
            'warnings' => $warnings,
            'approved_keys' => count($rows),
            'approved_usernames' => count($usernameCounts),
            'review_candidates' => count(array_filter($rows, static fn (array $row): bool => $row['review_candidate'])),
            'counts' => $counts,
            'rows' => $rows,
        ];
    }
}
