<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Session\ViewerSessionCookie;
use ForumRewrite\Session\ViewerSessionDatabaseConfig;
use ForumRewrite\Session\ViewerSessionStore;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

final class ViewerSessionStoreTest
{
    private int $now = 1_800_000_000;

    public function testSignInIssuesSessionThatResolvesForItsLifetime(): void
    {
        $store = $this->store();
        $session = $store->signIn('OpenPGP:ABC');
        assertSame('openpgp:abc', $session->identityId);
        assertSame(true, ViewerSessionStore::isWellFormedToken($session->token));

        // Idle far past PHP's old 24-minute default: still signed in.
        $this->now += 86400 * 30;
        assertSame('openpgp:abc', $store->resolve($session->token)?->identityId);

        $this->now = $session->expiresAt;
        assertSame(null, $store->resolve($session->token));
    }

    public function testUnknownMalformedAndPlainTokensResolveToNothing(): void
    {
        $store = $this->store();
        assertSame(null, $store->resolve(str_repeat('a', 64)));
        assertSame(null, $store->resolve('not-a-token'));
        assertSame(null, $store->resolve(''));
    }

    public function testStoreKeepsOnlyTokenHashes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new ViewerSessionStore($pdo, fn (): int => $this->now);
        $session = $store->signIn('openpgp:abc');
        $stored = $pdo->query('SELECT token_hash FROM viewer_sessions')->fetchAll(PDO::FETCH_COLUMN);
        assertSame([hash('sha256', $session->token)], $stored);
    }

    public function testSigningInAgainAsSameIdentityKeepsTheSession(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new ViewerSessionStore($pdo, fn (): int => $this->now);
        $first = $store->signIn('openpgp:abc');
        $this->now += 600;
        $second = $store->signIn('openpgp:abc', $first->token);
        assertSame($first->token, $second->token);
        assertSame($this->now + ViewerSessionStore::LIFETIME_SECONDS, $second->expiresAt);
        assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM viewer_sessions')->fetchColumn());
    }

    public function testSigningInAsAnotherIdentityReplacesTokenAndForwardsBriefly(): void
    {
        $store = $this->store();
        $old = $store->signIn('openpgp:abc');
        $new = $store->signIn('openpgp:def', $old->token);
        assertSame(false, $old->token === $new->token);

        // A request already in flight with the old token acts on the new session.
        $this->now += ViewerSessionStore::FORWARD_SECONDS;
        assertSame('openpgp:def', $store->resolve($old->token)?->identityId);

        $this->now += 2;
        assertSame(null, $store->resolve($old->token));
        assertSame('openpgp:def', $store->resolve($new->token)?->identityId);
    }

    public function testSignOutEndsSessionAndKeepsLobbyIdentity(): void
    {
        $store = $this->store();
        $session = $store->signIn('openpgp:abc');
        $copied = $session->token;

        $after = $store->signOut($session->token);
        assertSame(false, $after?->isSignedIn());
        assertSame('openpgp:abc', $after?->lobbyIdentityId);

        // A copy of the cookie no longer signs anyone in.
        $resolved = $store->resolve($copied);
        assertSame('', $resolved?->identityId);
        assertSame('openpgp:abc', $resolved?->lobbyIdentityId);

        $store->forgetLobbyIdentity($copied);
        assertSame('', $store->resolve($copied)?->lobbyIdentityId);
    }

    public function testSignOutDuringForwardingSignsOutTheReplacement(): void
    {
        $store = $this->store();
        $old = $store->signIn('openpgp:abc');
        $new = $store->signIn('openpgp:def', $old->token);
        $store->signOut($old->token);
        assertSame('', $store->resolve($new->token)?->identityId);
    }

    public function testSigningInFromSignedOutSessionIssuesNewToken(): void
    {
        $store = $this->store();
        $session = $store->signIn('openpgp:abc');
        $store->signOut($session->token);
        $again = $store->signIn('openpgp:abc', $session->token);
        assertSame(false, $again->token === $session->token);
        assertSame('openpgp:abc', $store->resolve($again->token)?->identityId);
    }

    public function testChallengesAreSingleUseAndExpire(): void
    {
        $store = $this->store();
        $challenge = $store->createChallenge();
        assertSame(true, $store->challengeIsOpen($challenge));
        assertSame(true, $store->consumeChallenge($challenge));
        assertSame(false, $store->consumeChallenge($challenge));
        assertSame(false, $store->challengeIsOpen($challenge));

        $late = $store->createChallenge();
        $this->now += ViewerSessionStore::CHALLENGE_SECONDS + 1;
        assertSame(false, $store->challengeIsOpen($late));
        assertSame(false, $store->consumeChallenge($late));
        assertSame(false, $store->consumeChallenge('never-issued'));
    }

    public function testPurgeRemovesExpiredSessions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new ViewerSessionStore($pdo, fn (): int => $this->now);
        $store->signIn('openpgp:abc');
        $this->now += ViewerSessionStore::LIFETIME_SECONDS;
        $store->purgeExpired();
        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM viewer_sessions')->fetchColumn());
    }

    public function testReadingRecentSessionDoesNotWrite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new ViewerSessionStore($pdo, fn (): int => $this->now);
        $session = $store->signIn('openpgp:abc');
        $before = (int) $pdo->query('SELECT total_changes()')->fetchColumn();
        $this->now += 60;
        $store->resolve($session->token);
        assertSame($before, (int) $pdo->query('SELECT total_changes()')->fetchColumn());

        $this->now += 3600;
        $store->resolve($session->token);
        assertSame($this->now, (int) $pdo->query('SELECT last_seen_at FROM viewer_sessions')->fetchColumn());
    }

    public function testCookieNamesAreSeparatePerScheme(): void
    {
        $token = str_repeat('b', 64);
        assertSame('__Host-v3_session', ViewerSessionCookie::name(true));
        assertSame('v3_session', ViewerSessionCookie::name(false));
        // An http cookie never signs in an https request, and the reverse.
        assertSame('', ViewerSessionCookie::tokenFromRequest(['v3_session' => $token], true));
        assertSame('', ViewerSessionCookie::tokenFromRequest(['__Host-v3_session' => $token], false));
        assertSame($token, ViewerSessionCookie::tokenFromRequest(['__Host-v3_session' => $token, 'v3_session' => str_repeat('c', 64)], true));
        assertSame('', ViewerSessionCookie::tokenFromRequest(['v3_session' => 'junk'], false));
        assertSame(true, ViewerSessionCookie::attributes(true, 5)['secure']);
        assertSame(false, ViewerSessionCookie::attributes(false, 5)['secure']);
        assertSame('/', ViewerSessionCookie::attributes(true, 5)['path']);
        assertSame(true, ViewerSessionCookie::requestIsHttps(['HTTPS' => 'on']));
        assertSame(false, ViewerSessionCookie::requestIsHttps(['HTTPS' => 'off']));
        assertSame(false, ViewerSessionCookie::requestIsHttps([]));
    }

    public function testDefaultPathIsPrivateStateAndConfigurable(): void
    {
        assertSame('/srv/v3/state/private/viewer_sessions.sqlite3', ViewerSessionDatabaseConfig::path('/srv/v3'));
        assertSame('/tmp/x.sqlite3', ViewerSessionDatabaseConfig::path('/srv/v3', ['VIEWER_SESSION_DATABASE_PATH' => '/tmp/x.sqlite3']));
    }

    public function testConcurrentRequestsShareOneStoreWithoutErrors(): void
    {
        $root = sys_get_temp_dir() . '/viewer-session-concurrency-' . bin2hex(random_bytes(4));
        mkdir($root);
        try {
            $config = ['VIEWER_SESSION_DATABASE_PATH' => $root . '/sessions.sqlite3'];
            $store = new ViewerSessionStore(ViewerSessionDatabaseConfig::open($root, $config));
            $token = $store->signIn('openpgp:abc')->token;
            $script = '<?php require ' . var_export(dirname(__DIR__) . '/autoload.php', true) . ';'
                . '$s = new ForumRewrite\Session\ViewerSessionStore(ForumRewrite\Session\ViewerSessionDatabaseConfig::open("", ["VIEWER_SESSION_DATABASE_PATH" => $argv[1]]));'
                . 'for ($i = 0; $i < 150; $i++) { if ($s->resolve($argv[2])?->identityId !== "openpgp:abc") { fwrite(STDERR, "lost"); exit(1); }'
                . ' $c = $s->createChallenge(); if (!$s->consumeChallenge($c)) { fwrite(STDERR, "challenge"); exit(1); }'
                . ' if ($i % 25 === 0) { $s->signIn("openpgp:w" . getmypid()); } }';
            file_put_contents($root . '/worker.php', $script);
            $processes = [];
            for ($worker = 0; $worker < 12; $worker++) {
                $processes[] = proc_open(['php', $root . '/worker.php', $config['VIEWER_SESSION_DATABASE_PATH'], $token], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $processes[count($processes) - 1] = [$processes[count($processes) - 1], $pipes];
            }
            foreach ($processes as [$process, $pipes]) {
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                assertSame(0, proc_close($process) === 0 ? 0 : 'worker failed: ' . $error);
            }
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($root);
        }
    }

    private function store(): ViewerSessionStore
    {
        return new ViewerSessionStore(new PDO('sqlite::memory:'), fn (): int => $this->now);
    }
}
