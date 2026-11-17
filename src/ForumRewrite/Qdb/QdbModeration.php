<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\Security\ApprovalPolicy;
use ForumRewrite\Security\FeatureFlagSwitchOn;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;

/**
 * The qdb quote queue. With the queue on, a quote created at or after the first
 * operator-signed switch-on waits until an active operator tags it `approved`;
 * an operator's `rejected` tag unlists any quote. Operator quotes and news never wait.
 * Pending quotes stay public on the Queue page and their permalink only.
 */
final class QdbModeration
{
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const PENDING = 'pending';
    public const TAGS = [self::APPROVED, self::REJECTED];

    private static ?string $repositoryRoot = null;
    private static ?self $current = null;
    private static ?string $currentSite = null;
    private static bool $overridden = false;

    /** @param list<string> $operatorIds */
    public function __construct(
        public readonly ?string $startedAt,
        public readonly array $operatorIds = [],
    ) {
    }

    public static function inactive(): self
    {
        return new self(null);
    }

    /** Called once per process with the canonical repository the listings read. */
    public static function useRepository(string $repositoryRoot): void
    {
        if (self::$repositoryRoot !== $repositoryRoot) {
            self::$repositoryRoot = $repositoryRoot;
            if (!self::$overridden) {
                self::$current = null;
            }
        }
    }

    /** Tests and builders may set the state directly; null resets to the repository's. */
    public static function override(?self $moderation): void
    {
        self::$current = $moderation;
        self::$overridden = $moderation !== null;
    }

    public static function current(): self
    {
        $site = SiteProfileRegistry::active()['name'];
        if (self::$current === null || (!self::$overridden && self::$currentSite !== $site)) {
            self::$current = self::$repositoryRoot === null ? self::inactive() : self::forRepository(self::$repositoryRoot);
            self::$currentSite = $site;
        }

        return self::$current;
    }

    public static function forRepository(string $repositoryRoot): self
    {
        if (SiteProfileRegistry::active()['name'] !== 'qdb'
            || !FeatureFlagEvaluator::forRepository($repositoryRoot)->isEnabled(FeatureFlagRegistry::QDB_QUEUE_ENABLED)) {
            return self::inactive();
        }
        $switchOn = FeatureFlagSwitchOn::earliest($repositoryRoot, FeatureFlagRegistry::QDB_QUEUE_ENABLED);
        if ($switchOn === null) {
            // Without a signed switch-on there is no start time: nothing waits.
            return self::inactive();
        }

        return new self($switchOn['at'], self::activeOperators($repositoryRoot));
    }

    /** @return list<string> */
    public static function activeOperators(string $repositoryRoot): array
    {
        $operators = [];
        foreach (glob($repositoryRoot . '/records/approval-seeds/openpgp-*.txt') ?: [] as $path) {
            $identityId = 'openpgp:' . substr(basename($path, '.txt'), strlen('openpgp-'));
            if (ApprovalPolicy::isActiveOperator($repositoryRoot, $identityId)) {
                $operators[] = $identityId;
            }
        }
        sort($operators);

        return $operators;
    }

    /** @return list<string> Lowercase fingerprints, for the page to decide before paint who sees operator controls. */
    public function operatorFingerprints(): array
    {
        return array_values(array_map(static fn (string $id): string => substr($id, strlen('openpgp:')), array_filter(
            $this->operatorIds,
            static fn (string $id): bool => preg_match('/^openpgp:[a-f0-9]{40}$/D', $id) === 1,
        )));
    }

    public function isActive(): bool
    {
        return $this->startedAt !== null;
    }

    public function isOperator(string $identityId): bool
    {
        return in_array($identityId, $this->operatorIds, true);
    }

    /**
     * SQL condition, empty when inactive, that keeps pending and rejected quotes out of a
     * listing. `$postsAlias` names a `posts` row; replies and other threads are unaffected.
     */
    public function listedSqlSuffix(string $postsAlias = 'posts'): string
    {
        if (!$this->isActive()) {
            return '';
        }

        return ' AND NOT (' . $this->quoteSql($postsAlias) . ' AND (' . $this->taggedSql($postsAlias, self::REJECTED)
            . ' OR ' . $this->waitingSql($postsAlias) . '))';
    }

    /** SQL condition for quotes on the Queue page: waiting and not rejected. Never true when inactive. */
    public function queueSql(string $postsAlias = 'posts'): string
    {
        if (!$this->isActive()) {
            return '0';
        }

        return '(' . $this->quoteSql($postsAlias) . ' AND NOT ' . $this->taggedSql($postsAlias, self::REJECTED)
            . ' AND ' . $this->waitingSql($postsAlias) . ')';
    }

    /** approved, pending or rejected for one root post; approved when inactive or not a quote. */
    public function state(PDO $pdo, string $postId): string
    {
        if (!$this->isActive()) {
            return self::APPROVED;
        }
        $statement = $pdo->prepare('SELECT CASE WHEN NOT ' . $this->quoteSql('posts') . ' THEN \'approved\''
            . ' WHEN ' . $this->taggedSql('posts', self::REJECTED) . ' THEN \'rejected\''
            . ' WHEN ' . $this->waitingSql('posts') . ' THEN \'pending\' ELSE \'approved\' END'
            . ' FROM posts WHERE posts.post_id = ?');
        $statement->execute([$postId]);
        $state = $statement->fetchColumn();
        $statement->closeCursor();

        return is_string($state) ? $state : self::APPROVED;
    }

    private function quoteSql(string $alias): string
    {
        return "$alias.post_id GLOB '*-qdb-[0-9]*'";
    }

    private function waitingSql(string $alias): string
    {
        return '(' . $alias . '.created_at >= ' . self::literal((string) $this->startedAt)
            . ' AND COALESCE(' . $alias . '.author_identity_id, \'\') NOT IN (' . $this->operatorList() . ')'
            . ' AND instr(COALESCE(' . $alias . '.board_tags_json, \'\'), \'"news"\') = 0'
            . ' AND NOT ' . $this->taggedSql($alias, self::APPROVED) . ')';
    }

    private function taggedSql(string $alias, string $tag): string
    {
        if ($this->operatorIds === []) {
            return '0';
        }

        // Uncorrelated, so SQLite builds the tagged set once per query instead of probing per row.
        return "$alias.post_id IN (SELECT qdb_moderation.target_id FROM viewer_tags qdb_moderation"
            . " WHERE qdb_moderation.record_family = 'thread-labels' AND qdb_moderation.tag = " . self::literal($tag)
            . ' AND qdb_moderation.author_identity_id IN (' . $this->operatorList() . '))';
    }

    private function operatorList(): string
    {
        $values = [];
        foreach ($this->operatorIds as $identityId) {
            if (preg_match('/^openpgp:([a-f0-9]{40})$/D', $identityId, $match) !== 1) {
                continue;
            }
            // Label records may store either notation of the same key.
            $values[] = self::literal($identityId);
            $values[] = self::literal('openpgp-' . $match[1]);
        }

        // No identity is '-', so an empty operator list matches no author.
        return $values === [] ? "'-'" : implode(', ', $values);
    }

    private static function literal(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
