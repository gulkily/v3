<?php

declare(strict_types=1);

namespace ForumRewrite\Codex;

use PDO;
use RuntimeException;

final class CodexHandoffStore
{
    private const STATUSES = [
        'requested' => true,
        'draft_ready' => true,
        'approved' => true,
        'rejected' => true,
        'running' => true,
        'failed' => true,
        'completed' => true,
    ];

    /**
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        'requested' => ['draft_ready', 'rejected', 'failed'],
        'draft_ready' => ['approved', 'rejected', 'failed'],
        'approved' => ['running', 'rejected', 'failed'],
        'running' => ['completed', 'failed'],
        'rejected' => [],
        'failed' => [],
        'completed' => [],
    ];

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureSchema();
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $viewerProfile
     * @return array<string, mixed>
     */
    public function requestForPost(array $post, array $viewerProfile): array
    {
        $postId = trim((string) ($post['post_id'] ?? ''));
        if ($postId === '') {
            throw new RuntimeException('Codex handoff requires an origin post.');
        }

        $contentHash = $this->contentHashForPost($post);
        $existing = $this->findByOrigin($postId, $contentHash);
        if ($existing !== null) {
            $existing['requested'] = false;
            return $existing;
        }

        $now = gmdate('c');
        $row = [
            'handoff_id' => $this->newHandoffId($now),
            'origin_post_id' => $postId,
            'origin_thread_id' => (string) ($post['thread_id'] ?? $postId),
            'origin_content_hash' => $contentHash,
            'requester_identity_id' => (string) ($viewerProfile['identity_id'] ?? ''),
            'requester_profile_slug' => (string) ($viewerProfile['profile_slug'] ?? ''),
            'requester_username' => (string) ($viewerProfile['username'] ?? ''),
            'status' => 'requested',
            'user_story' => null,
            'fdp_step1' => null,
            'confidence_summary' => null,
            'draft_text' => null,
            'status_context_json' => $this->encode([]),
            'requested_at' => $now,
            'draft_ready_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'running_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'updated_at' => $now,
        ];

        $this->insert($row);
        $stored = $this->findByHandoffId($row['handoff_id']) ?? $this->hydrate($row);
        $stored['requested'] = true;
        $this->recordEvent($stored, 'requested', []);

        return $stored;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByHandoffId(string $handoffId): ?array
    {
        $stmt = $this->pdo->prepare($this->selectSql() . ' WHERE handoff_id = :handoff_id');
        $stmt->execute(['handoff_id' => $handoffId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>|null
     */
    public function findByPost(array $post): ?array
    {
        $postId = trim((string) ($post['post_id'] ?? ''));
        if ($postId === '') {
            return null;
        }

        return $this->findByOrigin($postId, $this->contentHashForPost($post));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function updateStatus(string $handoffId, string $status, array $context = []): array
    {
        if (!isset(self::STATUSES[$status])) {
            throw new RuntimeException('Unknown Codex handoff status: ' . $status);
        }

        $existing = $this->findByHandoffId($handoffId);
        if ($existing === null) {
            throw new RuntimeException('Codex handoff not found.');
        }

        $currentStatus = (string) $existing['status'];
        $statusChanged = $status !== $currentStatus;
        if ($statusChanged && !in_array($status, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new RuntimeException('Invalid Codex handoff status transition: ' . $currentStatus . ' to ' . $status);
        }

        $now = gmdate('c');
        $statusColumn = match ($status) {
            'draft_ready' => 'draft_ready_at',
            'approved' => 'approved_at',
            'rejected' => 'rejected_at',
            'running' => 'running_at',
            'completed' => 'completed_at',
            'failed' => 'failed_at',
            default => null,
        };

        $mergedContext = is_array($existing['status_context'] ?? null) ? $existing['status_context'] : [];
        if ($context !== []) {
            $mergedContext[$status] = $context;
        }

        $values = [
            'handoff_id' => $handoffId,
            'status' => $status,
            'user_story' => array_key_exists('user_story', $context) ? (string) $context['user_story'] : $existing['user_story'],
            'fdp_step1' => array_key_exists('fdp_step1', $context) ? (string) $context['fdp_step1'] : $existing['fdp_step1'],
            'confidence_summary' => array_key_exists('confidence_summary', $context) ? (string) $context['confidence_summary'] : $existing['confidence_summary'],
            'draft_text' => array_key_exists('draft_text', $context) ? (string) $context['draft_text'] : $existing['draft_text'],
            'status_context_json' => $this->encode($mergedContext),
            'updated_at' => $now,
        ];
        $timestampAssignments = '';
        if ($statusColumn !== null) {
            $timestampAssignments = ', ' . $statusColumn . ' = COALESCE(' . $statusColumn . ', :status_at)';
            $values['status_at'] = $now;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE codex_handoffs
             SET status = :status,
                 user_story = :user_story,
                 fdp_step1 = :fdp_step1,
                 confidence_summary = :confidence_summary,
                 draft_text = :draft_text,
                 status_context_json = :status_context_json,
                 updated_at = :updated_at'
                . $timestampAssignments
                . ' WHERE handoff_id = :handoff_id'
        );
        $stmt->execute($values);

        $updated = $this->findByHandoffId($handoffId) ?? [];
        if ($statusChanged && $updated !== []) {
            $this->recordEvent($updated, $status, $context);
        }

        return $updated;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimNextApproved(int $limit = 1): array
    {
        $limit = max(1, $limit);

        return $this->withImmediateTransaction(function () use ($limit): array {
            $stmt = $this->pdo->prepare(
                $this->selectSql()
                . ' WHERE status = :status ORDER BY approved_at ASC, id ASC LIMIT :limit'
            );
            $stmt->bindValue('status', 'approved');
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $claimed = [];
            foreach ($stmt->fetchAll() as $row) {
                $handoffId = (string) ($row['handoff_id'] ?? '');
                if ($handoffId === '') {
                    continue;
                }

                $claimed[] = $this->updateStatus($handoffId, 'running', [
                    'runner' => 'codex exec',
                ]);
            }

            return $claimed;
        });
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS codex_handoffs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                handoff_id TEXT NOT NULL UNIQUE,
                origin_post_id TEXT NOT NULL,
                origin_thread_id TEXT NOT NULL,
                origin_content_hash TEXT NOT NULL,
                requester_identity_id TEXT NOT NULL,
                requester_profile_slug TEXT NOT NULL,
                requester_username TEXT NOT NULL,
                status TEXT NOT NULL,
                user_story TEXT NULL,
                fdp_step1 TEXT NULL,
                confidence_summary TEXT NULL,
                draft_text TEXT NULL,
                status_context_json TEXT NOT NULL DEFAULT \'{}\',
                requested_at TEXT NOT NULL,
                draft_ready_at TEXT NULL,
                approved_at TEXT NULL,
                rejected_at TEXT NULL,
                running_at TEXT NULL,
                completed_at TEXT NULL,
                failed_at TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(origin_post_id, origin_content_hash)
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS codex_handoffs_status_updated_idx
             ON codex_handoffs (status, updated_at)'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS codex_handoffs_origin_post_idx
             ON codex_handoffs (origin_post_id)'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS codex_handoff_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                handoff_id TEXT NOT NULL,
                event_status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                origin_post_id TEXT NOT NULL,
                origin_thread_id TEXT NOT NULL,
                label TEXT NOT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_username_token TEXT NULL,
                author_label TEXT NOT NULL,
                author_is_approved INTEGER NOT NULL DEFAULT 1,
                context_json TEXT NOT NULL DEFAULT \'{}\'
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS codex_handoff_events_recent_idx
             ON codex_handoff_events (created_at DESC, id DESC)'
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO codex_handoffs (
                handoff_id, origin_post_id, origin_thread_id, origin_content_hash,
                requester_identity_id, requester_profile_slug, requester_username, status,
                user_story, fdp_step1, confidence_summary, draft_text, status_context_json,
                requested_at, draft_ready_at, approved_at, rejected_at, running_at,
                completed_at, failed_at, updated_at
             ) VALUES (
                :handoff_id, :origin_post_id, :origin_thread_id, :origin_content_hash,
                :requester_identity_id, :requester_profile_slug, :requester_username, :status,
                :user_story, :fdp_step1, :confidence_summary, :draft_text, :status_context_json,
                :requested_at, :draft_ready_at, :approved_at, :rejected_at, :running_at,
                :completed_at, :failed_at, :updated_at
             )'
        );
        $stmt->execute($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByOrigin(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->selectSql() . ' WHERE origin_post_id = :origin_post_id AND origin_content_hash = :origin_content_hash'
        );
        $stmt->execute([
            'origin_post_id' => $postId,
            'origin_content_hash' => $contentHash,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    private function selectSql(): string
    {
        return 'SELECT id, handoff_id, origin_post_id, origin_thread_id, origin_content_hash,
                    requester_identity_id, requester_profile_slug, requester_username, status,
                    user_story, fdp_step1, confidence_summary, draft_text, status_context_json,
                    requested_at, draft_ready_at, approved_at, rejected_at, running_at,
                    completed_at, failed_at, updated_at
                FROM codex_handoffs';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'handoff_id' => (string) ($row['handoff_id'] ?? ''),
            'origin_post_id' => (string) ($row['origin_post_id'] ?? ''),
            'origin_thread_id' => (string) ($row['origin_thread_id'] ?? ''),
            'origin_content_hash' => (string) ($row['origin_content_hash'] ?? ''),
            'requester_identity_id' => (string) ($row['requester_identity_id'] ?? ''),
            'requester_profile_slug' => (string) ($row['requester_profile_slug'] ?? ''),
            'requester_username' => (string) ($row['requester_username'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'user_story' => isset($row['user_story']) ? (string) $row['user_story'] : null,
            'fdp_step1' => isset($row['fdp_step1']) ? (string) $row['fdp_step1'] : null,
            'confidence_summary' => isset($row['confidence_summary']) ? (string) $row['confidence_summary'] : null,
            'draft_text' => isset($row['draft_text']) ? (string) $row['draft_text'] : null,
            'status_context' => $this->decode((string) ($row['status_context_json'] ?? '{}')),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
            'draft_ready_at' => isset($row['draft_ready_at']) ? (string) $row['draft_ready_at'] : null,
            'approved_at' => isset($row['approved_at']) ? (string) $row['approved_at'] : null,
            'rejected_at' => isset($row['rejected_at']) ? (string) $row['rejected_at'] : null,
            'running_at' => isset($row['running_at']) ? (string) $row['running_at'] : null,
            'completed_at' => isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            'failed_at' => isset($row['failed_at']) ? (string) $row['failed_at'] : null,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private function contentHashForPost(array $post): string
    {
        if (isset($post['content_hash']) && (string) $post['content_hash'] !== '') {
            return (string) $post['content_hash'];
        }

        return hash('sha256', (string) ($post['subject'] ?? '') . "\n" . (string) ($post['body'] ?? ''));
    }

    private function newHandoffId(string $now): string
    {
        return 'codex-handoff-' . gmdate('YmdHis', strtotime($now) ?: time()) . '-' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, mixed> $handoff
     * @param array<string, mixed> $context
     */
    private function recordEvent(array $handoff, string $status, array $context): void
    {
        $now = gmdate('c');
        $author = $this->eventAuthor($handoff, $status, $context);
        $event = [
            'handoff_id' => (string) ($handoff['handoff_id'] ?? ''),
            'event_status' => $status,
            'created_at' => $now,
            'origin_post_id' => (string) ($handoff['origin_post_id'] ?? ''),
            'origin_thread_id' => (string) ($handoff['origin_thread_id'] ?? ''),
            'label' => $this->eventLabel($handoff, $status),
            'author_identity_id' => $author['identity_id'],
            'author_profile_slug' => $author['profile_slug'],
            'author_username_token' => $this->usernameToken($author['username']),
            'author_label' => $author['username'],
            'author_is_approved' => 1,
            'context_json' => $this->encode($context),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO codex_handoff_events (
                handoff_id, event_status, created_at, origin_post_id, origin_thread_id,
                label, author_identity_id, author_profile_slug, author_username_token,
                author_label, author_is_approved, context_json
             ) VALUES (
                :handoff_id, :event_status, :created_at, :origin_post_id, :origin_thread_id,
                :label, :author_identity_id, :author_profile_slug, :author_username_token,
                :author_label, :author_is_approved, :context_json
             )'
        );
        $stmt->execute($event);
        $this->insertActivityEvent($event);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function insertActivityEvent(array $event): void
    {
        if (!$this->tableExists('activity')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved,
                source_path, source_commit_sha
             ) VALUES (
                :created_at, :kind, :post_id, :thread_id, :label, :board_tags_json,
                :author_identity_id, :author_profile_slug, :author_username_token, :author_label, :author_is_approved,
                NULL, NULL
             )'
        );
        $stmt->execute([
            'created_at' => (string) $event['created_at'],
            'kind' => 'codex_handoff',
            'post_id' => (string) $event['origin_post_id'],
            'thread_id' => (string) $event['origin_thread_id'],
            'label' => (string) $event['label'],
            'board_tags_json' => '["codex","handoff"]',
            'author_identity_id' => $event['author_identity_id'],
            'author_profile_slug' => $event['author_profile_slug'],
            'author_username_token' => $event['author_username_token'],
            'author_label' => (string) $event['author_label'],
            'author_is_approved' => (int) $event['author_is_approved'],
        ]);
    }

    /**
     * @param array<string, mixed> $handoff
     * @param array<string, mixed> $context
     * @return array{identity_id:?string,profile_slug:?string,username:string}
     */
    private function eventAuthor(array $handoff, string $status, array $context): array
    {
        $prefix = $status === 'approved' ? 'approved_by' : ($status === 'rejected' ? 'rejected_by' : '');
        if ($prefix !== '') {
            return [
                'identity_id' => isset($context[$prefix . '_identity_id']) ? (string) $context[$prefix . '_identity_id'] : null,
                'profile_slug' => isset($context[$prefix . '_profile_slug']) ? (string) $context[$prefix . '_profile_slug'] : null,
                'username' => isset($context[$prefix . '_username']) ? (string) $context[$prefix . '_username'] : 'approved user',
            ];
        }

        return [
            'identity_id' => (string) ($handoff['requester_identity_id'] ?? '') ?: null,
            'profile_slug' => (string) ($handoff['requester_profile_slug'] ?? '') ?: null,
            'username' => (string) ($handoff['requester_username'] ?? '') ?: 'approved user',
        ];
    }

    /**
     * @param array<string, mixed> $handoff
     */
    private function eventLabel(array $handoff, string $status): string
    {
        $postId = (string) ($handoff['origin_post_id'] ?? '');
        return match ($status) {
            'requested' => 'Requested Codex handoff for post ' . $postId,
            'draft_ready' => 'Codex handoff draft ready for post ' . $postId,
            'approved' => 'Approved Codex handoff for post ' . $postId,
            'rejected' => 'Rejected Codex handoff for post ' . $postId,
            'running' => 'Started Codex handoff for post ' . $postId,
            'completed' => 'Completed Codex handoff for post ' . $postId,
            'failed' => 'Codex handoff failed for post ' . $postId,
            default => 'Updated Codex handoff for post ' . $postId,
        };
    }

    private function usernameToken(string $username): ?string
    {
        $token = strtolower(trim($username));
        $token = preg_replace('/[^a-z0-9]+/', '-', $token) ?? '';
        $token = trim($token, '-');

        return $token !== '' ? $token : null;
    }

    private function encode(mixed $value): string
    {
        return json_encode(is_array($value) ? $value : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withImmediateTransaction(callable $callback): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $callback();
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $throwable) {
            $this->pdo->exec('ROLLBACK');
            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
