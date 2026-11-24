<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use PDO;

/**
 * Which approved keys may read an account's private messages.
 *
 * Approval lets a key post as its username. Private access needs more: the key is the
 * username's first key or a root seed (rule 1), was approved by a same-username key that
 * itself has private access (rule 2), was approved by a root-approved operator (rule 3), or
 * was later confirmed by a key meeting rule 2 or 3 (rule 4, added with confirm records).
 * Every other approved key is "public only".
 *
 * Retired keys still count as links in a chain (a key approved by an old, now-retired phone
 * keeps access), but only active approved keys have access themselves.
 *
 * Plan: docs/plans/private_key_access/private_key_access_step3_development_plan.md
 */
final class PrivateKeyAccess
{
    public const ROOT = 'root';
    public const FIRST = 'first';
    public const OWN_ACCOUNT = 'own_account';
    public const OPERATOR = 'operator';
    public const CONFIRMED = 'confirmed';
    public const PUBLIC_ONLY = 'public_only';

    /** @var array<string, array<string, string>> username token => identity => reason */
    private array $reasonsByUsername = [];
    /** @var array<string, array<string, mixed>|null> */
    private array $profileCache = [];
    /** @var array<string, true>|null */
    private ?array $columns = null;
    /** @var \Closure(string): (array{classification:string,target_username:string}|null|false) */
    private \Closure $receiptFor;
    /** @var \Closure(string): bool */
    private \Closure $isOperator;
    /** @var \Closure(string): list<string> */
    private \Closure $confirmersFor;

    /**
     * @param (callable(string): (array{classification:string,target_username:string}|null|false))|null $receiptFor
     *        Receipt facts for a target key: null when none exists, false when one exists but is invalid.
     * @param (callable(string): bool)|null $isOperator Whether a key is a root-approved operator.
     * @param (callable(string): list<string>)|null $confirmersFor Keys that signed a confirm record for a target.
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?string $repositoryRoot = null,
        ?callable $receiptFor = null,
        ?callable $isOperator = null,
        ?callable $confirmersFor = null,
    ) {
        $this->receiptFor = $receiptFor !== null ? \Closure::fromCallable($receiptFor) : fn (string $id) => $this->repositoryReceipt($id);
        $this->isOperator = $isOperator !== null ? \Closure::fromCallable($isOperator)
            : fn (string $id): bool => $this->repositoryRoot !== null && ApprovalPolicy::isOperator($this->repositoryRoot, $id);
        $this->confirmersFor = $confirmersFor !== null ? \Closure::fromCallable($confirmersFor) : static fn (string $id): array => [];
    }

    public function hasAccess(string $identityId): bool
    {
        return $this->reason($identityId) !== self::PUBLIC_ONLY;
    }

    /** Why a key has private access, or PUBLIC_ONLY (also for unknown, pending and retired keys). */
    public function reason(string $identityId): string
    {
        $profile = $this->profile($identityId);
        if ($profile === null || !self::isActiveApproved($profile)) {
            return self::PUBLIC_ONLY;
        }

        return $this->reasonsFor((string) $profile['username_token'])[$identityId] ?? self::PUBLIC_ONLY;
    }

    /**
     * Active approved keys of a username that have private access.
     *
     * @return list<array<string, mixed>>
     */
    public function keysFor(string $usernameToken): array
    {
        $usernameToken = strtolower(trim($usernameToken));
        $reasons = $this->reasonsFor($usernameToken);

        return array_values(array_filter(
            $this->candidates($usernameToken),
            static fn (array $profile): bool => self::isActiveApproved($profile) && isset($reasons[(string) $profile['identity_id']]),
        ));
    }

    /**
     * Active approved keys of a username that are public only, waiting for a confirm.
     *
     * @return list<array<string, mixed>>
     */
    public function waitingFor(string $usernameToken): array
    {
        $usernameToken = strtolower(trim($usernameToken));
        $reasons = $this->reasonsFor($usernameToken);

        return array_values(array_filter(
            $this->candidates($usernameToken),
            static fn (array $profile): bool => self::isActiveApproved($profile) && !isset($reasons[(string) $profile['identity_id']]),
        ));
    }

    /**
     * Every active approved key with its reason, for the operator report.
     *
     * @return list<array{username_token:string,identity_id:string,profile_slug:string,approved_by_identity_id:?string,reason:string}>
     */
    public function report(): array
    {
        $rows = [];
        $tokens = $this->pdo->query("SELECT DISTINCT username_token FROM profiles WHERE is_approved = 1 AND username_token <> '' ORDER BY username_token")
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tokens as $token) {
            $reasons = $this->reasonsFor((string) $token);
            foreach ($this->candidates((string) $token) as $profile) {
                if (!self::isActiveApproved($profile)) {
                    continue;
                }
                $id = (string) $profile['identity_id'];
                $rows[] = [
                    'username_token' => (string) $token,
                    'identity_id' => $id,
                    'profile_slug' => (string) $profile['profile_slug'],
                    'approved_by_identity_id' => $profile['approved_by_identity_id'] ?? null,
                    'reason' => $reasons[$id] ?? self::PUBLIC_ONLY,
                ];
            }
        }

        return $rows;
    }

    /** @return array<string, string> identity => reason, for keys with private access (active or retired). */
    private function reasonsFor(string $usernameToken): array
    {
        if ($usernameToken === '') {
            return [];
        }
        if (isset($this->reasonsByUsername[$usernameToken])) {
            return $this->reasonsByUsername[$usernameToken];
        }

        $candidates = [];
        foreach ($this->candidates($usernameToken) as $profile) {
            $candidates[(string) $profile['identity_id']] = $profile;
        }

        $reasons = [];
        // Rule 1: root seeds and the username's first key.
        foreach ($candidates as $id => $profile) {
            if (($this->isOperator)($id)) {
                $reasons[$id] = self::ROOT;
            }
        }
        foreach ($this->firstKeys($usernameToken, $candidates) as $id) {
            $reasons[$id] ??= self::FIRST;
        }

        // Rules 2–4, to a fixed point: an approver or confirmer counts only once it has access itself.
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($candidates as $id => $profile) {
                if (isset($reasons[$id])) {
                    continue;
                }
                $approver = (string) ($profile['approved_by_identity_id'] ?? '');
                $reason = null;
                if ($approver !== '' && $approver !== $id && isset($reasons[$approver])) {
                    $reason = self::OWN_ACCOUNT;
                } elseif ($approver !== '' && $approver !== $id && ($this->isOperator)($approver)) {
                    $reason = self::OPERATOR;
                } else {
                    foreach (($this->confirmersFor)($id) as $confirmer) {
                        if ($confirmer !== $id && (isset($reasons[$confirmer]) || ($this->isOperator)($confirmer))) {
                            $reason = self::CONFIRMED;
                            break;
                        }
                    }
                }
                if ($reason !== null) {
                    $reasons[$id] = $reason;
                    $changed = true;
                }
            }
        }

        return $this->reasonsByUsername[$usernameToken] = $reasons;
    }

    /**
     * Rule 1. A key renamed into the username never counts as its first key.
     * With receipts: keys whose receipt says new_account under this username. Without any such
     * receipt: the earliest-created key that has no receipt at all (legacy grants). A key with a
     * cross-account, own-account or invalid receipt is never a first key.
     *
     * @param array<string, array<string, mixed>> $candidates
     * @return list<string>
     */
    private function firstKeys(string $usernameToken, array $candidates): array
    {
        $unchanged = array_filter($candidates, static function (array $profile) use ($usernameToken): bool {
            $bootstrap = (string) ($profile['bootstrap_username_token'] ?? '');
            return $bootstrap === '' || $bootstrap === $usernameToken;
        });
        if (count($candidates) === 1 && count($unchanged) === 1) {
            return array_keys($unchanged);
        }

        $newAccount = [];
        $legacy = [];
        foreach ($unchanged as $id => $profile) {
            $receipt = ($this->receiptFor)($id);
            if ($receipt === null) {
                $legacy[$id] = $profile;
            } elseif (is_array($receipt) && $receipt['classification'] === 'new_account'
                && strtolower($receipt['target_username']) === $usernameToken) {
                $newAccount[] = $id;
            }
        }
        if ($newAccount !== []) {
            return $newAccount;
        }
        if ($legacy === []) {
            return [];
        }

        $created = $this->bootstrapTimes(array_keys($legacy));
        uksort($legacy, static function (string $a, string $b) use ($created, $legacy): int {
            $timeA = $created[$a] ?? "\u{10FFFF}";
            $timeB = $created[$b] ?? "\u{10FFFF}";
            return [$timeA, (string) $legacy[$a]['profile_slug']] <=> [$timeB, (string) $legacy[$b]['profile_slug']];
        });

        return [array_key_first($legacy)];
    }

    /** @return list<array<string, mixed>> approved or once-approved profiles of a username */
    private function candidates(string $usernameToken): array
    {
        $hasRetirement = $this->hasColumn('retired_at') && $this->hasColumn('was_approved');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM profiles WHERE username_token = :token AND (is_approved = 1'
            . ($hasRetirement ? ' OR (retired_at IS NOT NULL AND was_approved = 1)' : '')
            . ') ORDER BY profile_slug'
        );
        $stmt->execute(['token' => $usernameToken]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($this->repositoryRoot !== null) {
            $rows = array_map(fn (array $row): array => KeyRetirement::profile($this->repositoryRoot, $row), $rows);
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function profile(string $identityId): ?array
    {
        if (!array_key_exists($identityId, $this->profileCache)) {
            $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE identity_id = :id');
            $stmt->execute(['id' => $identityId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && $this->repositoryRoot !== null) {
                $row = KeyRetirement::profile($this->repositoryRoot, $row);
            }
            $this->profileCache[$identityId] = $row === false ? null : $row;
        }

        return $this->profileCache[$identityId];
    }

    /** @param array<string, mixed> $profile */
    private static function isActiveApproved(array $profile): bool
    {
        return (int) ($profile['is_approved'] ?? 0) === 1 && empty($profile['retired_at']);
    }

    /**
     * @param list<string> $identityIds
     * @return array<string, string> identity => bootstrap post created_at
     */
    private function bootstrapTimes(array $identityIds): array
    {
        try {
            $times = [];
            $stmt = $this->pdo->prepare(
                'SELECT posts.created_at FROM profiles JOIN posts ON posts.post_id = profiles.bootstrap_post_id WHERE profiles.identity_id = :id'
            );
            foreach ($identityIds as $id) {
                $stmt->execute(['id' => $id]);
                $value = $stmt->fetchColumn();
                if (is_string($value)) {
                    $times[$id] = $value;
                }
            }
            return $times;
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * The acceptance receipt for the grant that approved a key, found among the posts that
     * reply to its bootstrap post. Null when no receipt exists; false when one is invalid.
     *
     * @return array{classification:string,target_username:string}|null|false
     */
    private function repositoryReceipt(string $identityId): array|null|false
    {
        if ($this->repositoryRoot === null) {
            return null;
        }
        $profile = $this->profile($identityId);
        if ($profile === null) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT post_id FROM posts WHERE thread_id = :thread AND parent_id = :parent ORDER BY created_at, post_id');
            $stmt->execute(['thread' => $profile['bootstrap_thread_id'], 'parent' => $profile['bootstrap_post_id']]);
            $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException) {
            return null;
        }
        $found = null;
        foreach ($postIds as $postId) {
            try {
                if (ApprovalAcceptance::existingPaths($this->repositoryRoot, (string) $postId) === []) {
                    continue;
                }
                $receipt = ApprovalAcceptance::read($this->repositoryRoot, ApprovalAcceptance::grantPath($this->repositoryRoot, (string) $postId));
            } catch (\Throwable) {
                $found ??= false;
                continue;
            }
            $context = is_array($receipt) ? ($receipt['context'] ?? null) : null;
            if (!is_array($context) || ($context['target_identity_id'] ?? null) !== $identityId) {
                continue;
            }
            return [
                'classification' => (string) ($context['classification'] ?? ''),
                'target_username' => (string) ($context['target_username'] ?? ''),
            ];
        }

        return $found;
    }

    private function hasColumn(string $column): bool
    {
        if ($this->columns === null) {
            $this->columns = [];
            foreach ($this->pdo->query('PRAGMA table_info(profiles)')->fetchAll(PDO::FETCH_ASSOC) as $info) {
                $this->columns[(string) ($info['name'] ?? '')] = true;
            }
        }

        return isset($this->columns[$column]);
    }
}
