<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

/** Which hashtags in a new post become thread labels (FORUM_HASHTAG_SELF_TAGS_ENABLED). */
final class LocalWriteServiceSelfTagsTest
{
    private const BODY = "This put a task in the strip (#zen and #meme).\n\n#issue #meta";

    public function testNewThreadIsTaggedOnlyByItsClosingHashtags(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $before = $this->labelRecordPaths($repositoryRoot);
        $service = $this->service($repositoryRoot, $databasePath, $artifactRoot);

        $service->createThread(['board_tags' => 'general', 'subject' => 'Gallery task', 'body' => self::BODY]);
        $service->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'All should open a gallery view #meta']);

        assertSame(['issue meta', 'meta'], $this->newLabelLines($repositoryRoot, $before));
    }

    public function testFlagOffRestoresTheEveryHashtagRule(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment(
            "Schema: site-feature-flags-v1\n\nFORUM_HASHTAG_SELF_TAGS_ENABLED: false\n"
        );
        $before = $this->labelRecordPaths($repositoryRoot);

        $this->service($repositoryRoot, $databasePath, $artifactRoot)
            ->createThread(['board_tags' => 'general', 'subject' => 'Gallery task', 'body' => self::BODY]);

        assertSame(['zen meme issue meta'], $this->newLabelLines($repositoryRoot, $before));
    }

    public function testSignedPreparedThreadFollowsTheSameRule(): void
    {
        [$repositoryRoot, , , $application] = $this->createTempEnvironment();
        $before = $this->labelRecordPaths($repositoryRoot);
        $key = $this->createSigningKey('self-tags-author');
        $_POST = ['public_key' => $key['public_key']];
        preg_match('/identity_id=(\S+)/', $this->renderMethod($application, 'POST', '/api/link_identity'), $linked);
        $identityId = (string) ($linked[1] ?? '');
        assertTrue($identityId !== '', 'identity linked');

        $prepared = json_decode($this->renderMethod($application, 'POST', '/api/prepare_thread?board_tags=general&subject=' . rawurlencode('Signed task')
            . '&body=' . rawurlencode(self::BODY) . '&author_identity_id=' . rawurlencode($identityId)), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $identityId,
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record']),
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_post'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        GpgHomeCleaner::dispose($key['home']);

        assertSame('ok', $finalized['status']);
        assertSame(['issue meta'], $this->newLabelLines($repositoryRoot, $before));
    }

    /** @param list<string> $before @return list<string> the Labels value of each new label record, sorted */
    private function newLabelLines(string $repositoryRoot, array $before): array
    {
        $lines = [];
        foreach (array_diff($this->labelRecordPaths($repositoryRoot), $before) as $path) {
            preg_match('/^(?:Labels|Tags): (.*)$/m', (string) file_get_contents($path), $match);
            $lines[] = $match[1] ?? '';
        }
        sort($lines);

        return $lines;
    }

    /** @return list<string> */
    private function labelRecordPaths(string $repositoryRoot): array
    {
        return glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [];
    }

    private function service(string $repositoryRoot, string $databasePath, string $artifactRoot): LocalWriteService
    {
        return new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot), featureFlags: FeatureFlagEvaluator::forRepository($repositoryRoot));
    }

    private function createTempEnvironment(?string $featureFlagsRecord = null): array
    {
        $suffix = bin2hex(random_bytes(6));
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-self-tags-repo-' . $suffix;
        mkdir($repositoryRoot, 0777, true);
        $this->runCommand(__DIR__, 'cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($repositoryRoot));
        if ($featureFlagsRecord !== null) {
            file_put_contents($repositoryRoot . '/records/instance/feature-flags.txt', $featureFlagsRecord);
        }
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-self-tags-db-' . $suffix . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-self-tags-public-' . $suffix;
        mkdir($artifactRoot, 0777, true);
        $this->runCommand($repositoryRoot, 'git init -q && git config user.name "Forum Rewrite" && git config user.email "forum-rewrite@example.invalid" && git add . && git -c commit.gpgsign=false commit -q -m init');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        return [$repositoryRoot, $databasePath, $artifactRoot, $application];
    }

    private function createSigningKey(string $username): array
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-self-tags-gpg-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->runCommand($home, 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0');
        register_shutdown_function(static fn () => GpgHomeCleaner::dispose($home));

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

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function runCommand(string $workdir, string $command): string
    {
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
