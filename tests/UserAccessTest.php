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
