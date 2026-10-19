<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Canonical\AgentResponseMetadata as Metadata;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\TaskQueue\AgentReplyTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\Write\LocalWriteService;
use PDO;
use InvalidArgumentException;
use Throwable;

/** Shares the reply lifecycle while keeping expansion authorization and source binding explicit. */
final class ScriptureExpansionService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $repositoryRoot,
        private readonly string $queuePath,
        private readonly mixed $fetchPost,
        private readonly mixed $contextForPost,
        private readonly mixed $generator,
        private readonly LocalWriteService $writer,
        private readonly AgentIdentityService $identity,
        private readonly bool $requestsEnabled,
        private readonly mixed $dispatch = null,
        private readonly bool $verificationRequired = false,
    ) {}

    public function sources(string $replyId): array
    {
        $initial = ($this->fetchPost)($replyId);
        if (!$this->visible($initial) || ($initial['author_label'] ?? '') !== AgentIdentityService::USERNAME) {
            throw new ExpansionSourceException('This response is not available for expansion.');
        }
        $meta = Metadata::decode($initial['agent_response_json'] ?? null, $initial['parent_id'] ?? null);
        $sourceId = (string) ($initial['parent_id'] ?? '');
        if ($meta === null) {
            $this->store(); // Older responses may predate the disposable request table.
            $stmt = $this->pdo->prepare('SELECT request_context_json, target_post_id, target_content_hash FROM post_generated_responses WHERE agent_post_id = :id');
            $stmt->execute(['id' => $replyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $stored = $row ? json_decode($row['request_context_json'], true) : [];
            $task = AgentResponseTask::fromStoredRequestContext(is_array($stored) ? $stored : []);
            if ($task['type'] !== AgentResponseTask::SCRIPTURE_ORIENTED_ANALYSIS_TYPE || ($row['target_post_id'] ?? '') !== $sourceId) {
                throw new ExpansionSourceException('Only verified scripture responses can be expanded.');
            }
        } elseif ($meta['type'] !== Metadata::SCRIPTURE || $meta['source_post_id'] !== $sourceId) {
            throw new ExpansionSourceException('Expansions cannot themselves be expanded.');
        }
        $source = ($this->fetchPost)($sourceId);
        $thread = ($this->fetchPost)((string) $initial['thread_id']);
        if (!$this->visible($source) || !$this->visible($thread) || $source['thread_id'] !== $initial['thread_id']) {
            throw new ExpansionSourceException('The source discussion is unavailable.');
        }
        if (($meta !== null && $meta['source_hash'] !== Metadata::postHash($source))
            || ($meta === null && $row['target_content_hash'] !== ($this->contextForPost)($source)['content_hash'])) {
            throw new ExpansionSourceException('The source changed; request fresh scripture analysis.');
        }
        return [$initial, $source, [
            'type' => Metadata::EXPANSION, 'source_post_id' => $sourceId, 'source_hash' => Metadata::postHash($source),
            'expansion_of' => $replyId, 'initial_hash' => Metadata::postHash($initial),
        ]];
    }

    private function visible(?array $post): bool
    {
        return $post !== null && empty($post['is_hidden']) && ($post['retracted_at'] ?? null) === null;
    }

    private function store(): SqliteAgentReplyGenerationStore { return new SqliteAgentReplyGenerationStore($this->pdo); }

    public function requestExpansion(string $replyId, array $viewer): array
    {
        if (!$this->requestsEnabled || (int) ($viewer['is_approved'] ?? 0) !== 1) {
            throw new ExpansionSourceException('An approved member with agent requests enabled is required.');
        }
        [$initial, , $meta] = $this->sources($replyId);
        $context = ($this->contextForPost)($initial);
        $context['analysis_hash'] = 'task:' . hash('sha256', json_encode($meta, JSON_THROW_ON_ERROR));
        // Canonical identity is stable across rebuilds and all requesters.
        if (is_file($this->repositoryRoot . '/' . CanonicalPathResolver::post(Metadata::expansionId($replyId)))) {
            return $this->status($replyId);
        }
        $row = $this->store()->requestForTarget($context, [
            'requested_by_identity_id' => (string) ($viewer['identity_id'] ?? ''),
            'agent_response_task' => AgentResponseTask::forPost($context, AgentResponseTask::SCRIPTURE_EXPANSION_TYPE),
            'scripture_expansion' => $meta,
        ]);
        if ($row['status'] === 'requested') {
            $this->enqueue($replyId, (string) $context['content_hash']);
        }
        return $this->status($replyId);
    }

    private function enqueue(string $replyId, string $hash): void
    {
        if (!is_dir(dirname($this->queuePath))) { mkdir(dirname($this->queuePath), 0700, true); }
        $queued = (new SqliteTaskQueueStore(new PDO('sqlite:' . $this->queuePath)))->enqueue(
            SqliteTaskQueueStore::AGENT_REPLY, AgentReplyTask::deduplicationKey($replyId, $hash));
        if (($queued['enqueued'] ?? false) && $this->dispatch !== null) {
            ($this->dispatch)((int) $queued['id'], $replyId);
        }
    }

    public function retry(string $replyId, string $hash): array
    {
        if (!$this->requestsEnabled) { throw new ExpansionSourceException('Agent requests are disabled.'); }
        [$initial, , $meta] = $this->sources($replyId);
        if (($this->contextForPost)($initial)['content_hash'] !== $hash) {
            throw new ExpansionSourceException('Initial response changed.');
        }
        $row = $this->store()->findByTarget($replyId, $hash);
        if (($row['request_context']['agent_reply_request']['scripture_expansion'] ?? null) !== $meta) {
            throw new ExpansionSourceException('Expansion source versions changed.');
        }
        return $this->store()->retryRequestedForTarget($replyId, $hash);
    }

    public function status(string $replyId): array
    {
        [$initial, , $meta] = $this->sources($replyId);
        $context = ($this->contextForPost)($initial);
        $published = ($this->fetchPost)(Metadata::expansionId($replyId));
        $result = ['status' => 'ok', 'post_id' => $replyId, 'generation_status' => 'available'];
        if ($this->visible($published)) {
            $stored = Metadata::decode($published['agent_response_json'] ?? null, $published['parent_id'] ?? null);
            if ($stored === $meta && $published['author_identity_id'] === $initial['author_identity_id']) {
                return array_merge($result, ['generation_status' => 'generated', 'agent_post_id' => $published['post_id'], 'expansion_body' => $published['body']]);
            }
            return array_merge($result, ['generation_status' => 'not_recommended', 'reason' => 'source_changed']);
        }
        // A hidden/retracted publication must not cause a replacement to be generated.
        if (is_file($this->repositoryRoot . '/' . CanonicalPathResolver::post(Metadata::expansionId($replyId)))) {
            return array_merge($result, ['generation_status' => 'not_recommended', 'reason' => 'expansion_unavailable']);
        }
        $row = $this->store()->findByTarget($replyId, (string) $context['content_hash']);
        if ($row === null) { return $result; }
        $state = match ($row['status']) {
            'requested' => 'requested', 'pending', 'complete', 'posting' => 'in_progress',
            'posted' => 'not_recommended', 'failed' => 'failed', 'skipped' => 'not_recommended', default => 'available',
        };
        return array_merge($result, ['generation_status' => $state, 'failure_code' => $row['failure_code'],
            'failure_message' => AgentReplyFailure::message((string) ($row['failure_code'] ?? ''))]);
    }

    public function fulfill(array $row): array
    {
        $id = (string) $row['target_post_id']; $hash = (string) $row['target_content_hash'];
        try {
            [$initial, $source, $meta] = $this->sources($id);
            $expected = $row['request_context']['agent_reply_request']['scripture_expansion'] ?? null;
            if ($meta !== $expected || ($this->contextForPost)($initial)['content_hash'] !== $hash) {
                throw new ExpansionSourceException('Expansion source versions changed.');
            }
            $verify = function () use ($id, $meta, $row): void {
                if (!$this->requestsEnabled || $this->sources($id)[2] !== $meta) {
                    throw new ExpansionSourceException('Expansion source or permissions changed.');
                }
                $requester = (string) ($row['request_context']['agent_reply_request']['requested_by_identity_id'] ?? '');
                $stmt = $this->pdo->prepare('SELECT is_approved FROM profiles WHERE identity_id = ?');
                $stmt->execute([$requester]); $approved = $stmt->fetchColumn(); $stmt->closeCursor();
                if ((int) $approved !== 1) { throw new ExpansionSourceException('Expansion requester is no longer approved.'); }
            };
            $verify();
            $path = CanonicalPathResolver::post(Metadata::expansionId($id));
            $canonical = is_file($this->repositoryRoot . '/' . $path)
                ? (new CanonicalRecordRepository($this->repositoryRoot))->loadPost($path) : null;
            $stored = $this->store()->findByTarget($id, $hash);
            if ($canonical !== null) {
                if ($canonical->agentResponse !== $meta) { throw new ExpansionSourceException('Published expansion is stale.'); }
                $generation = ['response_text' => $canonical->body, 'response_intent' => AgentResponseTask::SCRIPTURE_EXPANSION_TYPE];
            } elseif (($stored['status'] ?? '') === 'complete' || ($stored['status'] ?? '') === 'posting') {
                $generation = $stored;
            } else {
                $generator = ($this->generator)();
                if ($generator === null) { throw new ExpansionSourceException('Reply provider is not configured.'); }
                $input = AgentResponseTask::generationInputForType(($this->contextForPost)($initial), AgentResponseTask::SCRIPTURE_EXPANSION_TYPE);
                $input['original_post'] = (string) $source['body'];
                $input['initial_answer'] = (string) $initial['body'];
                $input['participants'] = (new AgentParticipantContext($this->pdo, $this->repositoryRoot))->resolve(
                    $source, $row['request_context']['agent_reply_request']['requested_by_identity_id'] ?? null,
                );
                $generation = $generator->generate($input);
                $generation['response_intent'] = AgentResponseTask::SCRIPTURE_EXPANSION_TYPE;
            }
            if ($canonical === null) {
                $validationGenerator = ($this->generator)();
                if ($validationGenerator === null && ($this->verificationRequired || isset($generation['raw_response']['scripture_verification']))) {
                    throw new ScriptureVerificationException('Publication validation requires the configured reply provider.');
                }
                $input = AgentResponseTask::generationInputForType(($this->contextForPost)($initial), AgentResponseTask::SCRIPTURE_EXPANSION_TYPE);
                $input['original_post'] = (string) $source['body'];
                $input['initial_answer'] = (string) $initial['body'];
                $sourceVerify = $verify;
                $verify = function () use ($sourceVerify, $validationGenerator, $generation, $input): void {
                    $sourceVerify();
                    $validationGenerator?->validatePublication($generation, $input);
                };
                $verify();
            }
            $context = $row['request_context'];
            $stored = $this->store()->saveComplete($context, $generation);
            $verify();
            ScriptureTiming::record(dirname(__DIR__, 3), $id, 'publication_started');
            $identity = $this->identity->ensureReplyAgentIdentity();
            if ($identity['identity_id'] !== $initial['author_identity_id']) {
                throw new ExpansionSourceException('Expansion agent identity differs from initial author.');
            }
            $this->store()->reservePosting($id, $hash);
            $write = $this->writer->createReply([
                'thread_id' => $initial['thread_id'], 'parent_id' => $id,
                'body' => $canonical?->body ?? $stored['response_text'], 'author_identity_id' => $identity['identity_id'],
            ], $meta, $verify);
            $this->store()->markPosted($id, $hash, $write['post_id'], $identity['identity_id'], $identity['profile_slug']);
            ScriptureTiming::record(dirname(__DIR__, 3), $id, 'publication_finished');
            return ['status' => 'ok', 'generation_status' => 'generated', 'agent_post_id' => $write['post_id']];
        } catch (ExpansionSourceException $error) {
            $this->store()->markSkipped($id, $hash, 'expansion_source_unavailable');
            return ['status' => 'ok', 'generation_status' => 'not_recommended', 'reason' => 'expansion_source_unavailable'];
        } catch (Throwable $error) {
            $code = AgentReplyFailure::code($error);
            $this->store()->saveFailed($id, $hash, (string) ($row['analysis_hash'] ?? ''), $code, $error->getMessage());
            return ['status' => 'ok', 'generation_status' => 'failed', 'failure_code' => $code];
        }
    }
}
