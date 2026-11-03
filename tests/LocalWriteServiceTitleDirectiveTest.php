<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\ThreadSubjectRecordParser;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Security\TitleDirectiveAuthority;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\TitleDirective;
use ForumRewrite\Write\LocalWriteService;

final class LocalWriteServiceTitleDirectiveTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    /** Unit-test identities for the authority class; write-path tests link real keys. */
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const STRANGER = 'openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testExtractFollowsHashtagLineRules(): void
    {
        assertSame('Better name', TitleDirective::extract("Some text\ntitle: Better name\nmore"));
        assertSame('Mixed Case', TitleDirective::extract("TITLE:   Mixed Case   "));
        assertSame('First', TitleDirective::extract("title: First\ntitle: Second"));
        assertSame('Real', TitleDirective::extract("> title: quoted\n  title: Real"));
        assertNullValue(TitleDirective::extract("> title: quoted only"));
        assertNullValue(TitleDirective::extract("title:   \nbody"));
        assertNullValue(TitleDirective::extract("the title: of this is not a directive"));
        assertNullValue(TitleDirective::extract("subtitle: nope"));
    }

    public function testThreadAuthorReplyWritesAuthoredSubjectRecordAndKeepsReplyBody(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot, $application] = $this->createTempEnvironment();
        $author = $this->linkIdentity($application, 'thread-author');
        $service = $this->service($repositoryRoot, $databasePath, $artifactRoot);
        $threadId = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Original title', 'body' => 'Authored root body.', 'author_identity_id' => $author])['post_id'];

        $result = $service->createReply([
            'thread_id' => $threadId, 'parent_id' => $threadId,
            'body' => "Thanks all.\ntitle: Renamed by the author\nMore words.",
            'author_identity_id' => $author,
        ]);

        $record = $this->singleSubjectRecord($repositoryRoot);
        assertSame($threadId, $record->threadId);
        assertSame('Renamed by the author', $record->subject);
        assertSame($author, $record->authorIdentityId);
        assertSame((string) $result['post_id'], $record->sourcePostId);
        $replyContents = (string) file_get_contents($repositoryRoot . '/' . $this->postPath($repositoryRoot, (string) $result['post_id']));
        assertSame(hash('sha256', $replyContents), $record->sourcePostSha256);
        assertSame(TitleDirective::REASON, $record->reason);
        assertTrue(str_contains($replyContents, "\ntitle: Renamed by the author\n"));
        $committed = trim($this->runCommand($repositoryRoot, 'git show --name-only --format= HEAD'));
        assertTrue(str_contains($committed, 'records/thread-subjects/' . $record->recordId . '.txt'));
    }

    public function testOperatorReplyWritesSubjectRecordOnAnyThread(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $service = $this->service($repositoryRoot, $databasePath, $artifactRoot);

        $service->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'title: Operator fix', 'author_identity_id' => self::OPERATOR]);

        $record = $this->singleSubjectRecord($repositoryRoot);
        assertSame('Operator fix', $record->subject);
        assertSame(self::OPERATOR, $record->authorIdentityId);
    }

    public function testUnauthorizedGuestQuotedAndEmptyDirectivesWriteNothing(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot, $application] = $this->createTempEnvironment();
        $author = $this->linkIdentity($application, 'thread-author');
        $strangerId = $this->linkIdentity($application, 'stranger');
        $service = $this->service($repositoryRoot, $databasePath, $artifactRoot);
        $threadId = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Original title', 'body' => 'Authored root body.', 'author_identity_id' => $author])['post_id'];
        $before = count($this->subjectRecordPaths($repositoryRoot));

        $stranger = $service->createReply(['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => 'title: Not mine to change', 'author_identity_id' => $strangerId]);
        $service->createReply(['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => 'title: Guest attempt']);
        $service->createReply(['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => "> title: quoted\nagree", 'author_identity_id' => $author]);
        $service->createReply(['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => "title:   \nnothing", 'author_identity_id' => $author]);

        assertSame($before, count($this->subjectRecordPaths($repositoryRoot)));
        $strangerContents = (string) file_get_contents($repositoryRoot . '/' . $this->postPath($repositoryRoot, (string) $stranger['post_id']));
        assertTrue(str_contains($strangerContents, 'title: Not mine to change'));
    }

    public function testDirectiveFlagOffLeavesOperatorLineAsPlainText(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment(
            "Schema: site-feature-flags-v1\n\nFORUM_TITLE_DIRECTIVE_ENABLED: false\n"
        );
        $service = $this->service($repositoryRoot, $databasePath, $artifactRoot);
        $before = count($this->subjectRecordPaths($repositoryRoot));

        $service->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'title: Flag is off', 'author_identity_id' => self::OPERATOR]);

        assertSame($before, count($this->subjectRecordPaths($repositoryRoot)));
    }

    public function testApprovedMemberAuthorityFollowsSecondFlag(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, attribution_verified INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec('CREATE TABLE profiles (identity_id TEXT PRIMARY KEY, is_approved INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec("INSERT INTO posts VALUES ('root-x', 1), ('root-unverified', 0)");
        $pdo->exec("INSERT INTO profiles VALUES ('" . self::STRANGER . "', 1), ('" . self::AUTHOR . "', 0)");
        $root = (new PostRecordParser())->parse("Post-ID: root-x\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: " . self::AUTHOR . "\n\nBody\n");
        $unverifiedRoot = (new PostRecordParser())->parse("Post-ID: root-unverified\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: " . self::AUTHOR . "\n\nBody\n");
        $noSeeds = sys_get_temp_dir() . '/forum-rewrite-title-authority-' . bin2hex(random_bytes(6));
        mkdir($noSeeds, 0777, true);
        $defaults = new FeatureFlagEvaluator();
        $widened = $defaults->withSiteValues(['FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS' => true]);
        $off = $defaults->withSiteValues(['FORUM_TITLE_DIRECTIVE_ENABLED' => false, 'FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS' => true]);

        assertTrue(TitleDirectiveAuthority::allows($noSeeds, $pdo, $root, self::AUTHOR, $defaults));
        assertFalse(TitleDirectiveAuthority::allows($noSeeds, $pdo, $unverifiedRoot, self::AUTHOR, $defaults));
        assertFalse(TitleDirectiveAuthority::allows($noSeeds, $pdo, $root, self::STRANGER, $defaults));
        assertTrue(TitleDirectiveAuthority::allows($noSeeds, $pdo, $root, self::STRANGER, $widened));
        assertFalse(TitleDirectiveAuthority::allows($noSeeds, $pdo, $root, self::AUTHOR, $off));
        assertFalse(TitleDirectiveAuthority::allows($noSeeds, $pdo, $root, self::STRANGER, $off));
        rmdir($noSeeds);
    }

    public function testSignedPreparedReplyFromThreadAuthorWritesSubjectRecordInSameCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot, $application] = $this->createTempEnvironment();
        $key = $this->createSigningKey('title-author');
        $_POST = ['public_key' => $key['public_key']];
        $identityId = $this->extractValue($this->renderMethod($application, 'POST', '/api/link_identity'), 'identity_id');
        $_POST = [];

        $thread = $this->finalizePrepared($application, $key['home'], $identityId,
            '/api/prepare_thread?board_tags=general&subject=' . rawurlencode('Signed subject') . '&body=' . rawurlencode('Signed body') . '&author_identity_id=' . rawurlencode($identityId));
        $threadId = (string) $thread['thread_id'];
        $reply = $this->finalizePrepared($application, $key['home'], $identityId,
            '/api/prepare_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId)
            . '&body=' . rawurlencode("title: Signed and renamed\nreply text") . '&author_identity_id=' . rawurlencode($identityId));

        $record = $this->singleSubjectRecord($repositoryRoot);
        assertSame($threadId, $record->threadId);
        assertSame('Signed and renamed', $record->subject);
        assertSame($identityId, $record->authorIdentityId);
        assertSame((string) $reply['post_id'], $record->sourcePostId);
        assertSame(hash('sha256', (string) $reply['canonical_record']), $record->sourcePostSha256);
        $committed = trim($this->runCommand($repositoryRoot, 'git show --name-only --format= HEAD'));
        assertTrue(str_contains($committed, 'records/thread-subjects/' . $record->recordId . '.txt'));
        assertTrue(str_contains($committed, (string) $reply['record_path']));
        GpgHomeCleaner::dispose($key['home']);
    }

    /**
     * @return array<string, mixed> prepared payload plus finalize status
     */
    private function finalizePrepared(Application $application, string $home, string $identityId, string $prepareRoute): array
    {
        $prepared = json_decode($this->renderMethod($application, 'POST', $prepareRoute), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $identityId,
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($home, $prepared['canonical_record']),
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_post'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('ok', $finalized['status']);

        return $prepared;
    }

    /** Links a fresh key through the normal API and returns its identity ID. */
    private function linkIdentity(Application $application, string $username): string
    {
        $key = $this->createSigningKey($username);
        $_POST = ['public_key' => $key['public_key']];
        $identityId = $this->extractValue($this->renderMethod($application, 'POST', '/api/link_identity'), 'identity_id');
        $_POST = [];
        GpgHomeCleaner::dispose($key['home']);

        return $identityId;
    }

    private function service(string $repositoryRoot, string $databasePath, string $artifactRoot): LocalWriteService
    {
        return new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot), featureFlags: FeatureFlagEvaluator::forRepository($repositoryRoot));
    }

    private function singleSubjectRecord(string $repositoryRoot): \ForumRewrite\Canonical\ThreadSubjectRecord
    {
        $paths = array_values(array_filter($this->subjectRecordPaths($repositoryRoot), static fn (string $path): bool => !str_contains($path, 'thread-subject-20260415153000-ab12cd34')));
        assertSame(1, count($paths));

        return (new ThreadSubjectRecordParser())->parse((string) file_get_contents($paths[0]));
    }

    /** @return list<string> */
    private function subjectRecordPaths(string $repositoryRoot): array
    {
        return glob($repositoryRoot . '/records/thread-subjects/*.txt') ?: [];
    }

    private function postPath(string $repositoryRoot, string $postId): string
    {
        foreach (\ForumRewrite\Canonical\CanonicalPathResolver::postCandidates($postId) as $candidate) {
            if (is_file($repositoryRoot . '/' . $candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException('Post file not found for ' . $postId);
    }

    /**
     * @return array{string,string,string,Application} repository, database, artifacts, application with a built read model
     */
    private function createTempEnvironment(?string $featureFlagsRecord = null): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-title-directive-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        if ($featureFlagsRecord !== null) {
            file_put_contents($repositoryRoot . '/records/instance/feature-flags.txt', $featureFlagsRecord);
        }
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-title-directive-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-title-directive-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        return [$repositoryRoot, $databasePath, $artifactRoot, $application];
    }

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git -c commit.gpgsign=false commit -m "Initialize test repository"');
    }

    /**
     * @return array{home:string,public_key:string}
     */
    private function createSigningKey(string $username): array
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-title-gpg-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->runCommand($home, 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0');
        register_shutdown_function(static function () use ($home): void {
            GpgHomeCleaner::dispose($home);
        });

        return ['home' => $home, 'public_key' => trim($this->runCommand($home, 'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export')) . "\n"];
    }

    private function signCanonicalRecord(string $home, string $canonicalRecord): string
    {
        $recordPath = $home . '/record.txt';
        @unlink($recordPath . '.asc');
        file_put_contents($recordPath, $canonicalRecord);
        $this->runCommand($home, 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home) . ' --armor --detach-sign --output ' . escapeshellarg($recordPath . '.asc') . ' ' . escapeshellarg($recordPath));

        return (string) file_get_contents($recordPath . '.asc');
    }

    private function extractValue(string $response, string $key): string
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded[$key])) {
            return (string) $decoded[$key];
        }
        foreach (explode("\n", trim($response)) as $line) {
            if (str_starts_with($line, $key . '=')) {
                return substr($line, strlen($key) + 1);
            }
        }
        throw new RuntimeException('Missing ' . $key . ' in response: ' . substr($response, 0, 300));
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}

if (!function_exists('assertNullValue')) {
    function assertNullValue(mixed $value): void
    {
        if ($value !== null) {
            throw new RuntimeException('Failed asserting that value is null: ' . var_export($value, true));
        }
    }
}
