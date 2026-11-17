<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Security\ApprovalPolicy;
use ForumRewrite\Security\ApprovalReceiptText;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Write\LocalWriteService;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** Session key bootstrap: the record, its `session` receipt and their replay (docs/plans/session_key_bootstrap/). */
final class KeyBootstrapTest
{
    private const ROOT_IDENTITY = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    /** @var list<string> */
    private array $cleanup = [];

    public function testBootstrappedKeySurvivesFullAndIncrementalRebuildsAndRetraction(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'mira');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $replacement = $this->link($app, 'mira');

            $result = $this->bootstrap($writer, $lost, $replacement);
            assertSame('ok', $result['status']);
            // The incremental update applied the grant, and the session's key is the approver.
            $profile = $this->profile($db, $replacement['identity_id']);
            assertSame(1, (int) $profile['is_approved']);
            assertSame($lost['identity_id'], $profile['approved_by_identity_id']);
            assertSame('session', ApprovalAcceptance::read($root, $result['record_path'])['kind']);
            // A bootstrapped key never becomes an operator.
            assertSame(false, ApprovalPolicy::isOperator($root, $replacement['identity_id']));
            assertSame(false, is_file($root . '/records/approval-seeds/openpgp-' . substr($replacement['identity_id'], 8) . '.txt'));

            $fresh = $db . '.fresh';
            $this->cleanup[] = $fresh;
            (new ReadModelBuilder($root, $fresh, new CanonicalRecordRepository($root)))->rebuild();
            assertSame(1, (int) $this->profile($fresh, $replacement['identity_id'])['is_approved']);
            assertSame($lost['identity_id'], $this->profile($fresh, $replacement['identity_id'])['approved_by_identity_id']);

            // Its author cannot retract the record; an operator can, and that withdraws the grant.
            $this->rejects(fn () => $writer->prepareRetraction(['author_identity_id' => $replacement['identity_id'], 'post_id' => $result['post_id']]));
            $operator = $this->operator($app, $root, $db);
            $prepared = $writer->prepareRetraction(['author_identity_id' => $operator['identity_id'], 'post_id' => $result['post_id']]);
            $retracted = $writer->finalizePreparedRetraction([
                'prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'], 'record_path' => $prepared['record_path'],
                'author_identity_id' => $operator['identity_id'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->sign($operator['home'], $prepared['canonical_record']),
            ]);
            assertSame(null, $retracted['failed_step'] ?? null);
            assertSame([], ApprovalAcceptance::existingPaths($root, $result['post_id']));
            assertSame(0, (int) $this->profile($db, $replacement['identity_id'])['is_approved']);
            (new ReadModelBuilder($root, $fresh, new CanonicalRecordRepository($root)))->rebuild();
            assertSame(0, (int) $this->profile($fresh, $replacement['identity_id'])['is_approved']);
        });
    }

    public function testLostKeyOrOperatorRetiresAWronglyBootstrappedKey(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'pia');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $stolen = $this->link($app, 'pia');
            $this->bootstrap($writer, $lost, $stolen);

            // The lost key, restored on another device, retires the key a stolen session made.
            $prepared = $writer->prepareKeyRetirement(['actor_identity_id' => $lost['identity_id'], 'target_identity_id' => $stolen['identity_id']]);
            $writer->finalizePreparedKeyRetirement(['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->sign($lost['home'], $prepared['canonical_record'])]);
            assertSame(true, \ForumRewrite\Security\KeyRetirement::read($root, $stolen['identity_id']) !== null);
            assertSame(false, (string) ($this->profile($db, $stolen['identity_id'])['retired_at'] ?? '') === '');
        });
    }

    /** Links a key and makes it a root-approved operator. @return array<string, string> */
    private function operator(Application $app, string $root, string $db): array
    {
        $operator = $this->link($app, 'operator');
        $path = $root . '/records/approval-seeds/openpgp-' . substr($operator['identity_id'], 8) . '.txt';
        file_put_contents($path, 'Approved-Identity-ID: ' . $operator['identity_id'] . "\nSeed-Reason: test operator\n\nTest operator.\n");
        $this->run('cd ' . escapeshellarg($root) . ' && git add -A && git commit -q -m operator');
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
        return $operator;
    }

    public function testRecordWithoutValidSessionReceiptGrantsNothing(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'noor');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $replacement = $this->link($app, 'noor');
            $result = $this->bootstrap($writer, $lost, $replacement);
            $receiptPath = $root . '/' . ApprovalAcceptance::path($result['post_id']);
            $receipt = ApprovalReceiptText::decode((string) file_get_contents($receiptPath));

            // A receipt naming another authorizer than the record does is invalid evidence and stops the rebuild.
            $forged = $receipt;
            $forged['context']['actor_identity_id'] = self::ROOT_IDENTITY;
            file_put_contents($receiptPath, ApprovalReceiptText::encode($forged));
            $this->rejects(fn () => (new ReadModelBuilder($root, $db . '.forged', new CanonicalRecordRepository($root)))->rebuild());
            $this->cleanup[] = $db . '.forged';

            // A cross-account classification is never valid for a session receipt.
            $forged = $receipt;
            $forged['context']['classification'] = 'cross_account_key';
            file_put_contents($receiptPath, ApprovalReceiptText::encode($forged));
            $this->rejects(fn () => ApprovalAcceptance::read($root, $result['record_path']));

            // Without a receipt the signed record is only a post.
            unlink($receiptPath);
            $fresh = $db . '.unreceipted';
            $this->cleanup[] = $fresh;
            (new ReadModelBuilder($root, $fresh, new CanonicalRecordRepository($root)))->rebuild();
            assertSame(0, (int) $this->profile($fresh, $replacement['identity_id'])['is_approved']);
        });
    }

    public function testSessionCannotAddAKeyUnderAnotherUsernameOrToItself(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'ines');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $stranger = $this->link($app, 'otto');
            $this->rejects(fn () => $writer->prepareKeyBootstrap(['from_identity_id' => $lost['identity_id'], 'identity_id' => $stranger['identity_id']]));
            $this->rejects(fn () => $writer->prepareKeyBootstrap(['from_identity_id' => $lost['identity_id'], 'identity_id' => $lost['identity_id']]));
            // An unapproved session key cannot vouch.
            $other = $this->link($app, 'otto');
            $this->rejects(fn () => $writer->prepareKeyBootstrap(['from_identity_id' => $stranger['identity_id'], 'identity_id' => $other['identity_id']]));
        });
    }

    public function testSignatureMustComeFromTheNewKeyAndRetryReturnsTheAcceptedResult(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'tove');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $replacement = $this->link($app, 'tove');
            $prepared = $writer->prepareKeyBootstrap(['from_identity_id' => $lost['identity_id'], 'identity_id' => $replacement['identity_id']]);
            $input = $this->finalizeInput($prepared, $replacement['identity_id'], $this->sign($lost['home'], $prepared['canonical_record']));
            $this->rejects(fn () => $writer->finalizeKeyBootstrap($input));

            $input['detached_signature'] = $this->sign($replacement['home'], $prepared['canonical_record']);
            $first = $writer->finalizeKeyBootstrap($input);
            $retry = $writer->finalizeKeyBootstrap($input);
            assertSame(true, $retry['recovered']);
            assertSame($first['commit_sha'], $retry['commit_sha']);
        });
    }

    /** @return array<string, mixed> */
    private function bootstrap(LocalWriteService $writer, array $lost, array $replacement): array
    {
        $prepared = $writer->prepareKeyBootstrap(['from_identity_id' => $lost['identity_id'], 'identity_id' => $replacement['identity_id']]);
        return $writer->finalizeKeyBootstrap($this->finalizeInput($prepared, $replacement['identity_id'], $this->sign($replacement['home'], $prepared['canonical_record'])));
    }

    /** @return array<string, string> */
    private function finalizeInput(array $prepared, string $identityId, string $signature): array
    {
        return ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'], 'record_path' => $prepared['record_path'],
            'author_identity_id' => $identityId, 'canonical_record' => $prepared['canonical_record'], 'detached_signature' => $signature];
    }

    /** @return array<string, string> */
    private function approvalInput(string $approver, array $target): array
    {
        return ['approver_identity_id' => $approver, 'target_identity_id' => $target['identity_id'], 'target_profile_slug' => $target['profile_slug'],
            'thread_id' => $target['bootstrap_thread_id'], 'parent_id' => $target['bootstrap_post_id']];
    }

    /** @return array<string, mixed> */
    private function profile(string $db, string $identityId): array
    {
        $query = (new ReadModelConnection($db))->open()->prepare('SELECT * FROM profiles WHERE identity_id = ?');
        $query->execute([$identityId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Profile missing: ' . $identityId);
        return $row;
    }

    private function writer(string $root, string $db, string $artifacts): LocalWriteService
    {
        return new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
    }

    /** Links a fresh signing key under a username. @return array<string, string> */
    private function link(Application $app, string $username): array
    {
        $home = sys_get_temp_dir() . '/key-bootstrap-gpg-' . bin2hex(random_bytes(5));
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
        $base = sys_get_temp_dir() . '/key-bootstrap-' . bin2hex(random_bytes(6));
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
            foreach ($this->cleanup as $path) @unlink($path);
            $this->cleanup = [];
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
