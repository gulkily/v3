<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Operator\OperatorAction;
use ForumRewrite\Operator\OperatorAuthority;
use ForumRewrite\Operator\OperatorGraph;
use RuntimeException;

trait OperatorWriteActions
{
    public function prepareOperatorAction(array $input, string $actorId): array
    {
        return $this->withTimedWriteLock(function () use ($input, $actorId): array {
            $this->assertWritableRepository();
            $state = OperatorAuthority::load($this->readModelPdo());
            $actor = $this->requireOperatorActor($state, $actorId);
            $action = (string) ($input['action'] ?? 'grant');
            if (!in_array($action, ['grant', 'revoke'], true)) {
                throw new RuntimeException('Unsupported operator action.');
            }
            $grantId = (string) ($input['grant_id'] ?? '');
            $affected = [];
            if ($action === 'revoke') {
                $affected = OperatorGraph::preview($state, $grantId);
                $targetId = $state['grants'][$grantId]['target_identity_id'];
            } else {
                $targetId = (string) ($input['target_identity_id'] ?? '');
            }
            $target = $state['profiles'][$targetId] ?? null;
            if ($target === null || (int) $target['is_approved'] !== 1 || isset($state['roots'][$target['username_token']])) {
                throw new RuntimeException('Select an approved key of a non-root account.');
            }
            $postId = $this->generateRecordId('reply');
            $createdAt = $this->canonicalTimestampNow();
            $sequence = $state['sequence'] + 1;
            $contents = "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: identity operator internal\n"
                . "Thread-ID: {$target['bootstrap_thread_id']}\nParent-ID: {$target['bootstrap_post_id']}\n"
                . "Author-Identity-ID: {$actorId}\n\nOperator-Action: {$action}\n"
                . "Operator-Account: {$target['username_token']}\nOperator-Sequence: {$sequence}\n"
                . "Operator-Target-Identity: {$target['identity_id']}\n";
            if ($action === 'revoke') {
                $contents .= "Operator-Grant-ID: {$grantId}\n";
            }
            OperatorAction::parse((new PostRecordParser())->parse($contents));
            $path = CanonicalPathResolver::datedPost($postId, $createdAt);
            $prepared = $this->storePreparedPost($path, $contents, [
                'kind' => 'operator-action', 'post_id' => $postId, 'thread_id' => $target['bootstrap_thread_id'],
                'author_identity_id' => $actorId, 'revision' => $state['revision'],
            ]);
            return $prepared + ['status' => 'ok', 'post_id' => $postId, 'record_path' => $path,
                'canonical_record' => $contents, 'account' => $target['username_token'], 'action' => $action,
                'affected_accounts' => $affected];
        });
    }

    public function finalizeOperatorAction(array $input, string $actorId): array
    {
        return $this->withTimedWriteLock(function () use ($input, $actorId): array {
            $this->assertWritableRepository();
            $token = $this->requireHexToken((string) ($input['prepare_token'] ?? ''), 'prepare_token');
            $prepared = $this->loadPreparedPost($token);
            if (($prepared['kind'] ?? '') !== 'operator-action' || strtotime($prepared['expires_at']) < time()
                || ($prepared['author_identity_id'] ?? '') !== $actorId) {
                throw new RuntimeException('Prepared operator action expired or belongs to another identity.');
            }
            $state = OperatorAuthority::load($this->readModelPdo());
            $this->requireOperatorActor($state, $actorId);
            if (!hash_equals($prepared['revision'], $state['revision'])) {
                throw new RuntimeException('Operator or account state changed. Review the action again.');
            }
            $contents = (string) ($input['canonical_record'] ?? '');
            if (!hash_equals($prepared['canonical_sha256'], hash('sha256', $contents))) {
                throw new RuntimeException('Prepared operator canonical record mismatch.');
            }
            $path = $this->requirePreparedMatch($input, $prepared, 'record_path');
            $id = $this->requirePreparedMatch($input, $prepared, 'post_id');
            if ($this->postTargetExists($id)) {
                throw new RuntimeException('Prepared operator action already exists.');
            }
            $signature = $this->normalizeAsciiBody((string) ($input['detached_signature'] ?? ''), 'detached_signature');
            [$key, $fingerprint] = $this->publicKeyForIdentity($actorId);
            if (!$this->signatureVerifier->verifyDetached($key, $contents, $signature, $fingerprint)['ok']) {
                throw new RuntimeException('Detached operator signature verification failed.');
            }
            $record = (new PostRecordParser())->parse($contents);
            $event = OperatorAction::parse($record);
            if ($event['sequence'] !== $state['sequence'] + 1 || $event['author_identity_id'] !== $actorId
                || $record->postId !== $id || CanonicalPathResolver::datedPost($id, $record->createdAt) !== $path) {
                throw new RuntimeException('Prepared operator metadata mismatch.');
            }
            $this->writeFile($path, $contents);
            $this->writeFile($path . '.asc', $signature);
            $commit = $this->commitCanonicalWrite([$path, $path . '.asc'], 'Operator ' . $event['action'] . ' for ' . $event['account'] . ' by ' . $actorId);
            $this->refreshDerivedStateAfterCommit($commit['commit_sha']);
            $this->invalidator()->invalidateApprovalSeed();
            $this->deletePreparedPost($token);
            return ['status' => 'ok', 'post_id' => $id, 'account' => $event['account'], 'commit_sha' => $commit['commit_sha']];
        });
    }

    private function requireOperatorActor(array $state, string $identityId): array
    {
        $actor = $state['profiles'][$identityId] ?? null;
        if ($actor === null || (int) $actor['is_approved'] !== 1 || !isset($state['accounts'][$actor['username_token']])) {
            throw new RuntimeException('An authenticated approved operator key is required.');
        }
        return $actor;
    }
}
