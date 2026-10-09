<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\ProfilePageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

final class PrivateMessageComposerTest
{
    /** @return array<string, mixed> */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/private_message_compose.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testFailedSubmissionKeepsDraftAndStableIdUntilSuccessfulRetry(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const values = new Map();
const calls = [];
let shouldFail = true;
const inputListeners = {};
const formListeners = {};
const textarea = { value: 'private prose', addEventListener(name, callback) { inputListeners[name] = callback; } };
const button = { disabled: false };
const form = {
  querySelector(selector) { return selector === '[name="plaintext"]' ? textarea : selector === 'button[type="submit"]' ? button : null; },
  addEventListener(name, callback) { formListeners[name] = callback; }
};
const feedback = { textContent: '', className: '', hidden: true };
const root = {
  dataset: { recipientUsernameToken: 'ilyag', senderUsernameToken: 'alice', recipientLabel: 'ilyag' },
  querySelector(selector) { return selector === '[data-private-message-form]' ? form : selector === '[data-role="private-message-feedback"]' ? feedback : null; }
};
global.window = {
  localStorage: { getItem(key) { return values.has(key) ? values.get(key) : null; }, setItem(key, value) { values.set(key, value); }, removeItem(key) { values.delete(key); } },
  crypto: { randomUUID() { return 'fixed-id'; } },
  __forumBrowserIdentity: { async ensureActionIdentity() {} },
  ForumPrivateMessages: { async prepareEnvelope(input) { return { encryptedEnvelope: '-----BEGIN PGP MESSAGE-----\\nCIPHERTEXT\\n-----END PGP MESSAGE-----', received: input }; } }
};
global.fetch = async function(url, options) {
  calls.push({ url, body: String(options.body) });
  return { ok: !shouldFail, async json() { return shouldFail ? { status: 'error', error: 'Temporary failure.' } : { status: 'ok', message: { message_id: 'private-fixed-id' } }; } };
};
global.document = { addEventListener() {}, querySelectorAll() { return []; } };
vm.runInThisContext(source);
window.ForumPrivateMessageComposer.bind(root);
const event = { preventDefault() {} };
(async () => {
  await formListeners.submit(event);
  const failedDraft = values.get('forum_private_message_draft:ilyag');
  shouldFail = false;
  await formListeners.submit(event);
  process.stdout.write(JSON.stringify({ calls, failedDraft, finalDraft: values.get('forum_private_message_draft:ilyag') || null, textarea: textarea.value, feedback: feedback.textContent }));
})().catch((error) => { process.stderr.write(error.stack || String(error)); process.exit(1); });
NODE;

        $result = $this->runScript($script);

        assertSame('/api/private_messages', $result['calls'][0]['url']);
        assertSame('/api/private_messages', $result['calls'][1]['url']);
        assertStringContains('"message_id":"private-fixed-id"', $result['calls'][0]['body']);
        assertStringContains('"message_id":"private-fixed-id"', $result['calls'][1]['body']);
        assertStringNotContains('private prose', $result['calls'][0]['body']);
        assertStringNotContains('private prose', $result['calls'][1]['body']);
        assertStringContains('private prose', (string) $result['failedDraft']);
        assertSame(null, $result['finalDraft']);
        assertSame('', $result['textarea']);
        assertSame('Private message sent.', $result['feedback']);
    }

    public function testProfileRendersComposerOnlyForApprovedOtherUser(): void
    {
        $renderer = new TemplateRenderer(__DIR__ . '/../templates', 'test');
        $profile = [
            'profile_slug' => 'openpgp-ilyag',
            'username' => 'ilyag',
            'username_token' => 'ilyag',
            'fallback_label' => 'ilyag',
            'is_approved' => 1,
            'thread_count' => 0,
            'post_count' => 0,
            'identity_id' => 'openpgp:ilyag',
            'bootstrap_post_id' => 'identity-ilyag',
            'bootstrap_thread_id' => 'thread-ilyag',
            'public_key' => 'PUBLIC KEY',
        ];
        $pageData = [
            'profile' => $profile,
            'self' => false,
            'identityHint' => '',
            'notice' => null,
            'error' => null,
            'viewerProfile' => ['username_token' => 'alice'],
            'isOwnProfile' => false,
            'canApprove' => false,
            'canPrivateMessage' => true,
        ];

        $html = $renderer->renderPageTemplate(
            'profile.php',
            $pageData,
            'ilyag - Profile',
            'profiles',
            ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js', '/assets/private_message_compose.js'],
        );

        assertStringContains('data-private-message-composer', $html);
        assertStringContains('data-recipient-username-token="ilyag"', $html);
        assertStringContains('data-sender-username-token="alice"', $html);
        assertStringContains('Encrypted to every approved key associated with this username.', $html);
        assertStringContains('/assets/private_message_compose.', $html);

        $pageData['canPrivateMessage'] = false;
        $withoutComposer = $renderer->renderPageTemplate('profile.php', $pageData, 'ilyag - Profile', 'profiles');
        assertStringNotContains('data-private-message-composer', $withoutComposer);
    }

    public function testUsernameRendersTheSameComposerOnlyForEligibleViewer(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-message-composer-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            foreach (ReadModelSchema::statements() as $statement) {
                $pdo->exec($statement);
            }
            $this->addProfile($pdo, 'openpgp:ilyag-one', 'openpgp-ilyag-one', 'ilyag', 1);
            $this->addProfile($pdo, 'openpgp:ilyag-two', 'openpgp-ilyag-two', 'ilyag', 1);
            $this->addProfile($pdo, 'openpgp:ilyag-pending', 'openpgp-ilyag-pending', 'ilyag', 0);
            $this->addProfile($pdo, 'openpgp:pending', 'openpgp-pending', 'pending', 0);

            $eligible = $this->renderUsername($databasePath, ['identity_id' => 'openpgp:alice', 'username_token' => 'alice', 'is_approved' => 1], 'ilyag');
            assertStringContains('data-private-message-composer', $eligible);
            assertStringContains('data-recipient-username-token="ilyag"', $eligible);
            assertStringContains('data-sender-username-token="alice"', $eligible);
            assertStringContains('/assets/private_message_compose.', $eligible);

            $self = $this->renderUsername($databasePath, ['identity_id' => 'openpgp:ilyag-one', 'username_token' => 'ilyag', 'is_approved' => 1], 'ilyag');
            $unapproved = $this->renderUsername($databasePath, ['identity_id' => 'openpgp:mallory', 'username_token' => 'mallory', 'is_approved' => 0], 'ilyag');
            $pendingOnly = $this->renderUsername($databasePath, ['identity_id' => 'openpgp:alice', 'username_token' => 'alice', 'is_approved' => 1], 'pending');
            foreach ([$self, $unapproved, $pendingOnly] as $html) {
                assertStringNotContains('data-private-message-composer', $html);
                assertStringNotContains('/assets/private_message_compose.', $html);
            }
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
        }
    }

    /** @param array<string, mixed>|null $viewer */
    private function renderUsername(string $databasePath, ?array $viewer, string $username): string
    {
        $services = new RouteServices(
            $databasePath,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): ?array => $viewer,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): ?array => $viewer,
        );
        $controller = new ProfilePageController(
            $services,
            static fn (): ?array => $viewer,
            static function (array $profile, bool $self, ?string $notice, ?string $error): string {
                return '';
            },
        );

        return (string) $controller->username($username);
    }

    private function addProfile(\PDO $pdo, string $identityId, string $profileSlug, string $username, int $approved): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key, is_approved
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key, :is_approved
             )'
        );
        $stmt->execute([
            'identity_id' => $identityId,
            'profile_slug' => $profileSlug,
            'username' => $username,
            'username_token' => $username,
            'fallback_label' => $username,
            'signer_fingerprint' => str_repeat('A', 40),
            'bootstrap_post_id' => 'identity-' . $profileSlug,
            'bootstrap_thread_id' => 'thread-' . $profileSlug,
            'public_key' => 'PUBLIC KEY ' . $profileSlug,
            'is_approved' => $approved,
        ]);
    }
}
