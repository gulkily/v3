<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Security\PrivateAccessConfirmation;
use ForumRewrite\Security\PrivateKeyAccess;
use ForumRewrite\Write\LocalWriteService;
use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageHistorySyncService;
use ForumRewrite\Messaging\PrivateMessageHistorySyncStore;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** Signed confirm records for private access, and their enforcement in messaging (docs/plans/private_key_access/). */
final class PrivateAccessConfirmationTest
{
    private const ROOT_IDENTITY = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testMemberConfirmsOwnSecondKeyAndItSurvivesARebuild(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            assertSame(false, $this->access($root, $db)->hasAccess($keys['mira2']['identity_id']));

            $result = $this->confirm($writer, $keys['mira1'], $keys['mira2']);
            assertSame(true, $result['has_access']);
            assertSame(PrivateKeyAccess::CONFIRMED, $result['reason']);
            assertSame(true, PrivateAccessConfirmation::read($root, $keys['mira2']['identity_id']) !== null);

            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            assertSame(PrivateKeyAccess::CONFIRMED, $this->access($root, $db)->reason($keys['mira2']['identity_id']));
        });
    }

    public function testRepeatOfTheAcceptedConfirmIsANoOp(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            $prepared = $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $keys['mira1']['identity_id'], 'target_identity_id' => $keys['mira2']['identity_id']]);
            $input = ['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->sign($keys['mira1']['home'], $prepared['canonical_record'])];
            $first = $writer->finalizePrivateAccessConfirmation($input);
            $again = $writer->finalizePrivateAccessConfirmation($input);
            assertSame(false, $first['recovered']);
            assertSame(true, $again['recovered']);
            assertSame($first['commit_sha'], $again['commit_sha']);
        });
    }

    public function testPublicOnlyOrOtherAccountKeysCannotConfirm(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            // A public-only key of the same account cannot vouch for another.
            $this->rejects(fn () => $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $keys['mira2']['identity_id'], 'target_identity_id' => $keys['mira3']['identity_id']]));
            // A key with access in another account is not an operator.
            $this->rejects(fn () => $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $keys['bob']['identity_id'], 'target_identity_id' => $keys['mira2']['identity_id']]));
            // A key that already has access needs no confirm.
            $this->rejects(fn () => $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $keys['mira2']['identity_id'], 'target_identity_id' => $keys['mira1']['identity_id']]));
        });
    }

    public function testWrongSignerIsRefused(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            $prepared = $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $keys['mira1']['identity_id'], 'target_identity_id' => $keys['mira2']['identity_id']]);
            $this->rejects(fn () => $writer->finalizePrivateAccessConfirmation(['prepare_token' => $prepared['prepare_token'],
                'canonical_record' => $prepared['canonical_record'], 'detached_signature' => $this->sign($keys['mira2']['home'], $prepared['canonical_record'])]));
            assertSame(null, PrivateAccessConfirmation::read($root, $keys['mira2']['identity_id']));
        });
    }

    public function testOperatorConfirmsAnyKey(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            // bob becomes an operator after approving mira2: that alone gives mira2 no access.
            $operator = $keys['bob'];
            file_put_contents($root . '/records/approval-seeds/openpgp-' . substr($operator['identity_id'], 8) . '.txt',
                'Approved-Identity-ID: ' . $operator['identity_id'] . "\nSeed-Reason: test operator\n\nTest operator.\n");
            assertSame(false, $this->access($root, $db)->hasAccess($keys['mira2']['identity_id']));
            $result = $this->confirm($writer, $operator, $keys['mira2']);
            assertSame(true, $result['has_access']);
            assertSame('operator', PrivateAccessConfirmation::read($root, $keys['mira2']['identity_id'])['Authority']);
        });
    }

    public function testConfirmRecordPathsAreArchiveRecordsButStayOutOfImports(): void
    {
        $path = 'records/private-access-confirmations/openpgp-' . str_repeat('a', 40) . '.txt';
        assertSame(true, \ForumRewrite\Import\ArchiveRecordCatalog::isRecordPath($path));
        assertSame(true, \ForumRewrite\Import\ArchiveRecordCatalog::isRecordPath(str_replace('confirmations/', 'confirmation-acceptances/', $path)));
        assertSame(true, \ForumRewrite\Import\ArchiveRecordCatalog::isSignaturePath($path . '.asc'));
    }

    public function testEnforcementLeavesPublicOnlyKeysOutOfNewMessagesUntilConfirmed(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            $pdo = (new ReadModelConnection($db))->open();
            $resolver = new ApprovedUserKeyResolver($root);
            $ids = fn (bool $historical): array => array_column($resolver->keysForUsernameToken($pdo, 'mira', $historical), 'identity_id');
            assertSame([$keys['mira1']['identity_id']], $ids(false));
            assertSame(3, count($ids(true)));

            $this->confirm($writer, $keys['mira1'], $keys['mira2']);
            assertSame(2, count($ids(false)));
        });
    }

    public function testPublicOnlyViewerHasNoMailboxOrHistory(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            $pdo = (new ReadModelConnection($db))->open();
            $mailbox = $this->mailbox($root, $db, $pdo);
            $history = $this->history($root, $db, $pdo);
            $viewer = fn (string $name): array => \ForumRewrite\ReadModel\ProfileRepository::byIdentityId($pdo, $keys[$name]['identity_id']);

            assertSame([], $mailbox->unreadConversations($viewer('mira1')));
            $this->rejectsWith(\ForumRewrite\Security\PublicOnlyKeyException::class, fn () => $mailbox->unreadConversations($viewer('mira2')));
            $this->rejectsWith(\ForumRewrite\Security\PublicOnlyKeyException::class, fn () => $mailbox->recipientKeys($viewer('mira2'), 'bob'));
            $this->rejectsWith(\ForumRewrite\Security\PublicOnlyKeyException::class, fn () => $history->work($viewer('mira2')));

            // History never chooses a public-only key as a target.
            $key = new ReflectionMethod($history, 'key');
            $fingerprint = substr($keys['mira2']['identity_id'], 8);
            assertSame(null, $key->invoke($history, 'mira', $fingerprint, false));
            assertSame(true, $key->invoke($history, 'mira', $fingerprint, true) !== null);
            $hasKey = new ReflectionMethod($history, 'hasKey');
            assertSame(false, $hasKey->invoke($history, 'mira', $fingerprint));
            assertSame(true, $hasKey->invoke($history, 'mira', substr($keys['mira1']['identity_id'], 8)));
        });
    }

    public function testFlagOffRestoresPlainApproval(): void
    {
        $this->withAccounts(function (string $root, string $db, LocalWriteService $writer, array $keys): void {
            file_put_contents($root . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_PRIVATE_KEY_ACCESS_ENFORCED: false\n");
            $pdo = (new ReadModelConnection($db))->open();
            assertSame(3, count((new ApprovedUserKeyResolver($root))->keysForUsernameToken($pdo, 'mira')));
            $viewer = \ForumRewrite\ReadModel\ProfileRepository::byIdentityId($pdo, $keys['mira2']['identity_id']);
            assertSame([], $this->mailbox($root, $db, $pdo)->unreadConversations($viewer));
            $hasKey = new ReflectionMethod($history = $this->history($root, $db, $pdo), 'hasKey');
            assertSame(true, $hasKey->invoke($history, 'mira', substr($keys['mira2']['identity_id'], 8)));
        });
    }

    private function mailbox(string $root, string $db, PDO $pdo): PrivateMessageMailboxService
    {
        return new PrivateMessageMailboxService(new PrivateMessageStore(new PDO('sqlite::memory:')), $pdo, new ApprovedUserKeyResolver($root), $root);
    }

    private function history(string $root, string $db, PDO $pdo): PrivateMessageHistorySyncService
    {
        $private = new PDO('sqlite::memory:');
        return new PrivateMessageHistorySyncService(new PrivateMessageStore($private), new PrivateMessageHistorySyncStore($private), $pdo, $root);
    }

    private function rejectsWith(string $class, callable $call): void
    {
        try {
            $call();
        } catch (Throwable $error) {
            if ($error instanceof $class) return;
            throw new RuntimeException('Expected ' . $class . ' but got ' . $error::class . ': ' . $error->getMessage());
        }
        throw new RuntimeException('Expected ' . $class . '.');
    }

    private function access(string $root, string $db): PrivateKeyAccess
    {
        return new PrivateKeyAccess((new ReadModelConnection($db))->open(), $root);
    }

    /** @return array<string, mixed> */
    private function confirm(LocalWriteService $writer, array $actor, array $target): array
    {
        $prepared = $writer->preparePrivateAccessConfirmation(['actor_identity_id' => $actor['identity_id'], 'target_identity_id' => $target['identity_id']]);

        return $writer->finalizePrivateAccessConfirmation(['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->sign($actor['home'], $prepared['canonical_record'])]);
    }

    /** Root approves bob; bob approves mira's first key, then two more mira keys across accounts. */
    private function withAccounts(callable $test): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app) use ($test): void {
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
            $keys = ['bob' => $this->link($app, 'bob')];
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $keys['bob']));
            foreach (['mira1', 'mira2', 'mira3'] as $name) {
                $keys[$name] = $this->link($app, 'mira');
                $writer->approveUser($this->approvalInput($keys['bob']['identity_id'], $keys[$name]));
            }
            $access = $this->access($root, $db);
            assertSame(PrivateKeyAccess::FIRST, $access->reason($keys['mira1']['identity_id']));
            assertSame(PrivateKeyAccess::PUBLIC_ONLY, $access->reason($keys['mira2']['identity_id']));
            $test($root, $db, $writer, $keys);
        });
    }

    /** @return array<string, string> */
    private function approvalInput(string $approver, array $target): array
    {
        return ['approver_identity_id' => $approver, 'target_identity_id' => $target['identity_id'], 'target_profile_slug' => $target['profile_slug'],
            'thread_id' => $target['bootstrap_thread_id'], 'parent_id' => $target['bootstrap_post_id']];
    }

    /** @return array<string, string> */
    private function link(Application $app, string $username): array
    {
        $home = sys_get_temp_dir() . '/private-access-gpg-' . bin2hex(random_bytes(5));
        mkdir($home, 0700, true);
        register_shutdown_function(static fn () => GpgHomeCleaner::dispose($home));
        $gpg = 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home);
        $this->run($gpg . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0');
        $_POST = ['public_key' => trim($this->run($gpg . ' --armor --export')) . "\n"];
        ob_start();
        $app->handle('POST', '/api/link_identity');
        $response = (string) ob_get_clean();
        $_POST = [];
        $values = ['home' => $home];
        foreach (['identity_id', 'profile_slug', 'bootstrap_post_id', 'bootstrap_thread_id'] as $key) {
            if (preg_match('/^' . $key . '=(.*)$/m', $response, $match) !== 1) throw new RuntimeException('link_identity: ' . $response);
            $values[$key] = $match[1];
        }
        return $values;
    }

    private function sign(string $home, string $record): string
    {
        file_put_contents($home . '/record.txt', $record);
        @unlink($home . '/record.txt.asc');
        $this->run('gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home)
            . ' --armor --detach-sign --output ' . escapeshellarg($home . '/record.txt.asc') . ' ' . escapeshellarg($home . '/record.txt'));
        return (string) file_get_contents($home . '/record.txt.asc');
    }

    private function withRepository(callable $test): void
    {
        $base = sys_get_temp_dir() . '/private-access-' . bin2hex(random_bytes(6));
        $root = $base . '/repo';
        mkdir($root, 0777, true);
        mkdir($base . '/public', 0777, true);
        $this->run('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($root));
        foreach (['git init -q', 'git config user.name Test', 'git config user.email test@example.invalid', 'git add .', 'git commit -q -m init'] as $command) {
            $this->run('cd ' . escapeshellarg($root) . ' && ' . $command);
        }
        $db = $base . '/index.sqlite3';
        $app = new Application(dirname(__DIR__), $root, $db, $base . '/public');
        ob_start();
        $app->handle('GET', '/');
        ob_end_clean();
        try {
            $test($root, $db, $base . '/public', $app);
        } finally {
            $_POST = [];
            $this->run('rm -rf ' . escapeshellarg($base));
        }
    }

    private function rejects(callable $call): void
    {
        try {
            $call();
        } catch (Throwable) {
            return;
        }
        throw new RuntimeException('Expected a rejection.');
    }

    private function run(string $command): string
    {
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0) throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        return implode("\n", $output);
    }
}
