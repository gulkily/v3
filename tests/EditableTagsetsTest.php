<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\Import\ArchiveRecordCatalog;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Support\Tagsets;
use ForumRewrite\Write\LocalWriteService;

/** Operators edit the site tagsets (records/instance/tagsets.txt) with a signed change record. */
final class EditableTagsetsTest
{
    private const EVENT_TEXT = "Parent: issue\nTarget: reply, root\nUnlocks: claimed, resolved, closed, open\nExclusive: yes\n\nParent: event\nUnlocks: attend, interested\n";

    public function testOperatorSetsAndResetsTheSiteTagsets(): void
    {
        $this->withOperator(function (LocalWriteService $writer, string $root, string $operator, array $key): void {
            $result = $this->signed($writer, $operator, $key, ['operation' => 'set', 'text' => str_replace("\n", "\r\n", self::EVENT_TEXT) . "\n\n"]);
            assertSame('ok', $result['status']);
            assertSame(Tagsets::SOURCE_SITE, $result['source']);
            assertSame(self::EVENT_TEXT, (string) file_get_contents($root . '/records/instance/tagsets.txt'), 'line endings and trailing blank lines are normalized');
            assertSame(['attend', 'interested'], Tagsets::forRepository($root)->unlockedFor(['event']));
            $files = $this->git($root, 'show --name-only --format= HEAD');
            assertSame(['records/instance/tagsets.txt', $result['record_path'], $result['record_path'] . '.asc'],
                $this->sortedLike($this->git($root, 'show --name-only --format= HEAD'), $result['record_path']), 'one commit holds the record, its signature and the site file');
            assertStringContains('Set site tagsets by ' . $operator, $this->git($root, 'log -1 --format=%s'));
            assertStringContains("Operation: set\n", (string) file_get_contents($root . '/' . $result['record_path']));
            assertSame(true, SourcePathValidator::isValidCanonicalPath($result['record_path']));
            assertSame(true, SourcePathValidator::isValidCanonicalPath($result['record_path'] . '.asc'));
            assertSame(true, SourcePathValidator::isValidCanonicalPath('records/instance/tagsets.txt'));
            assertSame(true, ArchiveRecordCatalog::isRecordPath('records/instance/tagsets.txt'));

            assertSame('no', $writer->prepareTagsetsChange(['operation' => 'set', 'text' => self::EVENT_TEXT], $operator)['wrote_record'], 'saving the same text writes nothing');

            $reset = $this->signed($writer, $operator, $key, ['operation' => 'reset']);
            assertSame(Tagsets::SOURCE_DEFAULT, $reset['source']);
            assertSame(false, is_file($root . '/records/instance/tagsets.txt'));
            assertStringContains('D	records/instance/tagsets.txt', $this->git($root, 'show --name-status --format= HEAD'));
            assertSame(['claimed', 'resolved', 'closed', 'open'], Tagsets::forRepository($root)->unlockedFor(['issue']));
            assertSame('no', $writer->prepareTagsetsChange(['operation' => 'reset'], $operator)['wrote_record'], 'nothing to reset');
        });
    }

    public function testInvalidTextBadSignaturesAndNonOperatorsAreRefused(): void
    {
        $this->withOperator(function (LocalWriteService $writer, string $root, string $operator, array $key): void {
            $error = $this->errorOf(fn () => $writer->prepareTagsetsChange(['operation' => 'set', 'text' => "Parent: event\nUnlocks: like, flag\n"], $operator));
            assertSame("Line 2: like is a built-in tag and cannot be unlocked.\nLine 2: flag is a built-in tag and cannot be unlocked.", $error);

            $prepared = $writer->prepareTagsetsChange(['operation' => 'set', 'text' => self::EVENT_TEXT], $operator);
            $forged = $this->errorOf(fn () => $writer->finalizePreparedTagsetsChange($prepared + [
                'detached_signature' => $this->sign($key['home'], str_replace('attend', 'attack', $prepared['canonical_record'])),
            ], $operator));
            assertStringContains('Detached signature verification failed', $forged);
            assertSame(false, is_file($root . '/records/instance/tagsets.txt'));
            assertSame(false, is_file($root . '/' . $prepared['record_path']));

            $tampered = $this->errorOf(fn () => $writer->finalizePreparedTagsetsChange(array_merge($prepared, [
                'canonical_record' => str_replace('attend', 'attack', $prepared['canonical_record']),
                'detached_signature' => $this->sign($key['home'], str_replace('attend', 'attack', $prepared['canonical_record'])),
            ]), $operator));
            assertStringContains('canonical record mismatch', $tampered);

            $stranger = $writer->linkIdentity(['public_key' => $this->createSigningKey('tagsets-stranger')['public_key']])['identity_id'];
            assertStringContains('approved operator', $this->errorOf(fn () => $writer->prepareTagsetsChange(['operation' => 'reset'], $stranger)));
        });
    }

    private function sortedLike(string $files, string $recordPath): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $files)), static fn (string $line): bool => $line !== ''));
        $order = ['records/instance/tagsets.txt' => 0, $recordPath => 1, $recordPath . '.asc' => 2];
        usort($lines, static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));

        return $lines;
    }

    /** @param array{home:string,public_key:string} $key */
    private function signed(LocalWriteService $writer, string $operator, array $key, array $input): array
    {
        $prepared = $writer->prepareTagsetsChange($input, $operator);
        assertSame('yes', $prepared['wrote_record']);

        return $writer->finalizePreparedTagsetsChange($prepared + ['detached_signature' => $this->sign($key['home'], $prepared['canonical_record'])], $operator);
    }

    private function errorOf(callable $call): string
    {
        try {
            $call();
        } catch (\RuntimeException $error) {
            return $error->getMessage();
        }
        throw new RuntimeException('Expected an error.');
    }

    private function withOperator(callable $check): void
    {
        $base = sys_get_temp_dir() . '/forum-rewrite-editable-tagsets-' . bin2hex(random_bytes(6));
        $root = $base . '/repo';
        $database = $base . '/db/read.sqlite3';
        $artifacts = $base . '/public';
        mkdir($base . '/db', 0777, true);
        mkdir($artifacts, 0777, true);
        $key = $this->createSigningKey('tagsets-operator');
        try {
            exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . ' ' . escapeshellarg($root));
            $this->run($root, 'git init -q && git config user.name t && git config user.email t@example.invalid && git add . && git -c commit.gpgsign=false commit -qm init');
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $this->render($app, '/');
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $operator = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $fingerprint = substr($operator, strlen('openpgp:'));
            file_put_contents($root . '/records/approval-seeds/openpgp-' . $fingerprint . '.txt', "Approved-Identity-ID: {$operator}\nSeed-Reason: test operator\n\nTest trust anchor.\n");
            $this->run($root, 'git add records/approval-seeds && git -c commit.gpgsign=false commit -qm seed');
            $this->render(new Application(dirname(__DIR__), $root, $database, $artifacts), '/');
            $check(new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root)), $root, $operator, $key);
        } finally {
            Tagsets::useRepository(null);
            Tagsets::forget();
            GpgHomeCleaner::dispose($key['home']);
            exec('rm -rf ' . escapeshellarg($base));
        }
    }

    private function render(Application $app, string $path): string
    {
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        try {
            $app->handle('GET', $path);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /** @return array{home:string,public_key:string} */
    private function createSigningKey(string $username): array
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-tagsets-gpg-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->run($home, 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0');
        register_shutdown_function(static function () use ($home): void {
            GpgHomeCleaner::dispose($home);
        });

        return ['home' => $home, 'public_key' => trim($this->run($home, 'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export')) . "\n"];
    }

    private function sign(string $home, string $canonicalRecord): string
    {
        $recordPath = $home . '/record.txt';
        @unlink($recordPath . '.asc');
        file_put_contents($recordPath, $canonicalRecord);
        $this->run($home, 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home) . ' --armor --detach-sign --output ' . escapeshellarg($recordPath . '.asc') . ' ' . escapeshellarg($recordPath));

        return (string) file_get_contents($recordPath . '.asc');
    }

    private function git(string $root, string $arguments): string
    {
        return $this->run($root, 'git ' . $arguments);
    }

    private function run(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
