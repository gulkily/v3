<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

final class PrivateMessageFeatureFlagTest
{
    public function testBrowserAvailabilityStopsRequestsWithoutTreatingAuthorizationAsShutdown(): void
    {
        exec('node ' . escapeshellarg(__DIR__ . '/browser/private_message_availability.cjs') . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
    }

    public function testDisabledNamespaceDeniesEveryRouteWithoutTouchingPrivateStorage(): void
    {
        $root = sys_get_temp_dir() . '/messaging-gate-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        symlink(dirname(__DIR__) . '/templates', $root . '/templates');
        symlink(dirname(__DIR__) . '/public', $root . '/public');
        $keys = [FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED, 'PRIVATE_MESSAGE_DATABASE_PATH', 'FORUM_SECRETS_PATH'];
        $previous = array_map('getenv', $keys);
        $previousSession = $_SESSION ?? [];
        try {
            putenv($keys[0] . '=false');
            putenv($keys[1] . '=' . $root . '/messages.sqlite3');
            putenv($keys[2] . '=' . $root . '/absent.php');
            $store = new PrivateMessageStore(new PDO('sqlite:' . $root . '/messages.sqlite3'));
            $store->storeEnvelope('retained-message', '2026-10-13T00:00:00Z', 'alice', 'bob', 'alice-key', 'retained ciphertext');
            unset($store);
            $before = hash_file('sha256', $root . '/messages.sqlite3');
            foreach ([[], ['authenticated_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954']] as $session) {
                $_SESSION = $session;
                foreach (['/api/private_messages', '/api/private_messages/inbox', '/api/private_messages/sent',
                    '/api/private_messages/conversation?username_token=bob', '/api/private_messages/conversations',
                    '/api/private_messages/recipient_keys?username_token=bob', '/api/private_messages/unread',
                    '/api/private_messages/read', '/api/private_messages/history_sync/work',
                    '/api/private_messages/history_sync/transfers', '/api/private_messages/history_sync/acknowledge',
                    '/api/private_messages/future_endpoint', '/messages', '/messages/', '/messages/inbox',
                    '/messages/sent', '/messages/conversation/bob'] as $path) {
                    foreach (['GET', 'POST'] as $method) {
                        [$status, $body] = $this->request($root, $method, $path);
                        assertSame(403, $status);
                        if (str_starts_with($path, '/api/')) {
                            assertSame('messaging_disabled', json_decode($body, true)['code']);
                        } else {
                            assertStringContains('Messaging unavailable', $body);
                            assertStringNotContains('data-private-message-composer', $body);
                        }
                        assertStringNotContains('retained ciphertext', $body);
                        assertStringNotContains('/assets/private_message_', $body);
                    }
                }
            }
            $pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
            $profile = $pdo->query("SELECT * FROM profiles WHERE is_approved = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $_SESSION = ['authenticated_identity_id' => $profile['identity_id']];
            $profile['identity_id'] = 'test-messaging-recipient';
            $profile['profile_slug'] = 'test-messaging-recipient';
            $profile['username'] = $profile['username_token'] = 'messaging-recipient';
            $pdo->prepare('INSERT INTO profiles (' . implode(',', array_keys($profile)) . ') VALUES (' . implode(',', array_fill(0, count($profile), '?')) . ')')->execute(array_values($profile));
            foreach ([false, true, false] as $enabled) {
                putenv($keys[0] . '=' . ($enabled ? 'true' : 'false'));
                foreach (['/profiles/test-messaging-recipient', '/user/messaging-recipient'] as $path) {
                    [$status, $html] = $this->request($root, 'GET', $path);
                    assertSame(200, $status);
                    assertSame($enabled, str_contains($html, 'data-private-message-composer'));
                    assertSame($enabled, str_contains($html, '/assets/private_messages.'));
                }
            }
            assertSame($before, hash_file('sha256', $root . '/messages.sqlite3'));
            assertSame(false, is_file($root . '/state/private/message_history_sync.sqlite3'));
            // A new request re-evaluates deployment state; normal authentication resumes.
            $_SESSION = [];
            putenv($keys[0] . '=true');
            [$status, $body] = $this->request($root, 'GET', '/api/private_messages/inbox');
            assertSame(401, $status);
            assertStringNotContains('messaging_disabled', $body);
            assertSame($before, hash_file('sha256', $root . '/messages.sqlite3'));
        } finally {
            foreach ($keys as $i => $key) { putenv($previous[$i] === false ? $key : $key . '=' . $previous[$i]); }
            $_SESSION = $previousSession;
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            $this->remove($root);
        }
    }

    public function testNavigationAndBackgroundScriptsFollowEffectiveFlag(): void
    {
        $key = FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED;
        $previous = getenv($key);
        putenv($key);
        try {
            foreach ([true, false] as $enabled) {
                $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates', featureFlags: new FeatureFlagEvaluator(siteValues: [$key => $enabled]));
                $html = $renderer->renderLayout('Board', '<main></main>', 'board', viewerProfile: [
                    'is_approved' => 1, '_authenticated_identity' => true, 'username_token' => 'alice',
                ]);
                foreach (['href="/messages"', 'data-private-message-unread', '/assets/private_message_unread.', '/assets/private_message_history_sync.'] as $marker) {
                    assertSame($enabled, str_contains($html, $marker));
                }
            }
        } finally { putenv($previous === false ? $key : $key . '=' . $previous); }
    }

    public function testLiveNavigationUsesCanonicalEligibilityAndDependencies(): void
    {
        $key = FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED;
        $members = FeatureFlagRegistry::APPROVED_MEMBERS_ONLY;
        $old = [getenv($key), getenv($members)];
        putenv($key); putenv($members);
        try {
            foreach ([false, true] as $enabled) {
                foreach ([false, true] as $membersOnly) {
                    $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates', featureFlags: new FeatureFlagEvaluator(siteValues: [$key => $enabled, $members => $membersOnly]));
                    foreach ([null, ['is_approved' => 0], ['is_approved' => 1, '_authenticated_identity' => false],
                        ['is_approved' => 1, '_authenticated_identity' => true, 'username_token' => 'alice'],
                        ['is_approved' => 1, '_authenticated_identity' => true, '_members_only_access' => false, 'username_token' => 'alice']] as $viewer) {
                        $state = $renderer->navigationState($viewer, 'tools');
                        $page = $renderer->renderLayout('Flags', '', 'tools', viewerProfile: $viewer);
                        assertSame(str_contains($page, 'data-private-message-unread'), $state['messaging_enabled']);
                        assertSame($state['messaging_enabled'], str_contains($state['html'], 'data-private-message-unread'));
                        assertSame($state['messaging_enabled'] ? 'alice' : null, $state['viewer']);
                        assertSame($state['messaging_enabled'], $state['scripts'] !== []);
                    }
                }
            }
        } finally {
            foreach ([$key, $members] as $i => $name) { putenv($old[$i] === false ? $name : $name . '=' . $old[$i]); }
        }
    }

    public function testLiveNavigationMatchesEveryProfileLayout(): void
    {
        $previous = getenv('FORUM_SITE_ID');
        try {
            foreach (\ForumRewrite\SiteProfileRegistry::all() as $id => $profile) {
                putenv('FORUM_SITE_ID=' . $id);
                $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
                $viewer = ['is_approved' => 1, '_authenticated_identity' => true, 'username_token' => 'alice'];
                $state = $renderer->navigationState($viewer, 'tools');
                $html = $renderer->renderLayout('Flags', '', 'tools', viewerProfile: $viewer);
                preg_match('/<nav class="nav">.*?<\/nav>/s', $html, $matches);
                assertSame(preg_replace('/\s+/', ' ', trim($matches[0])), preg_replace('/\s+/', ' ', trim($state['html'])));
            }
        } finally { putenv($previous === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID=' . $previous); }
    }

    private function request(string $root, string $method, string $path): array
    {
        $app = new Application($root, __DIR__ . '/fixtures/parity_minimal_v1', $root . '/read.sqlite3');
        http_response_code(200);
        ob_start();
        try { $app->handle($method, $path); return [http_response_code(), (string) ob_get_contents()]; }
        finally { ob_end_clean(); }
    }

    private function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) { @unlink($path); return; }
        foreach (scandir($path) as $name) { if ($name !== '.' && $name !== '..') { $this->remove($path . '/' . $name); } }
        rmdir($path);
    }
}
