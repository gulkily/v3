<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class UserAccessTest
{
    public function testToolsAccountHistoryAndForteDefaults(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $pdo = new PDO('sqlite:' . $db);
            $user = $pdo->query('SELECT * FROM profiles WHERE is_approved = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $name = $user['username_token'];
            assertStringContains('href="/tools/user-access/"', $this->render($root, $db, '/tools/'));
            assertStringContains('href="/tools/user-access/accounts/' . $name . '"', $this->render($root, $db, '/tools/user-access/'));
            $html = $this->render($root, $db, '/tools/user-access/accounts/' . $name);
            assertSame(200, http_response_code());
            assertStringContains('Account key history', $html);
            assertStringContains('/tools/user-access/profiles/' . $user['profile_slug'], $html);
            assertStringContains('Approval evidence', $html);
            assertStringContains('Account key history', $this->render($root, $db, '/forte/user/' . $name));
            assertStringContains('Account not found', $this->render($root, $db, '/tools/user-access/accounts/missing'));
            assertSame(404, http_response_code());
        });
    }

    public function testPendingQueuePermissionsAndExactKeyReview(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->git($root);
            $writer = new \ForumRewrite\Write\LocalWriteService($root, $db, $workspace->root . '/artifacts', new CanonicalRecordRepository($root));
            $target = $writer->linkIdentity(['public_key' => file_get_contents(__DIR__ . '/fixtures/openpgp_signature/reaction-public-key.asc')]);
            $pdo = new PDO('sqlite:' . $db);
            $actor = $pdo->query('SELECT identity_id FROM profiles WHERE is_approved = 1 LIMIT 1')->fetchColumn();
            foreach ([[], ['authenticated_identity_id' => $target['identity_id']]] as $session) {
                $_SESSION = $session;
                assertStringContains('Only approved users', $this->render($root, $db, '/tools/user-access/pending/'));
                assertSame(403, http_response_code());
            }
            $pendingProfile = \ForumRewrite\ReadModel\ProfileRepository::bySlug($pdo, $target['profile_slug']);
            $detail = json_decode($this->render($root, $db, '/api/forte_user_detail?username_token=' . rawurlencode($pendingProfile['username_token'])), true, 512, JSON_THROW_ON_ERROR);
            assertSame('ok', $detail['status']);
            assertStringContains('Pending approval', $detail['html']);
            $_SESSION = ['authenticated_identity_id' => $actor];
            assertStringContains('/tools/user-access/profiles/' . $target['profile_slug'], $this->render($root, $db, '/tools/user-access/pending/'));
            assertSame(200, http_response_code());
            $html = $this->render($root, $db, '/tools/user-access/profiles/' . $target['profile_slug']);
            assertStringContains('data-profile-slug="' . $target['profile_slug'] . '"', $html);
            assertStringContains('data-approval-return="tools"', $html);
            assertStringContains($target['identity_id'], $html);
            assertStringNotContains('Approval complete.', $this->render($root, $db, '/tools/user-access/profiles/' . $target['profile_slug'] . '?approval=success'));
            $writer->approveUser(['approver_identity_id' => $actor, 'target_identity_id' => $target['identity_id'], 'target_profile_slug' => $target['profile_slug'], 'thread_id' => $target['bootstrap_thread_id'], 'parent_id' => $target['bootstrap_post_id']]);
            assertStringContains('Approval complete.', $this->render($root, $db, '/tools/user-access/profiles/' . $target['profile_slug'] . '?approval=success'));
            assertStringNotContains('data-signed-approval-form', $this->render($root, $db, '/tools/user-access/profiles/' . $target['profile_slug']));
        });
    }

    public function testStandardSeparationLegacyLinksAndFortePreservation(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $pdo = new PDO('sqlite:' . $db);
            $user = $pdo->query('SELECT * FROM profiles WHERE is_approved = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $_SESSION = ['authenticated_identity_id' => $user['identity_id']];
            foreach (['/users/', '/user/' . $user['username_token'], '/profiles/' . $user['profile_slug']] as $url) {
                $html = $this->render($root, $db, $url);
                assertSame(200, http_response_code());
                foreach (['data-signed-approval-form', 'Account key history', 'Unapproved Profiles', 'Approved Profiles', 'Identity ID', '/users/pending/'] as $removed) assertStringNotContains($removed, $html);
                if (str_starts_with($url, '/profiles/')) assertStringContains('<summary>Public key</summary>', $html);
                else assertStringNotContains('Public key', $html);
            }
            $name = $user['username_token'];
            assertStringContains('/tools/user-access/accounts/' . $name . '?key_offset=50#account-keys', $this->render($root, $db, '/user/' . $name . '?key_offset=50'));
            // Users › Waiting lists the people waiting beside the member directory.
            assertStringContains('Waiting (', $this->render($root, $db, '/users/pending/'));
            assertSame(200, http_response_code());
            $_SESSION = [];
            $this->render($root, $db, '/users/pending/');
            assertSame(403, http_response_code());
            // Users is the same page for every viewer; its tabs are member-only and shown before paint by the client.
            $anonymous = $this->render($root, $db, '/users/');
            $_SESSION = ['authenticated_identity_id' => $user['identity_id']];
            $approved = $this->render($root, $db, '/users/');
            $_SESSION = [];
            $tabs = static fn (string $html): string => preg_match('#<nav class="board-filter" aria-label="Users" data-member-only>.*?</nav>#s', $html, $match) ? $match[0] : '';
            assertStringContains('Waiting (', $tabs($anonymous));
            assertSame($tabs($anonymous), $tabs($approved));
            assertStringContains('Not Approved', $this->render($root, $db, '/forte/users/'));
            assertStringContains('Approved Profiles', $this->render($root, $db, '/forte/user/' . $name));
            assertStringContains('Public key', $this->render($root, $db, '/forte/profiles/' . $user['profile_slug']));
        });
    }

    public function testHistoryPaginationAndForteLinksStayInTheirJourneys(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $pdo = new PDO('sqlite:' . $db);
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $copy = $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            for ($i = 0; $i < 52; $i++) $copy->execute(['openpgp:' . str_pad(dechex($i + 100), 40, '0', STR_PAD_LEFT), 'history-key-' . $i, $profile['username'], $profile['username_token'], 'history', 'history-' . $i, 'history-' . $i, 'history-' . $i, '']);
            $name = $profile['username_token'];
            $first = $this->render($root, $db, '/tools/user-access/accounts/' . $name);
            assertStringContains('/tools/user-access/accounts/' . $name . '?key_offset=50#account-keys', $first);
            assertSame(50, substr_count($first, 'class="account-key-history-entry"'));
            $last = $this->render($root, $db, '/tools/user-access/accounts/' . $name . '?key_offset=50');
            assertSame(3, substr_count($last, 'class="account-key-history-entry"'));
            assertStringContains('?key_offset=0#account-keys', $last);
            assertStringNotContains('More keys', $last);
            $forte = $this->render($root, $db, '/forte/user/' . $name);
            assertStringContains('/forte/user/' . $name . '?key_offset=50#account-keys', $forte);
            assertStringContains('/forte/profiles/', $forte);
        });
    }

    public function testHostServesRegeneratedMemberPagesAndNeverCachesAccessViews(): void
    {
        $previous = getenv('FORUM_STATIC_PROFILE_PAGES_ENABLED');
        putenv('FORUM_STATIC_PROFILE_PAGES_ENABLED=true');
        try {
            $this->fixture(function ($workspace, $root, $db): void {
                $public = $workspace->root . '/public';
                $static = $workspace->root . '/static';
                (new \ForumRewrite\Host\StaticArtifactBuilder(dirname(__DIR__), $root, $db, $public))->buildFromReadModel();
                $pdo = new PDO('sqlite:' . $db);
                $user = $pdo->query('SELECT * FROM profiles WHERE is_approved = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                assertStringNotContains('/users/pending/', file_get_contents($public . '/users.html'));
                $profile = file_get_contents($public . '/profiles/' . $user['profile_slug'] . '.html');
                assertStringContains('<summary>Public key</summary>', $profile);
                assertStringContains('/tools/user-access/profiles/', $profile);
                foreach (['/tools/user-access/index.html', '/tools/user-access/pending/index.html', '/users/pending/index.html'] as $path) $workspace->put($public . $path, 'STALE ACCESS');
                $host = new \ForumRewrite\Host\FrontController(dirname(__DIR__), $root, $db, $static, $public);
                foreach (['/tools/user-access/', '/tools/user-access/pending/', '/users/pending/', '/users/'] as $url) {
                    ob_start();
                    try { $host->handle('GET', $url, []); $html = (string) ob_get_contents(); }
                    finally { ob_end_clean(); }
                    assertStringNotContains('STALE ACCESS', $html);
                    assertSame(str_contains($url, 'pending') ? 403 : 200, http_response_code());
                }
            });
        } finally {
            putenv($previous === false ? 'FORUM_STATIC_PROFILE_PAGES_ENABLED' : 'FORUM_STATIC_PROFILE_PAGES_ENABLED=' . $previous);
        }
    }

    private function fixture(callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
        $db = $workspace->root . '/read.sqlite3';
        $flag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $session = $_SESSION ?? [];
        $cookies = $_COOKIE;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = []; $_COOKIE = [];
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        try {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $test($workspace, $root, $db);
        } finally {
            $_SESSION = $session; $_COOKIE = $cookies;
            putenv($flag === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $flag);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        }
    }

    private function render(string $root, string $db, string $url): string
    {
        http_response_code(200);
        ob_start();
        try {
            (new Application(dirname(__DIR__), $root, $db))->handle('GET', $url);
            return (string) ob_get_contents();
        } finally { ob_end_clean(); }
    }
}
