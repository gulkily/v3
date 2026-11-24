<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Security\PrivateKeyAccess;

final class PrivateKeyAccessTest
{
    /** @var array<string, array{classification:string,target_username:string}|false> */
    private array $receipts = [];
    /** @var array<string, true> */
    private array $operators = [];
    /** @var array<string, list<string>> */
    private array $confirmations = [];

    public function testSingleKeyIsTheFirstKey(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');

        $access = $this->access($pdo);
        assertSame(PrivateKeyAccess::FIRST, $access->reason('openpgp:ilya1'));
        assertSame(['openpgp:ilya1'], array_column($access->keysFor('ILYAG'), 'identity_id'));
    }

    public function testCrossAccountKeyIsPublicOnly(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:bob', 'bob', null);
        $this->operators['openpgp:bob'] = true;
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');
        $this->add($pdo, 'openpgp:mallory', 'ilyag', 'openpgp:carol');
        $this->add($pdo, 'openpgp:carol', 'carol', 'openpgp:bob');
        $this->receipts['openpgp:ilya1'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:mallory'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];

        $access = $this->access($pdo);
        assertTrue($access->hasAccess('openpgp:ilya1'));
        assertFalse($access->hasAccess('openpgp:mallory'));
        assertSame(PrivateKeyAccess::PUBLIC_ONLY, $access->reason('openpgp:mallory'));
        assertSame(['openpgp:ilya1'], array_column($access->keysFor('ilyag'), 'identity_id'));
        assertSame(['openpgp:mallory'], array_column($access->waitingFor('ilyag'), 'identity_id'));
    }

    public function testOwnDeviceAndSessionBootstrapKeepAccess(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');
        $this->add($pdo, 'openpgp:ilya2', 'ilyag', 'openpgp:ilya1');
        $this->add($pdo, 'openpgp:ilya3', 'ilyag', 'openpgp:ilya2');
        $this->receipts['openpgp:ilya1'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:ilya2'] = ['classification' => 'own_account_key', 'target_username' => 'ilyag'];
        // A session bootstrap records the vouching session's key as the approver.
        $this->receipts['openpgp:ilya3'] = ['classification' => 'own_account_key', 'target_username' => 'ilyag'];

        $access = $this->access($pdo);
        assertSame(PrivateKeyAccess::OWN_ACCOUNT, $access->reason('openpgp:ilya2'));
        assertSame(PrivateKeyAccess::OWN_ACCOUNT, $access->reason('openpgp:ilya3'));
        assertSame([], $access->waitingFor('ilyag'));
    }

    public function testChainedFakeIsPublicOnly(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');
        $this->add($pdo, 'openpgp:fake1', 'ilyag', 'openpgp:carol');
        $this->add($pdo, 'openpgp:fake2', 'ilyag', 'openpgp:fake1');
        $this->receipts['openpgp:ilya1'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:fake1'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:fake2'] = ['classification' => 'own_account_key', 'target_username' => 'ilyag'];

        $access = $this->access($pdo);
        assertFalse($access->hasAccess('openpgp:fake1'));
        assertFalse($access->hasAccess('openpgp:fake2'));
        assertSame(['openpgp:fake1', 'openpgp:fake2'], array_column($access->waitingFor('ilyag'), 'identity_id'));
    }

    public function testOperatorApprovalAndRootSeedHaveAccess(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:root', 'ilyag', null);
        $this->operators['openpgp:root'] = true;
        $this->add($pdo, 'openpgp:op', 'opal', 'openpgp:root');
        $this->operators['openpgp:op'] = true;
        $this->add($pdo, 'openpgp:ilya2', 'ilyag', 'openpgp:op');
        $this->receipts['openpgp:ilya2'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];

        $access = $this->access($pdo);
        assertSame(PrivateKeyAccess::ROOT, $access->reason('openpgp:root'));
        assertSame(PrivateKeyAccess::OPERATOR, $access->reason('openpgp:ilya2'));
    }

    public function testRetiredApproverStillVouchesButRetiredKeyHasNoAccess(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:oldphone', 'ilyag', 'openpgp:bob', retired: true);
        $this->add($pdo, 'openpgp:newphone', 'ilyag', 'openpgp:oldphone');
        $this->receipts['openpgp:oldphone'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:newphone'] = ['classification' => 'own_account_key', 'target_username' => 'ilyag'];

        $access = $this->access($pdo);
        assertFalse($access->hasAccess('openpgp:oldphone'));
        assertSame(PrivateKeyAccess::OWN_ACCOUNT, $access->reason('openpgp:newphone'));
        assertSame(['openpgp:newphone'], array_column($access->keysFor('ilyag'), 'identity_id'));
    }

    public function testKeyRenamedIntoTheNameIsNeverTheFirstKey(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:renamed', 'ilyag', 'openpgp:bob', bootstrapUsername: 'mallory');

        assertFalse($this->access($pdo)->hasAccess('openpgp:renamed'));
    }

    public function testLegacyKeysWithoutReceiptsUseTheEarliestKey(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:later', 'ilyag', 'openpgp:bob', createdAt: '2026-09-02T00:00:00Z');
        $this->add($pdo, 'openpgp:earlier', 'ilyag', 'openpgp:bob', createdAt: '2026-09-01T00:00:00Z');

        $access = $this->access($pdo);
        assertSame(PrivateKeyAccess::FIRST, $access->reason('openpgp:earlier'));
        assertFalse($access->hasAccess('openpgp:later'));
    }

    public function testInvalidReceiptIsNeverTheFirstKey(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:legit', 'ilyag', 'openpgp:bob', createdAt: '2026-09-02T00:00:00Z');
        $this->add($pdo, 'openpgp:broken', 'ilyag', 'openpgp:bob', createdAt: '2026-09-01T00:00:00Z');
        $this->receipts['openpgp:broken'] = false;

        $access = $this->access($pdo);
        assertFalse($access->hasAccess('openpgp:broken'));
        assertTrue($access->hasAccess('openpgp:legit'));
    }

    public function testConfirmByAPrivateKeyGivesAccess(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');
        $this->add($pdo, 'openpgp:laptop', 'ilyag', 'openpgp:carol');
        $this->add($pdo, 'openpgp:fake', 'ilyag', 'openpgp:carol');
        $this->receipts['openpgp:ilya1'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:laptop'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:fake'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];
        $this->confirmations['openpgp:laptop'] = ['openpgp:ilya1'];
        // A public-only key cannot confirm another.
        $this->confirmations['openpgp:fake'] = ['openpgp:laptop2'];

        $access = $this->access($pdo);
        assertSame(PrivateKeyAccess::CONFIRMED, $access->reason('openpgp:laptop'));
        assertFalse($access->hasAccess('openpgp:fake'));
    }

    public function testPendingAndUnknownKeysArePublicOnly(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:pending', 'ilyag', null, approved: false);

        $access = $this->access($pdo);
        assertFalse($access->hasAccess('openpgp:pending'));
        assertFalse($access->hasAccess('openpgp:nobody'));
        assertSame([], $access->keysFor('ilyag'));
    }

    public function testReportListsEveryApprovedKeyWithItsReason(): void
    {
        $pdo = $this->database();
        $this->add($pdo, 'openpgp:ilya1', 'ilyag', 'openpgp:bob');
        $this->add($pdo, 'openpgp:mallory', 'ilyag', 'openpgp:carol');
        $this->add($pdo, 'openpgp:carol', 'carol', 'openpgp:bob');
        $this->receipts['openpgp:ilya1'] = ['classification' => 'new_account', 'target_username' => 'ilyag'];
        $this->receipts['openpgp:mallory'] = ['classification' => 'cross_account_key', 'target_username' => 'ilyag'];

        $rows = $this->access($pdo)->report();
        $reasons = array_combine(array_column($rows, 'identity_id'), array_column($rows, 'reason'));
        assertSame(['openpgp:carol' => 'first', 'openpgp:ilya1' => 'first', 'openpgp:mallory' => 'public_only'], $reasons);
    }

    private function access(PDO $pdo): PrivateKeyAccess
    {
        return new PrivateKeyAccess(
            $pdo,
            null,
            fn (string $id) => $this->receipts[$id] ?? null,
            fn (string $id): bool => isset($this->operators[$id]),
            fn (string $id): array => $this->confirmations[$id] ?? [],
        );
    }

    private function database(): PDO
    {
        $this->receipts = [];
        $this->operators = [];
        $this->confirmations = [];
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE profiles (
            identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
            username_token TEXT NOT NULL, bootstrap_post_id TEXT NOT NULL, bootstrap_thread_id TEXT NOT NULL,
            public_key TEXT NOT NULL, is_approved INTEGER NOT NULL DEFAULT 0, was_approved INTEGER NOT NULL DEFAULT 0,
            retired_at TEXT NULL, bootstrap_username_token TEXT NOT NULL DEFAULT \'\', approved_by_identity_id TEXT NULL
        )');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, created_at TEXT NOT NULL, thread_id TEXT NOT NULL, parent_id TEXT NULL)');

        return $pdo;
    }

    private function add(
        PDO $pdo,
        string $id,
        string $username,
        ?string $approvedBy,
        bool $approved = true,
        bool $retired = false,
        ?string $bootstrapUsername = null,
        string $createdAt = '2026-10-01T00:00:00Z',
    ): void {
        $slug = str_replace(':', '-', $id);
        $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, bootstrap_post_id,
                bootstrap_thread_id, public_key, is_approved, was_approved, retired_at, bootstrap_username_token, approved_by_identity_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $id, $slug, $username, strtolower($username), 'identity-' . $slug, 'thread-' . $slug, 'KEY ' . $id,
            $approved && !$retired ? 1 : 0, $approved ? 1 : 0, $retired ? '2026-11-01T00:00:00Z' : null,
            strtolower($bootstrapUsername ?? $username), $approvedBy,
        ]);
        $pdo->prepare('INSERT INTO posts (post_id, created_at, thread_id, parent_id) VALUES (?, ?, ?, NULL)')
            ->execute(['identity-' . $slug, $createdAt, 'thread-' . $slug]);
    }
}
