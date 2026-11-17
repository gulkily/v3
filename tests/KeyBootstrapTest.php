<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/TestViewerSession.php';

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

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1';

    public function testSignedInIphoneThatLostItsKeyGetsAnApprovedKeyOnce(): void
    {
        $this->withApi(function (string $root, string $db, Application $app, callable $api): void {
            $writer = $this->writer($root, $db, dirname($db) . '/public');
            $lost = $this->link($app, 'lena');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            TestViewerSession::signIn($lost['identity_id'], 'safari/ios/mobile');

            $status = $api('GET', '/api/key_bootstrap_status');
            assertSame(['status' => 'eligible', 'from_identity_id' => $lost['identity_id'], 'username' => 'lena', 'device_match' => true], $status);
            $replacement = $this->link($app, 'lena');
            $result = $this->bootstrapOverApi($api, $replacement);
            assertSame('ok', $result['status']);
            assertSame(1, (int) $this->profile($db, $replacement['identity_id'])['is_approved']);

            // The same session never mints a second key, and a repeat of the accepted request still answers.
            assertSame(['status' => 'refused', 'reason' => 'used'], $api('GET', '/api/key_bootstrap_status'));
            $another = $this->link($app, 'lena');
            assertSame(['status' => 'refused', 'reason' => 'used'], $api('POST', '/api/prepare_key_bootstrap', ['identity_id' => $another['identity_id']]));
            assertSame(true, $api('POST', '/api/finalize_key_bootstrap', $this->lastFinalize)['recovered']);
        });
    }

    public function testEveryRefusalLeavesTheKeyPendingOrUntouched(): void
    {
        $this->withApi(function (string $root, string $db, Application $app, callable $api): void {
            $writer = $this->writer($root, $db, dirname($db) . '/public');
            $lost = $this->link($app, 'rafa');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $replacement = $this->link($app, 'rafa');

            // Another browser than the one that signed in: the manual step.
            TestViewerSession::signIn($lost['identity_id'], 'chrome/android/mobile');
            assertSame(false, $api('GET', '/api/key_bootstrap_status')['device_match']);
            assertSame(['status' => 'pending', 'reason' => 'device'], $api('POST', '/api/prepare_key_bootstrap', ['identity_id' => $replacement['identity_id']]));
            // A session that never recorded a browser counts as a mismatch.
            TestViewerSession::signIn($lost['identity_id']);
            assertSame(['status' => 'pending', 'reason' => 'device'], $api('POST', '/api/prepare_key_bootstrap', ['identity_id' => $replacement['identity_id']]));

            TestViewerSession::signIn($lost['identity_id'], 'safari/ios/mobile');
            // Plain HTTP needs its own flag.
            assertSame(['status' => 'refused', 'reason' => 'http'], $api('GET', '/api/key_bootstrap_status', [], false));
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED=1');
            assertSame('eligible', $api('GET', '/api/key_bootstrap_status', [], false)['status']);
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED');
            // The feature flag off.
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_ENABLED=0');
            assertSame(['status' => 'refused', 'reason' => 'off'], $api('GET', '/api/key_bootstrap_status'));
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_ENABLED=1');
            // Another site cannot post on this session's behalf.
            assertSame(403, $this->lastStatus($api('POST', '/api/prepare_key_bootstrap', ['identity_id' => $replacement['identity_id']], true, 'https://evil.example')));
            // Signed out ("Remove this key" signs out first): nothing to vouch with.
            TestViewerSession::lobbyOnly($lost['identity_id']);
            assertSame(['status' => 'refused', 'reason' => 'signed_out'], $api('GET', '/api/key_bootstrap_status'));
            assertSame(0, (int) $this->profile($db, $replacement['identity_id'])['is_approved']);
        });
    }

    public function testRetiredSessionKeyCannotVouch(): void
    {
        $this->withApi(function (string $root, string $db, Application $app, callable $api): void {
            $writer = $this->writer($root, $db, dirname($db) . '/public');
            $lost = $this->link($app, 'emil');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $kept = $this->link($app, 'emil');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $kept));
            $prepared = $writer->prepareKeyRetirement(['actor_identity_id' => $kept['identity_id'], 'target_identity_id' => $lost['identity_id']]);
            $writer->finalizePreparedKeyRetirement(['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->sign($kept['home'], $prepared['canonical_record'])]);
            TestViewerSession::signIn($lost['identity_id'], 'safari/ios/mobile');
            assertSame(['status' => 'refused', 'reason' => 'not_member'], $api('GET', '/api/key_bootstrap_status'));
        });
    }

    public function testReplacedKeyShowsItsPillAndWaitsInTheQueueUntilRetired(): void
    {
        $this->withRepository(function (string $root, string $db, string $artifacts, Application $app): void {
            $writer = $this->writer($root, $db, $artifacts);
            $lost = $this->link($app, 'ada');
            $writer->approveUser($this->approvalInput(self::ROOT_IDENTITY, $lost));
            $replacement = $this->link($app, 'ada');
            assertSame([], \ForumRewrite\ReadModel\ReplacedKeys::all((new ReadModelConnection($db))->open()));
            $this->bootstrap($writer, $lost, $replacement);

            $queue = \ForumRewrite\ReadModel\ReplacedKeys::all((new ReadModelConnection($db))->open());
            assertSame([$lost['identity_id']], array_column($queue, 'identity_id'));
            assertSame($replacement['profile_slug'], $queue[0]['replacement_profile_slug']);
            TestViewerSession::signIn($replacement['identity_id']);
            $index = $this->html($root, $db, $artifacts, '/tools/user-access/');
            assertSame(true, str_contains($index, 'Replaced keys') && str_contains($index, '<span class="chip chip--replaced">Replaced</span>')
                && str_contains($index, '/tools/user-access/profiles/' . $lost['profile_slug']), 'queue on User Access');
            $account = $this->html($root, $db, $artifacts, '/tools/user-access/accounts/ada');
            assertSame(1, substr_count($account, 'class="chip chip--replaced"'), 'one pill on the account keys list');
            $lostPage = $this->html($root, $db, $artifacts, '/tools/user-access/profiles/' . $lost['profile_slug']);
            assertSame(true, str_contains($lostPage, 'Replaced by:') && str_contains($lostPage, '/tools/user-access/profiles/' . $replacement['profile_slug']), 'replaced by link');
            $newPage = $this->html($root, $db, $artifacts, '/tools/user-access/profiles/' . $replacement['profile_slug']);
            assertSame(false, str_contains($newPage, 'class="chip chip--replaced"'));

            // Retiring the lost key with the existing tool takes it out of the queue.
            $prepared = $writer->prepareKeyRetirement(['actor_identity_id' => $replacement['identity_id'], 'target_identity_id' => $lost['identity_id']]);
            $writer->finalizePreparedKeyRetirement(['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->sign($replacement['home'], $prepared['canonical_record'])]);
            assertSame([], \ForumRewrite\ReadModel\ReplacedKeys::all((new ReadModelConnection($db))->open()));
            assertSame(false, str_contains($this->html($root, $db, $artifacts, '/tools/user-access/'), 'Replaced keys'));
            TestViewerSession::reset();
        });
    }

    private function html(string $root, string $db, string $artifacts, string $path): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        (new Application(dirname(__DIR__), $root, $db, $artifacts))->handle('GET', $path);
        return (string) ob_get_clean();
    }

    /** @var array<string, string> */
    private array $lastFinalize = [];

    /** @return array<string, mixed> */
    private function bootstrapOverApi(callable $api, array $replacement): array
    {
        $prepared = $api('POST', '/api/prepare_key_bootstrap', ['identity_id' => $replacement['identity_id']]);
        if (($prepared['status'] ?? '') !== 'ok') throw new RuntimeException('prepare_key_bootstrap: ' . json_encode($prepared));
        $this->lastFinalize = $this->finalizeInput($prepared, $replacement['identity_id'], $this->sign($replacement['home'], $prepared['canonical_record']));
        return $api('POST', '/api/finalize_key_bootstrap', $this->lastFinalize);
    }

    private function lastStatus(array $response): int
    {
        return (int) ($response['_http_status'] ?? 0);
    }

    /** Runs a test against the HTTP API with the flag on, from an iPhone over HTTPS. */
    private function withApi(callable $test): void
    {
        $saved = $_SERVER;
        putenv('FORUM_SESSION_KEY_BOOTSTRAP_ENABLED=1');
        try {
            $this->withRepository(function (string $root, string $db, string $artifacts, Application $app) use ($test): void {
                $api = function (string $method, string $path, array $post = [], bool $https = true, ?string $origin = null) use ($root, $db, $artifacts): array {
                    $_SERVER['HTTPS'] = $https ? 'on' : 'off';
                    $_SERVER['HTTP_HOST'] = 'board.example';
                    $_SERVER['HTTP_ORIGIN'] = $origin ?? (($https ? 'https://' : 'http://') . 'board.example');
                    $_SERVER['HTTP_USER_AGENT'] = self::IPHONE;
                    $_SERVER['REQUEST_METHOD'] = $method;
                    // The session cookie follows the scheme, as in a browser.
                    [$name, $token] = TestViewerSession::cookie();
                    $cookies = $_COOKIE;
                    if ($token === '') {
                        foreach ([\ForumRewrite\Session\ViewerSessionCookie::HTTPS_NAME, \ForumRewrite\Session\ViewerSessionCookie::HTTP_NAME] as $candidate) {
                            if (($_COOKIE[$candidate] ?? '') !== '') $token = $_COOKIE[$candidate];
                        }
                    }
                    unset($_COOKIE[\ForumRewrite\Session\ViewerSessionCookie::HTTPS_NAME], $_COOKIE[\ForumRewrite\Session\ViewerSessionCookie::HTTP_NAME]);
                    if ($token !== '') $_COOKIE[\ForumRewrite\Session\ViewerSessionCookie::name($https)] = $token;
                    $_POST = $post;
                    http_response_code(200);
                    ob_start();
                    (new Application(dirname(__DIR__), $root, $db, $artifacts))->handle($method, $path);
                    $body = (string) ob_get_clean();
                    $_POST = [];
                    $code = http_response_code();
                    $_COOKIE = $cookies;
                    $decoded = json_decode($body, true);
                    if (!is_array($decoded)) throw new RuntimeException($path . ': ' . $body);
                    return $code === 403 ? $decoded + ['_http_status' => 403] : $decoded;
                };
                $test($root, $db, $app, $api);
            });
        } finally {
            $_SERVER = $saved;
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_ENABLED');
            putenv('FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED');
            TestViewerSession::reset();
        }
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
