<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\ThemeDirective;
use ForumRewrite\View\ThreadTheme;
use ForumRewrite\Write\LocalWriteService;

final class ThreadThemeTest
{
    /** The fixture's committed root-approved operator. */
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    /** A verified author who is not an operator. */
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    /** Someone else, also not an operator. */
    private const OTHER = 'openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testExtractReadsOnlyWholeUnquotedLines(): void
    {
        threadThemeAssertSame('midnight', ThemeDirective::extract("Darker in here.\ntheme: midnight\nenjoy"));
        threadThemeAssertSame('word97', ThemeDirective::extract('  THEME:   Word97  '));
        threadThemeAssertSame('none', ThemeDirective::extract('theme: none'));
        threadThemeAssertSame('midnight', ThemeDirective::extract("> theme: chicago\ntheme: midnight"));
        threadThemeAssertSame(null, ThemeDirective::extract('> theme: midnight'));
        threadThemeAssertSame(null, ThemeDirective::extract('the theme: midnight'));
        threadThemeAssertSame(null, ThemeDirective::extract('theme: midnight, please'));
        threadThemeAssertSame(null, ThemeDirective::extract('theme:'));
    }

    public function testLatestLineFromAuthorOrOperatorWins(): void
    {
        [$root, $database, $service, $application] = $this->environment();
        $plain = $this->thread($service, 'Plain', 'No theme here.');
        $fromRoot = $this->thread($service, 'Root line', "Welcome.\ntheme: word97", self::OPERATOR);
        $changed = $this->thread($service, 'Changed', 'Opening post', self::OPERATOR);
        $this->reply($service, $changed, 'theme: chicago', self::OPERATOR);
        $this->reply($service, $changed, "Darker.\ntheme: midnight", self::OPERATOR);
        $cleared = $this->thread($service, 'Cleared', 'theme: midnight', self::OPERATOR);
        $this->reply($service, $cleared, 'theme: none', self::OPERATOR);
        $unknown = $this->thread($service, 'Unknown', 'theme: midnight', self::OPERATOR);
        $this->reply($service, $unknown, 'theme: notatheme', self::OPERATOR);
        $byAuthor = $this->thread($service, 'Author', 'Opening post', self::OPERATOR);
        $authorReply = $this->reply($service, $byAuthor, 'theme: chicago', self::OPERATOR);
        $byOther = $this->thread($service, 'Other', 'Opening post', self::OPERATOR);
        $otherReply = $this->reply($service, $byOther, 'theme: chicago', self::OPERATOR);
        $retracted = $this->thread($service, 'Retracted', 'theme: word97', self::OPERATOR);
        $retractedReply = $this->reply($service, $retracted, 'theme: midnight', self::OPERATOR);
        $unverified = $this->thread($service, 'Unverified', 'Opening post', self::OPERATOR);
        $unverifiedReply = $this->reply($service, $unverified, 'theme: midnight', self::OPERATOR);

        $pdo = new PDO('sqlite:' . $database);
        $setAuthor = $pdo->prepare('UPDATE posts SET author_identity_id = ? WHERE post_id = ?');
        // A non-operator who wrote the thread may theme it; someone else may not.
        $setAuthor->execute([self::AUTHOR, $byAuthor]);
        $setAuthor->execute([self::AUTHOR, $authorReply]);
        $setAuthor->execute([self::AUTHOR, $byOther]);
        $setAuthor->execute([self::OTHER, $otherReply]);
        $pdo->prepare("UPDATE posts SET retracted_at = '2026-11-07T12:00:00Z' WHERE post_id = ?")->execute([$retractedReply]);
        $pdo->prepare('UPDATE posts SET attribution_verified = 0 WHERE post_id = ?')->execute([$unverifiedReply]);

        threadThemeAssertSame(null, ThreadTheme::forThread($pdo, $root, $plain));
        threadThemeAssertSame('word97', ThreadTheme::forThread($pdo, $root, $fromRoot));
        threadThemeAssertSame('midnight', ThreadTheme::forThread($pdo, $root, $changed));
        threadThemeAssertSame(null, ThreadTheme::forThread($pdo, $root, $cleared));
        threadThemeAssertSame('midnight', ThreadTheme::forThread($pdo, $root, $unknown));
        threadThemeAssertSame('chicago', ThreadTheme::forThread($pdo, $root, $byAuthor));
        threadThemeAssertSame(null, ThreadTheme::forThread($pdo, $root, $byOther));
        threadThemeAssertSame('word97', ThreadTheme::forThread($pdo, $root, $retracted));
        threadThemeAssertSame(null, ThreadTheme::forThread($pdo, $root, $unverified));

        $page = $this->render($application, '/threads/' . rawurlencode($changed));
        threadThemeAssertContains('data-page-theme="midnight"', $page);
        // The line stays in the post as written.
        threadThemeAssertContains('theme: midnight', $page);
        threadThemeAssertNotContains('data-page-theme=', $this->render($application, '/threads/' . rawurlencode($plain)));
    }

    public function testThreadThemeBeatsTheSiteListAndStaysWithinPermittedThemes(): void
    {
        [$root, $database, $service, $application] = $this->environment();
        $thread = $this->thread($service, 'Listed', 'Opening post', self::OPERATOR);
        $profiles = SiteProfileRegistry::all();
        $profiles['zenmemes']['pageThemes'] = ['/threads/' . $thread => 'engagify'];
        SiteProfileRegistry::setProfilesForTesting($profiles);
        try {
            threadThemeAssertContains('data-page-theme="engagify"', $this->render($application, '/threads/' . rawurlencode($thread)));
            $this->reply($service, $thread, 'theme: midnight', self::OPERATOR);
            threadThemeAssertContains('data-page-theme="midnight"', $this->render($application, '/threads/' . rawurlencode($thread)));
            $this->reply($service, $thread, 'theme: none', self::OPERATOR);
            threadThemeAssertContains('data-page-theme="engagify"', $this->render($application, '/threads/' . rawurlencode($thread)));
            // A theme another site owns is not permitted here.
            $this->reply($service, $thread, 'theme: qdb', self::OPERATOR);
            threadThemeAssertContains('data-page-theme="engagify"', $this->render($application, '/threads/' . rawurlencode($thread)));
        } finally {
            SiteProfileRegistry::setProfilesForTesting(null);
        }
    }

    public function testBakedThreadPageFollowsAThemeReply(): void
    {
        [$root, $database, $service, , $artifacts] = $this->environment();
        $thread = $this->thread($service, 'Baked', 'Opening post', self::OPERATOR);
        $builder = new StaticArtifactBuilder(dirname(__DIR__), $root, $database, $artifacts);
        $path = $artifacts . '/threads/' . $thread . '.html';

        threadThemeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
        threadThemeAssertNotContains('data-page-theme=', (string) file_get_contents($path));

        $this->reply($service, $thread, 'theme: midnight', self::OPERATOR);
        // The reply drops the baked page, so the next build carries the theme.
        threadThemeAssertSame(false, is_file($path));
        threadThemeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
        threadThemeAssertContains('data-page-theme="midnight"', (string) file_get_contents($path));
    }

    public function testThemeInputWritesTheHiddenHeader(): void
    {
        [$root, , $service] = $this->environment();
        $thread = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Hidden', 'body' => 'Opening post', 'author_identity_id' => self::OPERATOR, 'theme' => 'Word97'])['thread_id'];
        $reply = (string) $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Darker now.', 'author_identity_id' => self::OPERATOR, 'theme' => 'midnight'])['post_id'];
        $plain = (string) $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'No theme.', 'author_identity_id' => self::OPERATOR])['post_id'];
        $repository = new CanonicalRecordRepository($root);

        threadThemeAssertSame('word97', $repository->loadPost(CanonicalPathResolver::post($thread))->theme);
        threadThemeAssertSame('midnight', $repository->loadPost(CanonicalPathResolver::post($reply))->theme);
        threadThemeAssertSame(null, $repository->loadPost(CanonicalPathResolver::post($plain))->theme);
        // The header stays out of the text.
        threadThemeAssertSame("Darker now.\n", $repository->loadPost(CanonicalPathResolver::post($reply))->body);

        try {
            $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Bad', 'author_identity_id' => self::OPERATOR, 'theme' => 'two words']);
            throw new LogicException('Expected rejection');
        } catch (RuntimeException $error) {
            threadThemeAssertContains('theme must be a theme name or none', $error->getMessage());
        }
    }

    public function testHiddenAndVisibleSettingsShareOneLatestWins(): void
    {
        [$root, $database, $service, $application] = $this->environment();
        $thread = $this->thread($service, 'Mixed', 'Opening post', self::OPERATOR);
        $pdo = new PDO('sqlite:' . $database);
        $page = fn (): string => $this->render($application, '/threads/' . rawurlencode($thread));

        $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Quiet change.', 'author_identity_id' => self::OPERATOR, 'theme' => 'midnight']);
        threadThemeAssertSame('midnight', ThreadTheme::forThread($pdo, $root, $thread));
        threadThemeAssertContains('data-page-theme="midnight"', $page());
        threadThemeAssertNotContains('theme: midnight', $page());

        $this->reply($service, $thread, 'theme: chicago', self::OPERATOR);
        threadThemeAssertSame('chicago', ThreadTheme::forThread($pdo, $root, $thread));

        $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Back to normal.', 'author_identity_id' => self::OPERATOR, 'theme' => 'none']);
        threadThemeAssertSame(null, ThreadTheme::forThread($pdo, $root, $thread));

        // A header beats a line in the same post.
        $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'theme: chicago', 'author_identity_id' => self::OPERATOR, 'theme' => 'word97']);
        threadThemeAssertSame('word97', ThreadTheme::forThread($pdo, $root, $thread));

        // A hidden setting from someone else changes nothing.
        $other = (string) $service->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Mine now.', 'author_identity_id' => self::OPERATOR, 'theme' => 'midnight'])['post_id'];
        $pdo->prepare('UPDATE posts SET author_identity_id = ? WHERE post_id = ?')->execute([self::OTHER, $other]);
        threadThemeAssertSame('word97', ThreadTheme::forThread($pdo, $root, $thread));

        // A full rebuild reads every hidden header back from the records. (Order among
        // posts written in the same second can differ after a rebuild, so this checks the
        // stored values rather than which one is latest.)
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
        $rebuilt = new PDO('sqlite:' . $database);
        $stored = $rebuilt->prepare('SELECT theme FROM posts WHERE thread_id = ? AND theme IS NOT NULL ORDER BY theme');
        $stored->execute([$thread]);
        threadThemeAssertSame(['midnight', 'midnight', 'none', 'word97'], $stored->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testStartupRebuildsASchema51ReadModel(): void
    {
        [$root, $database, $service, , $artifacts] = $this->environment();
        $thread = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Old model', 'body' => 'Opening post', 'author_identity_id' => self::OPERATOR, 'theme' => 'midnight'])['thread_id'];

        $pdo = new PDO('sqlite:' . $database);
        $pdo->exec('ALTER TABLE posts DROP COLUMN theme');
        // Schema 51 is main's Inbox index without posts.theme.
        $pdo->exec("UPDATE metadata SET value = '51' WHERE key = 'schema_version'");
        $pdo = null;

        $application = new Application(dirname(__DIR__), $root, $database, $artifacts);
        threadThemeAssertContains('data-page-theme="midnight"', $this->render($application, '/threads/' . rawurlencode($thread)));
        $pdo = new PDO('sqlite:' . $database);
        threadThemeAssertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
    }

    public function testComposersOfferTheThemeChoiceAndTheFormPathWritesIt(): void
    {
        [$root, $database, $service, $application] = $this->environment();
        $newThread = $this->render($application, '/compose/thread');
        threadThemeAssertContains('<select name="theme" data-role="thread-theme-choice">', $newThread);
        threadThemeAssertContains('<option value="midnight">', $newThread);
        threadThemeAssertNotContains('value="none"', $newThread);
        threadThemeAssertNotContains('<option value="qdb">', $newThread);
        $threadPage = $this->render($application, '/threads/root-001');
        threadThemeAssertContains('Thread theme', $threadPage);
        threadThemeAssertContains('<option value="none">Clear thread theme</option>', $threadPage);

        $_POST = ['body' => 'Hello from the form', 'board_tags' => 'general', 'theme' => 'word97'];
        try {
            $this->render($application, 'POST /compose/thread');
        } finally {
            $_POST = [];
        }
        $latest = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' log -1 --name-only --format='));
        threadThemeAssertContains("Theme: word97\n", (string) file_get_contents($root . '/' . strtok($latest, "\n")));
    }

    /** @return array{string,string,LocalWriteService,Application,string} */
    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-thread-theme-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            $this->run($root, $command);
        }
        $database = sys_get_temp_dir() . '/forum-rewrite-thread-theme-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifacts = sys_get_temp_dir() . '/forum-rewrite-thread-theme-public-' . bin2hex(random_bytes(6));
        mkdir($artifacts, 0777, true);
        $application = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $this->render($application, '/');
        $service = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root), featureFlags: FeatureFlagEvaluator::forRepository($root));

        return [$root, $database, $service, $application, $artifacts];
    }

    private function thread(LocalWriteService $service, string $subject, string $body, ?string $author = null): string
    {
        $input = ['board_tags' => 'general', 'subject' => $subject, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createThread($input)['thread_id'];
    }

    private function reply(LocalWriteService $service, string $threadId, string $body, ?string $author = null): string
    {
        $input = ['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    private function render(Application $application, string $path): string
    {
        [$method, $path] = str_starts_with($path, 'POST ') ? ['POST', substr($path, 5)] : ['GET', $path];
        ob_start();
        $application->handle($method, $path);

        return (string) ob_get_clean();
    }

    private function run(string $directory, string $command): void
    {
        exec('cd ' . escapeshellarg($directory) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }
}

function threadThemeAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function threadThemeAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function threadThemeAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
    }
}
