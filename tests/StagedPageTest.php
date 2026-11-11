<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Http\InboxPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Http\StagedPageController;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

final class StagedPageTest
{
    private const ALICE = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const BOB = 'openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const OPERATOR = 'openpgp:cccccccccccccccccccccccccccccccccccccccc';
    private const ALICE_POST = 'thread-20261111110000-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const BOB_POST = 'reply-20261111120000-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private string $repository = '';
    private string $database = '';

    /** Authors see their own staged posts, operators see all, everyone else sees the empty page. */
    public function testEachViewerSeesOnlyWhatTheyMay(): void
    {
        $this->withSite(function (): void {
            $guest = $this->render(null);
            assertStringContains('Nothing staged.', $guest);
            assertStringContains('/assets/staged_access.', $guest);
            assertSame(false, str_contains($guest, 'Cypher recap') || str_contains($guest, 'Line endings'));

            $other = $this->render($this->viewer('openpgp:dddddddddddddddddddddddddddddddddddddddd', 'dave'));
            assertStringContains('Nothing staged.', $other);
            assertSame(false, str_contains($other, 'Cypher recap'));

            $alice = $this->render($this->viewer(self::ALICE, 'alice'));
            assertStringContains('Cypher recap', $alice);
            assertStringContains('<code>U+1F3A4</code> at subject position 13.', $alice);
            assertStringContains('value="' . self::ALICE_POST . '"', $alice);
            assertStringContains('>Discard</button>', $alice);
            assertStringContains('Only you and operators see these.', $alice);
            assertSame(false, str_contains($alice, self::BOB_POST));

            $operator = $this->render($this->viewer(self::OPERATOR, 'operator'));
            assertStringContains(self::ALICE_POST, $operator);
            assertStringContains(self::BOB_POST, $operator);
            assertStringContains('Reply to <a href="/threads/' . self::ALICE_POST . '">', $operator);
            assertStringContains('· bob', $operator, 'author shown by name');
            assertStringContains(self::ALICE, $operator, 'an author without a profile is shown by key');

            // A staged post that has since been published is not listed.
            mkdir($this->repository . '/records/posts', 0777, true);
            file_put_contents($this->repository . '/records/posts/' . self::BOB_POST . '.txt', "published\n");
            assertSame(false, str_contains($this->render($this->viewer(self::OPERATOR, 'operator')), self::BOB_POST));
        });
    }

    public function testDiscardNeedsTheAuthorOrAnOperatorFromThisSite(): void
    {
        $this->withSite(function (): void {
            $alice = $this->viewer(self::ALICE, 'alice');
            $refused = $this->render($alice, 'POST', ['post_id' => self::ALICE_POST], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
            assertStringContains('Couldn’t discard. Nothing was removed.', $refused);
            assertStringContains('Cypher recap', $refused);

            $this->render($this->viewer('openpgp:dddddddddddddddddddddddddddddddddddddddd', 'dave'), 'POST', ['post_id' => self::ALICE_POST], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            assertStringContains('Cypher recap', $this->render($alice), 'another member cannot discard it');

            $this->render($alice, 'POST', ['post_id' => self::ALICE_POST], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            assertStringContains('Nothing staged.', $this->render($alice));

            $this->render($this->viewer(self::OPERATOR, 'operator'), 'POST', ['post_id' => self::BOB_POST], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            assertStringContains('Nothing staged.', $this->render($this->viewer(self::OPERATOR, 'operator')));
        });
    }

    public function testUnreadLinksToStagedOnlyWhenSomethingIsHeld(): void
    {
        $this->withSite(function (): void {
            assertStringContains('<a href="/staged/">Staged (1)</a>', $this->renderUnread($this->viewer(self::ALICE, 'alice')));
            assertStringContains('<a href="/staged/">Staged (2)</a>', $this->renderUnread($this->viewer(self::OPERATOR, 'operator')));
            assertSame(false, str_contains($this->renderUnread($this->viewer('openpgp:dddddddddddddddddddddddddddddddddddddddd', 'dave')), '/staged/'));
        });
    }

    /** @param callable(): void $test */
    private function withSite(callable $test): void
    {
        $root = sys_get_temp_dir() . '/staged-page-' . bin2hex(random_bytes(6));
        $this->repository = $root . '/repo';
        $this->database = $root . '/state/read_model.sqlite3';
        try {
            mkdir($this->repository . '/records/approval-seeds', 0777, true);
            mkdir(dirname($this->database), 0777, true);
            file_put_contents($this->repository . '/records/approval-seeds/openpgp-' . substr(self::OPERATOR, 8) . '.txt',
                'Approved-Identity-ID: ' . self::OPERATOR . "\nSeed-Reason: test operator\n\nTest trust anchor.\n");
            $pdo = new \PDO('sqlite:' . $this->database);
            foreach (\ForumRewrite\ReadModel\ReadModelSchema::statements() as $statement) $pdo->exec($statement);
            $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
                ->execute([self::BOB, 'openpgp-bob', 'bob', 'bob', 'bob', str_repeat('B', 40), 'identity-bob', 'thread-bob', 'KEY']);
            $store = $this->services(null)->writer()->stagedSubmissions();
            $store->stage(self::ALICE_POST, self::ALICE, null, 'Post-ID: ' . self::ALICE_POST . "\nSubject: Cypher recap \u{1F3A4}\n\nNotes\n", "sig\n",
                'subject must be printable ASCII.', 'subject', [['codepoint' => 'U+1F3A4', 'index' => 13]], strtotime('2026-11-11T11:00:00Z'));
            $store->stage(self::BOB_POST, self::BOB, self::ALICE_POST, 'Post-ID: ' . self::BOB_POST . "\r\n\r\nWindows\r\n", "sig\n",
                'Records use LF line endings, not CR LF.', 'record', [], strtotime('2026-11-11T12:00:00Z'));
            $test();
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    /** @param array<string, mixed>|null $viewer */
    private function services(?array $viewer): RouteServices
    {
        return new RouteServices(
            $this->database,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): ?array => $viewer,
            $this->repository,
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication($this->repository, dirname(__DIR__)),
            static fn (): ?array => $viewer,
        );
    }

    /**
     * @param array<string, mixed>|null $viewer
     * @param array<string, string> $input
     * @param array<string, string> $headers
     */
    private function render(?array $viewer, string $method = 'GET', array $input = [], array $headers = []): string
    {
        $server = $_SERVER;
        foreach ($headers as $name => $value) $_SERVER[$name] = $value;
        $_POST = $input;
        ob_start();
        try {
            (new StagedPageController($this->services($viewer), static fn (): ?array => $viewer))->show($method, $input);
        } finally {
            $_SERVER = $server;
            $_POST = [];
        }
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $viewer */
    private function renderUnread(array $viewer): string
    {
        ob_start();
        (new InboxPageController($this->services($viewer), static fn (): array => $viewer, null,
            static fn (): InboxMarkStore => new InboxMarkStore(new \PDO('sqlite::memory:'))))->show('GET');
        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function viewer(string $identityId, string $usernameToken): array
    {
        return ['identity_id' => $identityId, 'username_token' => $usernameToken, 'is_approved' => 1];
    }
}
