<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\PostRecord;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\PostReactionRecordParser;
use ForumRewrite\Canonical\SiteFeatureFlagsRecordParser;
use ForumRewrite\Canonical\ThreadLabelRecordParser;
use ForumRewrite\TagScore;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\UnicodeTextPolicy;
use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

class LocalWriteService
{
    private const HIDDEN_BOOTSTRAP_BOARD_TAGS = 'identity internal';

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
        private readonly CanonicalRecordRepository $canonicalRepository,
        private readonly OpenPgpKeyInspector $keyInspector = new OpenPgpKeyInspector(),
        private readonly FeatureFlagEvaluator $featureFlags = new FeatureFlagEvaluator(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function createThread(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);
            $postId = $this->generateRecordId('thread');
            $boardTags = $this->normalizeBoardTags((string) ($input['board_tags'] ?? 'general'));
            $subject = $this->normalizeAuthoredLine((string) ($input['subject'] ?? ''), 'subject');
            $body = $this->normalizeAuthoredBody((string) ($input['body'] ?? ''), 'body');
            $authorIdentityId = $this->resolveAuthorIdentityId($input);
            $createdAt = $this->canonicalTimestampNow();

            $contents = "Post-ID: {$postId}\n"
                . "Created-At: {$createdAt}\n"
                . "Board-Tags: {$boardTags}\n"
                . ($authorIdentityId !== null ? "Author-Identity-ID: {$authorIdentityId}\n" : '')
                . ($subject !== '' ? "Subject: {$subject}\n" : '')
                . "\n{$body}";

            $record = (new PostRecordParser())->parse($contents);
            $recordPath = 'records/posts/' . $postId . '.txt';
            $threadLabels = $this->extractThreadLabelsFromBody($body);
            $labelRecordPath = null;
            $phaseStartedAt = hrtime(true);
            $this->writeFile($recordPath, $contents);
            if ($threadLabels !== []) {
                [$labelRecordPath, $labelContents] = $this->buildThreadLabelRecord($postId, $threadLabels, $authorIdentityId, $createdAt);
                $this->writeFile($labelRecordPath, $labelContents);
            }
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);
            $phaseStartedAt = hrtime(true);
            $writtenPaths = [$recordPath];
            if ($labelRecordPath !== null) {
                $writtenPaths[] = $labelRecordPath;
            }
            $commitResult = $this->commitCanonicalWrite($writtenPaths, 'Create thread ' . $postId);
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];
            $timings = array_merge($timings, $this->synchronizePostDerivedState($record, $commitSha, $labelRecordPath !== null));
            $phaseStartedAt = hrtime(true);
            $this->invalidator()->invalidateBoardThread($postId);
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $postId,
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function createReply(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);
            $threadId = $this->requireAsciiToken((string) ($input['thread_id'] ?? ''), 'thread_id');
            $parentId = $this->requireAsciiToken((string) ($input['parent_id'] ?? ''), 'parent_id');
            $body = $this->normalizeAuthoredBody((string) ($input['body'] ?? ''), 'body');
            $boardTags = $this->normalizeBoardTags((string) ($input['board_tags'] ?? 'general'));
            $authorIdentityId = $this->resolveAuthorIdentityId($input);
            $createdAt = $this->canonicalTimestampNow();

            $thread = $this->canonicalRepository->loadPost('records/posts/' . $threadId . '.txt');
            $parent = $this->canonicalRepository->loadPost('records/posts/' . $parentId . '.txt');
            $parentThreadId = $parent->threadId ?? $parent->postId;
            if ($thread->postId !== $threadId || $parentThreadId !== $threadId) {
                throw new RuntimeException('Parent post must belong to the target thread.');
            }

            $postId = $this->generateRecordId('reply');
            $contents = "Post-ID: {$postId}\n"
                . "Created-At: {$createdAt}\n"
                . "Board-Tags: {$boardTags}\n"
                . "Thread-ID: {$threadId}\n"
                . "Parent-ID: {$parentId}\n"
                . ($authorIdentityId !== null ? "Author-Identity-ID: {$authorIdentityId}\n" : '')
                . "\n{$body}";

            $record = (new PostRecordParser())->parse($contents);
            $recordPath = 'records/posts/' . $postId . '.txt';
            $threadLabels = $this->extractThreadLabelsFromBody($body);
            $labelRecordPath = null;
            $phaseStartedAt = hrtime(true);
            $this->writeFile($recordPath, $contents);
            if ($threadLabels !== []) {
                [$labelRecordPath, $labelContents] = $this->buildThreadLabelRecord($threadId, $threadLabels, $authorIdentityId, $createdAt);
                $this->writeFile($labelRecordPath, $labelContents);
            }
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);
            $phaseStartedAt = hrtime(true);
            $writtenPaths = [$recordPath];
            if ($labelRecordPath !== null) {
                $writtenPaths[] = $labelRecordPath;
            }
            $commitResult = $this->commitCanonicalWrite($writtenPaths, 'Create reply ' . $postId);
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];
            $timings = array_merge($timings, $this->synchronizePostDerivedState($record, $commitSha, $labelRecordPath !== null));
            $phaseStartedAt = hrtime(true);
            $this->invalidator()->invalidateReply($threadId, $postId);
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $threadId,
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function applyThreadTag(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);
            $threadId = $this->requireAsciiToken((string) ($input['thread_id'] ?? ''), 'thread_id');
            $tag = $this->normalizeThreadTag((string) ($input['tag'] ?? ''));
            $authorIdentityId = $this->requireOpenPgpIdentityId((string) ($input['author_identity_id'] ?? ''), 'author_identity_id');
            $createdAt = $this->canonicalTimestampNow();

            $thread = $this->canonicalRepository->loadPost(CanonicalPathResolver::post($threadId));
            if ($thread->threadId !== null) {
                throw new RuntimeException('thread_id must refer to a root thread.');
            }

            $viewerIsApproved = $this->isApprovedIdentity($authorIdentityId) ? 'yes' : 'no';
            if ($this->hasThreadTagFromIdentity($threadId, $tag, $authorIdentityId)) {
                return [
                    'status' => 'ok',
                    'thread_id' => $threadId,
                    'tag' => $tag,
                    'score_total' => (string) $this->currentThreadScoreTotal($threadId),
                    'author_identity_id' => $authorIdentityId,
                    'viewer_is_approved' => $viewerIsApproved,
                    'wrote_record' => 'no',
                    'timings' => ['total' => $this->elapsedMilliseconds($totalStartedAt)],
                ];
            }

            $phaseStartedAt = hrtime(true);
            [$recordPath, $contents] = $this->buildThreadLabelRecord($threadId, [$tag], $authorIdentityId, $createdAt);
            $this->writeFile($recordPath, $contents);
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);

            $commitResult = $this->commitCanonicalWrite([$recordPath], 'Apply thread tag ' . $tag . ' to ' . $threadId);
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];

            $timings = array_merge($timings, $this->synchronizeThreadLabelDerivedState($threadId, $commitSha));

            $phaseStartedAt = hrtime(true);
            $this->invalidator()->invalidateBoardThread($threadId);
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            return [
                'status' => 'ok',
                'thread_id' => $threadId,
                'tag' => $tag,
                'score_total' => (string) $this->currentThreadScoreTotal($threadId),
                'author_identity_id' => $authorIdentityId,
                'viewer_is_approved' => $viewerIsApproved,
                'wrote_record' => 'yes',
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function applyPostTag(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);
            $postId = $this->requireAsciiToken((string) ($input['post_id'] ?? ''), 'post_id');
            $tag = $this->normalizeThreadTag((string) ($input['tag'] ?? ''));
            $authorIdentityId = $this->requireOpenPgpIdentityId((string) ($input['author_identity_id'] ?? ''), 'author_identity_id');
            $createdAt = $this->canonicalTimestampNow();

            $post = $this->canonicalRepository->loadPost(CanonicalPathResolver::post($postId));
            $threadId = $post->threadId ?? $post->postId;
            $viewerIsApproved = $this->isApprovedIdentity($authorIdentityId) ? 'yes' : 'no';
            if ($this->hasPostTagFromIdentity($postId, $tag, $authorIdentityId)) {
                $state = $this->currentPostReactionState($postId);

                return [
                    'status' => 'ok',
                    'post_id' => $postId,
                    'thread_id' => $threadId,
                    'tag' => $tag,
                    'post_score_total' => (string) $state['post_score_total'],
                    'approved_flag_count' => (string) $state['approved_flag_count'],
                    'is_hidden' => $state['is_hidden'] ? 'yes' : 'no',
                    'author_identity_id' => $authorIdentityId,
                    'viewer_is_approved' => $viewerIsApproved,
                    'wrote_record' => 'no',
                    'timings' => ['total' => $this->elapsedMilliseconds($totalStartedAt)],
                ];
            }

            $phaseStartedAt = hrtime(true);
            [$recordPath, $contents] = $this->buildPostReactionRecord($postId, [$tag], $authorIdentityId, $createdAt);
            $this->writeFile($recordPath, $contents);
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);

            $commitResult = $this->commitCanonicalWrite([$recordPath], 'Apply post tag ' . $tag . ' to ' . $postId);
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];

            $timings = array_merge($timings, $this->synchronizePostReactionDerivedState($postId, $commitSha));

            $phaseStartedAt = hrtime(true);
            if ($post->isReply()) {
                $this->invalidator()->invalidateReply($threadId, $postId);
            } else {
                $this->invalidator()->invalidateBoardThread($postId);
            }
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            $state = $this->currentPostReactionState($postId);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $threadId,
                'tag' => $tag,
                'post_score_total' => (string) $state['post_score_total'],
                'approved_flag_count' => (string) $state['approved_flag_count'],
                'is_hidden' => $state['is_hidden'] ? 'yes' : 'no',
                'author_identity_id' => $authorIdentityId,
                'viewer_is_approved' => $viewerIsApproved,
                'wrote_record' => 'yes',
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function linkIdentity(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $publicKey = $this->normalizeAsciiBody((string) ($input['public_key'] ?? ''), 'public_key');

            $inspected = $this->keyInspector->inspect($publicKey);
            $fingerprintUpper = $inspected['fingerprint'];
            $fingerprintLower = strtolower($fingerprintUpper);
            $username = $inspected['username'];
            $identityId = 'openpgp:' . $fingerprintLower;
            $postId = 'identity-openpgp-' . $fingerprintLower;

            $identityPath = 'records/identity/' . $postId . '.txt';
            if (is_file($this->repositoryRoot . '/' . $identityPath)) {
                throw new RuntimeException('Identity already exists for this fingerprint.');
            }

            [$bootstrapPostId, $bootstrapThreadId, $bootstrapPath] = $this->resolveBootstrapAnchor($input);

            $publicKeyPath = 'records/public-keys/openpgp-' . $fingerprintUpper . '.asc';
            $writtenPaths = [];
            if ($bootstrapPath !== null) {
                $writtenPaths[] = $bootstrapPath;
            }
            if (!is_file($this->repositoryRoot . '/' . $publicKeyPath)) {
                $this->writeFile($publicKeyPath, $publicKey);
                $writtenPaths[] = $publicKeyPath;
            }

            $contents = "Post-ID: {$postId}\n"
                . "Board-Tags: identity\n"
                . "Subject: identity bootstrap\n"
                . "Username: {$username}\n"
                . "Identity-ID: {$identityId}\n"
                . "Signer-Fingerprint: {$fingerprintUpper}\n"
                . "Bootstrap-By-Post: {$bootstrapPostId}\n"
                . "Bootstrap-By-Thread: {$bootstrapThreadId}\n"
                . "\n{$publicKey}";

            (new IdentityBootstrapRecordParser())->parse($contents);
            $this->writeFile($identityPath, $contents);
            $writtenPaths[] = $identityPath;
            $commitResult = $this->commitCanonicalWrite($writtenPaths, 'Link identity ' . $postId);
            $commitSha = $commitResult['commit_sha'];
            $timings = $this->synchronizeIdentityDerivedState($identityPath, $commitSha);
            $this->invalidator()->invalidateIdentityLink(
                'openpgp-' . $fingerprintLower,
                $bootstrapThreadId,
                $bootstrapPostId
            );

            return [
                'status' => 'ok',
                'identity_id' => $identityId,
                'profile_slug' => 'openpgp-' . $fingerprintLower,
                'username' => $username,
                'bootstrap_post_id' => $bootstrapPostId,
                'bootstrap_thread_id' => $bootstrapThreadId,
                'commit_sha' => $commitSha,
                'timings' => array_merge($commitResult['timings'], $timings),
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function approveUser(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);

            $approverIdentityId = $this->requireOpenPgpIdentityId((string) ($input['approver_identity_id'] ?? ''), 'approver_identity_id');
            $targetIdentityId = $this->requireOpenPgpIdentityId((string) ($input['target_identity_id'] ?? ''), 'target_identity_id');
            $targetProfileSlug = $this->requireAsciiToken((string) ($input['target_profile_slug'] ?? ''), 'target_profile_slug');
            $threadId = $this->requireAsciiToken((string) ($input['thread_id'] ?? ''), 'thread_id');
            $parentId = $this->requireAsciiToken((string) ($input['parent_id'] ?? ''), 'parent_id');

            if ($approverIdentityId === $targetIdentityId) {
                throw new RuntimeException('Self-approval is not allowed.');
            }

            $thread = $this->canonicalRepository->loadPost(CanonicalPathResolver::post($threadId));
            $parent = $this->canonicalRepository->loadPost(CanonicalPathResolver::post($parentId));
            $parentThreadId = $parent->threadId ?? $parent->postId;
            if ($thread->postId !== $threadId || $parentThreadId !== $threadId || $parent->postId !== $parentId) {
                throw new RuntimeException('Approval target bootstrap thread is invalid.');
            }

            $postId = $this->generateRecordId('reply');
            $createdAt = $this->canonicalTimestampNow();
            $contents = "Post-ID: {$postId}\n"
                . "Created-At: {$createdAt}\n"
                . "Board-Tags: identity approval\n"
                . "Thread-ID: {$threadId}\n"
                . "Parent-ID: {$parentId}\n"
                . "Author-Identity-ID: {$approverIdentityId}\n"
                . "\nApprove-Identity-ID: {$targetIdentityId}\n";

            $record = (new PostRecordParser())->parse($contents);
            $recordPath = CanonicalPathResolver::post($postId);
            $phaseStartedAt = hrtime(true);
            $this->writeFile($recordPath, $contents);
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);
            $commitResult = $this->commitCanonicalWrite(
                [$recordPath],
                'Approve user ' . $targetIdentityId . ' by ' . $approverIdentityId
            );
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];
            $timings = array_merge($timings, $this->synchronizeApprovalDerivedState($record, $commitSha));
            $phaseStartedAt = hrtime(true);
            $this->invalidator()->invalidateApproval($targetProfileSlug, $threadId, $parentId, $postId);
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $threadId,
                'target_identity_id' => $targetIdentityId,
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function setFeatureFlag(array $input): array
    {
        return $this->withTimedWriteLock(function () use ($input): array {
            $this->assertWritableRepository();
            $timings = [];
            $totalStartedAt = hrtime(true);
            $key = $this->requireFeatureFlagKey((string) ($input['key'] ?? ''));
            $requestedValue = $this->requireBooleanValue($input['value'] ?? null, 'value');
            $currentState = $this->featureFlags->evaluate($key);
            if ($currentState->source === 'environment') {
                throw new RuntimeException('feature flag is currently overridden by environment: ' . $key);
            }
            if ($currentState->siteError !== null) {
                throw new RuntimeException('feature flags record is invalid: ' . $currentState->siteError);
            }

            $recordPath = CanonicalPathResolver::featureFlags();
            $record = $this->canonicalRepository->loadFeatureFlags($recordPath);
            if (array_key_exists($key, $record->values) && $record->values[$key] === $requestedValue) {
                $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

                return [
                    'status' => 'ok',
                    'key' => $key,
                    'site_value' => $requestedValue ? 'true' : 'false',
                    'effective_value' => $currentState->effectiveValue ? 'true' : 'false',
                    'source' => $currentState->source,
                    'wrote_record' => 'no',
                    'timings' => $timings,
                ];
            }

            $values = $record->values;
            $values[$key] = $requestedValue;

            $phaseStartedAt = hrtime(true);
            $contents = $this->buildFeatureFlagsRecord($values);
            $this->writeFile($recordPath, $contents);
            $timings['write_file'] = $this->elapsedMilliseconds($phaseStartedAt);

            $commitResult = $this->commitCanonicalWrite([$recordPath], 'Set feature flag ' . $key . '=' . ($requestedValue ? 'true' : 'false'));
            $timings = array_merge($timings, $commitResult['timings']);
            $commitSha = $commitResult['commit_sha'];

            $phaseStartedAt = hrtime(true);
            $this->invalidator()->invalidateFeatureFlags();
            $timings['artifact_invalidate'] = $this->elapsedMilliseconds($phaseStartedAt);
            $timings['total'] = $this->elapsedMilliseconds($totalStartedAt);

            $updatedState = FeatureFlagEvaluator::forRepository($this->repositoryRoot)->evaluate($key);

            return [
                'status' => 'ok',
                'key' => $key,
                'site_value' => $requestedValue ? 'true' : 'false',
                'effective_value' => $updatedState->effectiveValue ? 'true' : 'false',
                'source' => $updatedState->source,
                'wrote_record' => 'yes',
                'commit_sha' => $commitSha,
                'timings' => $timings,
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    protected function rebuildReadModel(): array
    {
        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
            'write_refresh',
        );
        $builder->rebuild();

        return $builder->timings();
    }

    /**
     * @return array<string, float>
     */
    private function refreshDerivedStateAfterCommit(string $commitSha): array
    {
        try {
            $timings = $this->rebuildReadModel();
            $this->staleMarker()->clear();
            return $timings;
        } catch (\Throwable $throwable) {
            $this->staleMarker()->mark([
                'reason' => 'write_refresh_failed',
                'commit_sha' => $commitSha,
                'failed_at' => gmdate('c'),
                'message' => $throwable->getMessage(),
            ]);

            throw new RuntimeException(
                'Canonical write committed at ' . $commitSha . ' but read-model refresh failed. Derived state marked stale.'
            );
        }
    }

    /**
     * @return array<string, float>
     */
    private function synchronizePostDerivedState(PostRecord $record, string $commitSha, bool $hasThreadLabelWrite = false): array
    {
        $phaseStartedAt = hrtime(true);
        if (!$this->canIncrementallyUpdateReadModel()) {
            $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
            $timings = [
                'read_model_rebuild' => $this->elapsedMilliseconds($phaseStartedAt),
            ];
            foreach ($rebuildTimings as $name => $duration) {
                $timings['read_model_' . $name] = $duration;
            }

            return $timings;
        }

        try {
            $updater = $this->incrementalReadModelUpdater();
            $postTimings = $updater->applyPostWrite($record, $commitSha);
            if ($hasThreadLabelWrite) {
                $threadLabelTimings = $updater->applyThreadLabelWrite($record->threadId ?? $record->postId, $commitSha);
                $incrementalTimings = [];
                foreach ($postTimings as $name => $duration) {
                    $incrementalTimings['post_' . $name] = $duration;
                }
                foreach ($threadLabelTimings as $name => $duration) {
                    $incrementalTimings['thread_label_' . $name] = $duration;
                }
            } else {
                $incrementalTimings = $postTimings;
            }

            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $fallbackStartedAt = hrtime(true);
            try {
                $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
                $timings = [
                    'read_model_incremental_fallback' => $this->elapsedMilliseconds($phaseStartedAt),
                    'read_model_rebuild_fallback' => $this->elapsedMilliseconds($fallbackStartedAt),
                ];
                foreach ($rebuildTimings as $name => $duration) {
                    $timings['read_model_' . $name] = $duration;
                }

                return $timings;
            } catch (\Throwable $fallbackThrowable) {
                $this->staleMarker()->mark([
                    'reason' => 'write_refresh_failed',
                    'commit_sha' => $commitSha,
                    'failed_at' => gmdate('c'),
                    'message' => 'incremental=' . $throwable->getMessage() . '; fallback=' . $fallbackThrowable->getMessage(),
                ]);

                throw new RuntimeException(
                    'Canonical write committed at ' . $commitSha . ' but incremental read-model update and rebuild fallback both failed. Derived state marked stale.'
                );
            }
        }

        $timings = [
            'read_model_incremental_update' => $this->elapsedMilliseconds($phaseStartedAt),
        ];
        foreach ($incrementalTimings as $name => $duration) {
            $timings['read_model_incremental_' . $name] = $duration;
        }

        return $timings;
    }

    /**
     * @return array<string, float>
     */
    private function synchronizeThreadLabelDerivedState(string $threadId, string $commitSha): array
    {
        $phaseStartedAt = hrtime(true);
        if (!$this->canIncrementallyUpdateReadModel()) {
            $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
            $timings = [
                'read_model_rebuild' => $this->elapsedMilliseconds($phaseStartedAt),
            ];
            foreach ($rebuildTimings as $name => $duration) {
                $timings['read_model_' . $name] = $duration;
            }

            return $timings;
        }

        try {
            $incrementalTimings = $this->incrementalReadModelUpdater()->applyThreadLabelWrite($threadId, $commitSha);
            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $fallbackStartedAt = hrtime(true);
            try {
                $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
                $timings = [
                    'read_model_incremental_fallback' => $this->elapsedMilliseconds($phaseStartedAt),
                    'read_model_rebuild_fallback' => $this->elapsedMilliseconds($fallbackStartedAt),
                ];
                foreach ($rebuildTimings as $name => $duration) {
                    $timings['read_model_' . $name] = $duration;
                }

                return $timings;
            } catch (\Throwable $fallbackThrowable) {
                $this->staleMarker()->mark([
                    'reason' => 'write_refresh_failed',
                    'commit_sha' => $commitSha,
                    'failed_at' => gmdate('c'),
                    'message' => 'incremental=' . $throwable->getMessage() . '; fallback=' . $fallbackThrowable->getMessage(),
                ]);

                throw new RuntimeException(
                    'Canonical write committed at ' . $commitSha . ' but incremental thread-label update and rebuild fallback both failed. Derived state marked stale.'
                );
            }
        }

        $timings = [
            'read_model_incremental_update' => $this->elapsedMilliseconds($phaseStartedAt),
        ];
        foreach ($incrementalTimings as $name => $duration) {
            $timings['read_model_incremental_' . $name] = $duration;
        }

        return $timings;
    }

    /**
     * @return array<string, float>
     */
    private function synchronizePostReactionDerivedState(string $postId, string $commitSha): array
    {
        $phaseStartedAt = hrtime(true);
        if (!$this->canIncrementallyUpdateReadModel()) {
            $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
            $timings = [
                'read_model_rebuild' => $this->elapsedMilliseconds($phaseStartedAt),
            ];
            foreach ($rebuildTimings as $name => $duration) {
                $timings['read_model_' . $name] = $duration;
            }

            return $timings;
        }

        try {
            $incrementalTimings = $this->incrementalReadModelUpdater()->applyPostReactionWrite($postId, $commitSha);
            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $fallbackStartedAt = hrtime(true);
            try {
                $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
                $timings = [
                    'read_model_incremental_fallback' => $this->elapsedMilliseconds($phaseStartedAt),
                    'read_model_rebuild_fallback' => $this->elapsedMilliseconds($fallbackStartedAt),
                ];
                foreach ($rebuildTimings as $name => $duration) {
                    $timings['read_model_' . $name] = $duration;
                }

                return $timings;
            } catch (\Throwable $fallbackThrowable) {
                $this->staleMarker()->mark([
                    'reason' => 'write_refresh_failed',
                    'commit_sha' => $commitSha,
                    'failed_at' => gmdate('c'),
                    'message' => 'incremental=' . $throwable->getMessage() . '; fallback=' . $fallbackThrowable->getMessage(),
                ]);

                throw new RuntimeException(
                    'Canonical write committed at ' . $commitSha . ' but incremental post-reaction update and rebuild fallback both failed. Derived state marked stale.'
                );
            }
        }

        $timings = [
            'read_model_incremental_update' => $this->elapsedMilliseconds($phaseStartedAt),
        ];
        foreach ($incrementalTimings as $name => $duration) {
            $timings['read_model_incremental_' . $name] = $duration;
        }

        return $timings;
    }

    /**
     * @return array<string, float>
     */
    private function synchronizeIdentityDerivedState(string $identityPath, string $commitSha): array
    {
        $phaseStartedAt = hrtime(true);
        if (!$this->canIncrementallyUpdateReadModel()) {
            $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
            $timings = [
                'read_model_rebuild' => $this->elapsedMilliseconds($phaseStartedAt),
            ];
            foreach ($rebuildTimings as $name => $duration) {
                $timings['read_model_' . $name] = $duration;
            }

            return $timings;
        }

        $identity = $this->canonicalRepository->loadIdentity($identityPath);

        try {
            $incrementalTimings = $this->incrementalReadModelUpdater()->applyIdentityLink($identity, $commitSha);
            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $fallbackStartedAt = hrtime(true);
            try {
                $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
                $timings = [
                    'read_model_incremental_fallback' => $this->elapsedMilliseconds($phaseStartedAt),
                    'read_model_rebuild_fallback' => $this->elapsedMilliseconds($fallbackStartedAt),
                ];
                foreach ($rebuildTimings as $name => $duration) {
                    $timings['read_model_' . $name] = $duration;
                }

                return $timings;
            } catch (\Throwable $fallbackThrowable) {
                $this->staleMarker()->mark([
                    'reason' => 'write_refresh_failed',
                    'commit_sha' => $commitSha,
                    'failed_at' => gmdate('c'),
                    'message' => 'incremental=' . $throwable->getMessage() . '; fallback=' . $fallbackThrowable->getMessage(),
                ]);

                throw new RuntimeException(
                    'Canonical write committed at ' . $commitSha . ' but incremental identity update and rebuild fallback both failed. Derived state marked stale.'
                );
            }
        }

        $timings = [
            'read_model_incremental_update' => $this->elapsedMilliseconds($phaseStartedAt),
        ];
        foreach ($incrementalTimings as $name => $duration) {
            $timings['read_model_incremental_' . $name] = $duration;
        }

        return $timings;
    }

    /**
     * @return array<string, float>
     */
    private function synchronizeApprovalDerivedState(PostRecord $record, string $commitSha): array
    {
        $phaseStartedAt = hrtime(true);
        if (!$this->canIncrementallyUpdateReadModel()) {
            $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
            $timings = [
                'read_model_approval_rebuild' => $this->elapsedMilliseconds($phaseStartedAt),
            ];
            foreach ($rebuildTimings as $name => $duration) {
                $timings['read_model_' . $name] = $duration;
            }

            return $timings;
        }

        try {
            $incrementalTimings = $this->incrementalReadModelUpdater()->applyApprovalWrite($record, $commitSha);
            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $fallbackStartedAt = hrtime(true);
            try {
                $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
                $timings = [
                    'read_model_approval_incremental_fallback' => $this->elapsedMilliseconds($phaseStartedAt),
                    'read_model_approval_rebuild_fallback' => $this->elapsedMilliseconds($fallbackStartedAt),
                ];
                foreach ($rebuildTimings as $name => $duration) {
                    $timings['read_model_' . $name] = $duration;
                }

                return $timings;
            } catch (\Throwable $fallbackThrowable) {
                $this->staleMarker()->mark([
                    'reason' => 'write_refresh_failed',
                    'commit_sha' => $commitSha,
                    'failed_at' => gmdate('c'),
                    'message' => 'incremental=' . $throwable->getMessage() . '; fallback=' . $fallbackThrowable->getMessage(),
                ]);

                throw new RuntimeException(
                    'Canonical write committed at ' . $commitSha . ' but incremental approval update and rebuild fallback both failed. Derived state marked stale.'
                );
            }
        }

        $timings = [
            'read_model_approval_incremental_update' => $this->elapsedMilliseconds($phaseStartedAt),
        ];
        foreach ($incrementalTimings as $name => $duration) {
            $timings['read_model_approval_incremental_' . $name] = $duration;
        }

        return $timings;
    }

    protected function canIncrementallyUpdateReadModel(): bool
    {
        if (!is_file($this->databasePath) || $this->staleMarker()->exists()) {
            return false;
        }

        try {
            $pdo = (new ReadModelConnection($this->databasePath))->open();
            $metadata = [];
            foreach ($pdo->query('SELECT key, value FROM metadata')->fetchAll() as $row) {
                if (!is_array($row) || !isset($row['key'], $row['value'])) {
                    continue;
                }

                $metadata[(string) $row['key']] = (string) $row['value'];
            }

            return ($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION
                && ($metadata['repository_root'] ?? null) === $this->repositoryRoot;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
    {
        return new IncrementalReadModelUpdater($this->databasePath(), $this->repositoryRoot());
    }

    protected function repositoryRoot(): string
    {
        return $this->repositoryRoot;
    }

    protected function databasePath(): string
    {
        return $this->databasePath;
    }

    private function readModelPdo(): \PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
    }

    /**
     * @param list<string> $relativePaths
     */
    private function commitCanonicalWrite(array $relativePaths, string $message): array
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            throw new RuntimeException('Writable repository must be a git checkout before writes are allowed.');
        }

        $timings = [];
        $phaseStartedAt = hrtime(true);
        $this->runGitCommand(array_merge(['add', '--'], $relativePaths), 'Unable to stage canonical write');
        $timings['git_add'] = $this->elapsedMilliseconds($phaseStartedAt);
        $phaseStartedAt = hrtime(true);
        $this->runGitCommand([
            '-c', 'user.name=Forum Rewrite',
            '-c', 'user.email=forum-rewrite@example.invalid',
            'commit', '-m', $message,
        ], 'Unable to commit canonical write');
        $timings['git_commit'] = $this->elapsedMilliseconds($phaseStartedAt);
        $phaseStartedAt = hrtime(true);
        $commitSha = trim($this->runGitCommand(['rev-parse', 'HEAD'], 'Unable to read commit SHA'));
        $timings['git_rev_parse'] = $this->elapsedMilliseconds($phaseStartedAt);

        return [
            'commit_sha' => $commitSha,
            'timings' => $timings,
        ];
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $path = $this->repositoryRoot . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $bytes = file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new RuntimeException('Unable to write canonical file: ' . $relativePath);
        }
    }

    private function assertWritableRepository(): void
    {
        if (str_contains($this->repositoryRoot, '/tests/fixtures/')) {
            throw new RuntimeException('Write APIs are disabled against the committed fixture repository. Initialize a local writable copy and set FORUM_REPOSITORY_ROOT.');
        }
    }

    private function invalidator(): StaticArtifactInvalidator
    {
        return new StaticArtifactInvalidator($this->artifactRoot);
    }

    private function staleMarker(): ReadModelStaleMarker
    {
        return new ReadModelStaleMarker($this->databasePath);
    }

    private function executionLock(): ExecutionLock
    {
        return new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock');
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function withTimedWriteLock(callable $callback): array
    {
        $locked = $this->executionLock()->withExclusiveLockTimed($callback);
        $result = $locked['result'];
        $timings = isset($result['timings']) && is_array($result['timings'])
            ? $result['timings']
            : [];
        $result['timings'] = array_merge($locked['timings'], $timings);

        return $result;
    }

    /**
     * @param list<string> $args
     */
    private function runGitCommand(array $args, string $failureMessage): string
    {
        $command = 'git';
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($this->repositoryRoot) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException($failureMessage . ': ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function generateRecordId(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, gmdate('YmdHis'), substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function canonicalTimestampNow(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return list<string>
     */
    private function extractThreadLabelsFromBody(string $body): array
    {
        $labels = [];
        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }

            if (preg_match_all('/(^|[^A-Za-z0-9-])#([A-Za-z0-9-]+)/', $line, $matches) < 1) {
                continue;
            }

            foreach ($matches[2] as $label) {
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $label)) {
                    continue;
                }

                if (!in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @param list<string> $labels
     * @return array{string,string}
     */
    private function buildThreadLabelRecord(string $threadId, array $labels, ?string $authorIdentityId, string $createdAt): array
    {
        $recordId = $this->generateRecordId('thread-label');
        $contents = "Record-ID: {$recordId}\n"
            . "Created-At: {$createdAt}\n"
            . "Thread-ID: {$threadId}\n"
            . "Operation: add\n"
            . 'Labels: ' . implode(' ', $labels) . "\n"
            . ($authorIdentityId !== null ? "Author-Identity-ID: {$authorIdentityId}\n" : '')
            . "\n";

        (new ThreadLabelRecordParser())->parse($contents);

        return [
            CanonicalPathResolver::threadLabel($recordId),
            $contents,
        ];
    }

    private function buildPostReactionRecord(string $postId, array $tags, ?string $authorIdentityId, string $createdAt): array
    {
        $recordId = $this->generateRecordId('post-reaction');
        $contents = "Record-ID: {$recordId}\n"
            . "Created-At: {$createdAt}\n"
            . "Post-ID: {$postId}\n"
            . "Operation: add\n"
            . 'Tags: ' . implode(' ', $tags) . "\n"
            . ($authorIdentityId !== null ? "Author-Identity-ID: {$authorIdentityId}\n" : '')
            . "\n";

        (new PostReactionRecordParser())->parse($contents);

        return [
            CanonicalPathResolver::postReaction($recordId),
            $contents,
        ];
    }

    private function normalizeThreadTag(string $value): string
    {
        $tag = strtolower(trim($value));
        if (!TagScore::isScoredTag($tag)) {
            throw new RuntimeException('tag must be one of: ' . implode(', ', array_keys(TagScore::scoredTags())));
        }

        return $tag;
    }

    private function requireFeatureFlagKey(string $key): string
    {
        $key = trim($key);
        $definition = (new FeatureFlagRegistry())->get($key);
        if ($definition === null) {
            throw new RuntimeException('unknown feature flag: ' . $key);
        }
        if (!$definition->siteMutable) {
            throw new RuntimeException('feature flag is not mutable from site content: ' . $key);
        }

        return $key;
    }

    private function requireBooleanValue(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true') {
                return true;
            }
            if ($normalized === 'false') {
                return false;
            }
            if ($normalized === 'enabled') {
                return true;
            }
            if ($normalized === 'disabled') {
                return false;
            }
        }

        throw new RuntimeException($field . ' must be true or false.');
    }

    /**
     * @param array<string, bool> $values
     */
    private function buildFeatureFlagsRecord(array $values): string
    {
        ksort($values);
        $contents = "Schema: " . SiteFeatureFlagsRecordParser::SCHEMA . "\n"
            . 'Updated-At: ' . $this->canonicalTimestampNow() . "\n\n";
        foreach ($values as $key => $value) {
            $contents .= $key . ': ' . ($value ? 'true' : 'false') . "\n";
        }

        (new SiteFeatureFlagsRecordParser())->parse($contents);

        return $contents;
    }

    private function isApprovedIdentity(string $identityId): bool
    {
        $stmt = $this->readModelPdo()->prepare('SELECT is_approved FROM profiles WHERE identity_id = :identity_id');
        $stmt->execute(['identity_id' => $identityId]);
        $value = $stmt->fetchColumn();

        return ((int) $value) === 1;
    }

    private function hasThreadTagFromIdentity(string $threadId, string $tag, string $identityId): bool
    {
        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            $relativePath = 'records/thread-labels/' . basename($path);
            $record = $this->canonicalRepository->loadThreadLabel($relativePath);
            if ($record->threadId !== $threadId || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->labels, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasPostTagFromIdentity(string $postId, string $tag, string $identityId): bool
    {
        foreach (glob($this->repositoryRoot . '/records/post-reactions/*.txt') ?: [] as $path) {
            $relativePath = 'records/post-reactions/' . basename($path);
            $record = $this->canonicalRepository->loadPostReaction($relativePath);
            if ($record->postId !== $postId || $record->authorIdentityId !== $identityId) {
                continue;
            }

            if (in_array($tag, $record->tags, true)) {
                return true;
            }
        }

        return false;
    }

    private function currentThreadScoreTotal(string $threadId): int
    {
        $stmt = $this->readModelPdo()->prepare('SELECT score_total FROM threads WHERE root_post_id = :thread_id');
        $stmt->execute(['thread_id' => $threadId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new RuntimeException('thread_id does not resolve to a known thread.');
        }

        return (int) $value;
    }

    /**
     * @return array{post_score_total:int,approved_flag_count:int,is_hidden:bool}
     */
    private function currentPostReactionState(string $postId): array
    {
        $stmt = $this->readModelPdo()->prepare(
            'SELECT post_score_total, approved_flag_count, is_hidden
             FROM posts
             WHERE post_id = :post_id'
        );
        $stmt->execute(['post_id' => $postId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('post_id does not resolve to a known post.');
        }

        return [
            'post_score_total' => (int) $row['post_score_total'],
            'approved_flag_count' => (int) $row['approved_flag_count'],
            'is_hidden' => ((int) $row['is_hidden']) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{string,string,?string}
     */
    private function resolveBootstrapAnchor(array $input): array
    {
        $providedBootstrapPostId = trim((string) ($input['bootstrap_post_id'] ?? ''));
        if ($providedBootstrapPostId !== '') {
            $bootstrapPostId = $this->requireAsciiToken($providedBootstrapPostId, 'bootstrap_post_id');
            $bootstrapPost = $this->canonicalRepository->loadPost(CanonicalPathResolver::post($bootstrapPostId));

            return [$bootstrapPostId, $bootstrapPost->threadId ?? $bootstrapPost->postId, null];
        }

        $bootstrapPostId = $this->generateRecordId('bootstrap');
        $createdAt = $this->canonicalTimestampNow();
        $contents = "Post-ID: {$bootstrapPostId}\n"
            . "Created-At: {$createdAt}\n"
            . 'Board-Tags: ' . self::HIDDEN_BOOTSTRAP_BOARD_TAGS . "\n"
            . "Subject: account bootstrap\n"
            . "\nAutomatic account bootstrap anchor.\n";

        (new PostRecordParser())->parse($contents);
        $bootstrapPath = CanonicalPathResolver::post($bootstrapPostId);
        $this->writeFile($bootstrapPath, $contents);

        return [$bootstrapPostId, $bootstrapPostId, $bootstrapPath];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveAuthorIdentityId(array $input): ?string
    {
        $value = trim((string) ($input['author_identity_id'] ?? ''));
        if ($value === '') {
            return null;
        }

        return $this->requireOpenPgpIdentityId($value, 'author_identity_id');
    }

    private function requireOpenPgpIdentityId(string $value, string $field): string
    {
        $identityId = $this->requireAsciiToken($value, $field);
        if (!str_starts_with($identityId, 'openpgp:')) {
            throw new RuntimeException("{$field} must use the retained openpgp identity form.");
        }

        $fingerprint = substr($identityId, strlen('openpgp:'));
        if ($fingerprint === '' || preg_match('/[^a-f0-9]/', $fingerprint)) {
            throw new RuntimeException("{$field} must use a lowercase OpenPGP fingerprint.");
        }

        $identityPath = $this->repositoryRoot . '/' . CanonicalPathResolver::identity($fingerprint);
        if (!is_file($identityPath)) {
            throw new RuntimeException("{$field} does not resolve to a known identity.");
        }

        return $identityId;
    }

    private function normalizeBoardTags(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9 -]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'general';
    }

    private function normalizeAsciiLine(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[^\x20-\x7E]/', $value)) {
            throw new RuntimeException("{$field} must be printable ASCII.");
        }

        return $value;
    }

    private function normalizeAuthoredLine(string $value, string $field): string
    {
        if ($this->featureFlags->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT)) {
            return (new UnicodeTextPolicy())->normalizeLine($value, $field);
        }

        return $this->normalizeAsciiLine($value, $field);
    }

    private function requireAsciiToken(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[^A-Za-z0-9._:-]/', $value)) {
            throw new RuntimeException("{$field} is required and must be an ASCII token.");
        }

        return $value;
    }

    private function normalizeAsciiBody(string $value, string $field): string
    {
        $value = str_replace("\r\n", "\n", trim($value));
        if ($value === '') {
            throw new RuntimeException("{$field} is required.");
        }

        if (preg_match('/[^\x0A\x20-\x7E]/', $value)) {
            throw new RuntimeException("{$field} must be ASCII only.");
        }

        return $value . "\n";
    }

    private function normalizeAuthoredBody(string $value, string $field): string
    {
        if ($this->featureFlags->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT)) {
            return (new UnicodeTextPolicy())->normalizeBody($value, $field);
        }

        return $this->normalizeAsciiBody($value, $field);
    }
}
