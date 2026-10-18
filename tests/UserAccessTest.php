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
                foreach (['data-signed-approval-form', 'Account key history', 'Unapproved Profiles', 'Approved Profiles', 'Public key', 'Identity ID', '/users/pending/'] as $removed) assertStringNotContains($removed, $html);
            }
            $name = $user['username_token'];
            assertStringContains('/tools/user-access/accounts/' . $name . '?key_offset=50#account-keys', $this->render($root, $db, '/user/' . $name . '?key_offset=50'));
            assertStringContains('/tools/user-access/pending/', $this->render($root, $db, '/users/pending/'));
            assertSame(303, http_response_code());
            $_SESSION = [];
            $this->render($root, $db, '/users/pending/');
            assertSame(403, http_response_code());
            assertStringContains('Not Approved', $this->render($root, $db, '/forte/users/'));
            assertStringContains('Approved Profiles', $this->render($root, $db, '/forte/user/' . $name));
            assertStringContains('Public key', $this->render($root, $db, '/forte/profiles/' . $user['profile_slug']));
        });
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
