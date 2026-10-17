<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Security\ApprovalPolicy;
use PDO;

/** Current account keys with immutable acceptance facts when available. */
final class ApprovalHistory
{
    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    /** One key on a profile; the account-wide list belongs on the username page. */
    public function forKey(array $profile): array
    {
        $rows = (int) $profile['is_approved'] === 1 ? [$this->forProfile($profile)] : [];
        return ['username' => $profile['username_token'], 'rows' => $rows, 'total' => count($rows),
            'profile_scope' => true, 'offset' => 0, 'next_offset' => null, 'previous_offset' => null];
    }

    public function forUsername(string $token, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM profiles WHERE username_token = ? AND is_approved = 1');
        $count->execute([$token]);
        $total = (int) $count->fetchColumn();
        $query = $this->pdo->prepare('SELECT * FROM profiles WHERE username_token = ? AND is_approved = 1 ORDER BY identity_id LIMIT ? OFFSET ?');
        $query->bindValue(1, $token);
        $query->bindValue(2, $limit, PDO::PARAM_INT);
        $query->bindValue(3, $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $profile) $rows[] = $this->forProfile($profile);
        return ['username' => $token, 'rows' => $rows, 'total' => $total, 'offset' => $offset,
            'next_offset' => $offset + $limit < $total ? $offset + $limit : null,
            'previous_offset' => $offset > 0 ? max(0, $offset - $limit) : null];
    }

    public function forProfile(array $profile): array
    {
        $row = ['identity_id' => $profile['identity_id'], 'profile_slug' => $profile['profile_slug'],
            'approver_identity_id' => $profile['approved_by_identity_id'], 'approver_label' => $profile['approved_by_label'] ?: 'unknown',
            'classification' => 'unknown', 'accepted_at' => null, 'recorded_at' => null, 'recorded_at_kind' => null, 'existing_key_count' => null,
            'target_username_at_acceptance' => null, 'actor_is_operator' => null, 'cross_account_allowed' => null, 'source_path' => null, 'receipt_path' => null, 'kind' => 'unknown'];
        if ((int) $profile['is_approved'] !== 1) return $row;
        if ($profile['approved_by_identity_id'] === null && ApprovalPolicy::isOperator($this->root, $profile['identity_id'])) {
            $row['kind'] = 'root_seed';
            $row['source_path'] = CanonicalPathResolver::approvalSeed(substr($profile['identity_id'], 8));
            $commit = (new ApprovalSeedHistory($this->root))->recent($row['source_path'], 1)[0] ?? null;
            $row['recorded_at'] = $commit['committed_at'] ?? null;
            $row['recorded_at_kind'] = $commit !== null ? 'commit' : null;
            return $row;
        }
        // Bootstrap threads are indexed; bound candidate work per key as well as per page.
        $query = $this->pdo->prepare('SELECT post_id, created_at, body, board_tags_json, author_identity_id FROM posts
            WHERE thread_id = ? AND parent_id = ? AND (body LIKE ? OR (author_identity_id = ? AND body LIKE ?)) ORDER BY created_at, post_id LIMIT 100');
        $query->execute([$profile['bootstrap_thread_id'], $profile['bootstrap_post_id'], '%Approve-Identity-ID: ' . $profile['identity_id'] . '%', $profile['identity_id'], '%Invitation-Action: redeem%']);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $post) {
            $tags = json_decode($post['board_tags_json'], true) ?: [];
            $direct = in_array('approval', $tags, true) && $post['author_identity_id'] === $profile['approved_by_identity_id']
                && preg_match('/^Approve-Identity-ID: ' . preg_quote($profile['identity_id'], '/') . '$/m', $post['body']);
            $invitation = in_array('invitation', $tags, true) && $post['author_identity_id'] === $profile['identity_id']
                && preg_match('/^Invitation-Action: redeem$/m', $post['body']);
            if (!$direct && !$invitation) continue;
            $path = CanonicalPathResolver::datedPost($post['post_id'], $post['created_at']);
            if (!is_file($this->root . '/' . $path)) $path = CanonicalPathResolver::post($post['post_id']);
            $receipt = ApprovalAcceptance::read($this->root, $path);
            if ($receipt !== null && $receipt['context']['target_identity_id'] === $profile['identity_id']) {
                $context = $receipt['context'];
                return array_replace($row, ['approver_identity_id' => $context['actor_identity_id'], 'approver_label' => $context['actor_username'],
                    'classification' => $context['classification'], 'target_username_at_acceptance' => $context['target_username'], 'accepted_at' => $receipt['accepted_at'],
                    'recorded_at' => $post['created_at'], 'recorded_at_kind' => 'record',
                    'existing_key_count' => $context['existing_key_count'], 'actor_is_operator' => $context['actor_is_operator'],
                    'cross_account_allowed' => $context['cross_account_allowed'], 'source_path' => $path,
                    'receipt_path' => ApprovalAcceptance::path($post['post_id']), 'kind' => $receipt['kind']]);
            }
            if ($row['source_path'] === null) {
                $row['source_path'] = $path; $row['kind'] = $direct ? 'direct' : 'invitation';
                $row['recorded_at'] = $post['created_at']; $row['recorded_at_kind'] = 'record';
            }
        }
        return $row;
    }
}
