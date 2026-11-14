<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\{LabelRemoval, ReactionTarget, RemovalTarget};
use RuntimeException;

/**
 * `./v3 delete-record <reaction-id>`: removes a like or other reaction record and its signature,
 * and leaves the same local-operator receipt a removed post gets. Caller refreshes derived state.
 */
final class ReactionRemoval
{
    /** @return array{record_id: string, receipt_path: string, removed: list<string>, commit_sha: string, thread_id: string} */
    public static function run(string $root, string $recordId, ?string $acceptedAt = null): array
    {
        if (RemovalTarget::family($recordId) === 'post') throw new RuntimeException('Not a reaction record ID.');
        if (LabelRemoval::read($root, $recordId) !== null
            || \ForumRewrite\Canonical\AcceptedRetraction::read($root, $recordId) !== null) {
            throw new RuntimeException('Reaction already removed: ' . $recordId);
        }
        $reaction = ReactionTarget::describe($root, $recordId);
        $receiptPath = LabelRemoval::path($recordId);
        $receipt = LabelRemoval::encode(['acceptance_version' => 2, 'source_id' => $recordId, 'operation' => 'delete',
            'accepted_at' => $acceptedAt ?? gmdate('Y-m-d\TH:i:s\Z'), 'actor' => 'local-operator',
            'source_hash' => hash('sha256', $reaction['bytes']), 'labels' => [],
            'post' => ['post_id' => $recordId, 'thread_id' => $reaction['thread_id'], 'parent_id' => $reaction['target_post_id'],
                'created_at' => $reaction['created_at'], 'author_identity_id' => $reaction['author_identity_id'] ?: null, 'thread_type' => null]]);
        $removed = [];
        foreach ([$reaction['path'], $reaction['path'] . '.asc'] as $path) {
            if (trim(LabelRemoval::git($root, ['ls-files', '--', $path])) !== '') $removed[] = $path;
        }
        if (!in_array($reaction['path'], $removed, true)) {
            throw new RuntimeException('Reaction is not committed; commit or remove it by hand first: ' . $reaction['path']);
        }
        if (!is_dir(dirname($root . '/' . $receiptPath))) mkdir(dirname($root . '/' . $receiptPath), 0775, true);
        file_put_contents($root . '/' . $receiptPath, $receipt);
        try {
            LabelRemoval::git($root, array_merge(['rm', '-q', '--'], $removed));
            LabelRemoval::git($root, ['add', '--', $receiptPath]);
            LabelRemoval::git($root, array_merge(['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid',
                'commit', '-q', '-m', 'Delete ' . str_replace('-', ' ', RemovalTarget::family($recordId)) . ' ' . $recordId . ' (local operator)', '--', $receiptPath], $removed));
        } catch (\Throwable $error) {
            LabelRemoval::git($root, array_merge(['reset', '-q', 'HEAD', '--', $receiptPath], $removed));
            if (is_file($root . '/' . $receiptPath)) unlink($root . '/' . $receiptPath);
            LabelRemoval::git($root, array_merge(['checkout', '-q', 'HEAD', '--'], $removed));
            throw $error;
        }
        return ['record_id' => $recordId, 'receipt_path' => $receiptPath, 'removed' => $removed,
            'commit_sha' => trim(LabelRemoval::git($root, ['rev-parse', 'HEAD'])), 'thread_id' => $reaction['thread_id']];
    }
}
