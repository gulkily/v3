<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;
use ForumRewrite\UsageReports\UsageReportDatabaseConfig;
use ForumRewrite\UsageReports\UsageReportStore;

/** Usage reports never travel with forum content: not in Backup archives, not in offline snapshots, not in Git. */
final class UsageReportsExportExclusionTest
{
    private const MARKER = 'usage-report-marker-7f3a91';

    public function testTheDefaultStoreIsIgnoredByGit(): void
    {
        $path = UsageReportDatabaseConfig::path(dirname(__DIR__));
        $relative = substr($path, strlen(dirname(__DIR__)) + 1);
        exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' check-ignore -q ' . escapeshellarg($relative), $output, $status);
        assertSame(0, $status, $relative . ' must be ignored by Git');
    }

    public function testBackupArchivesLeaveOutReportsEvenAStoreInsideTheRepository(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/post-1.txt', $w->post('post-1'));
        $w->git($root);
        // Worst case: a misconfigured USAGE_REPORTS_DATABASE_PATH inside the content repository.
        $this->storeMarker($root . '/usage_reports.sqlite3');
        $this->storeMarker($w->root . '/state/private/usage_reports.sqlite3');

        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        foreach (['tar.gz', 'zip'] as $kind) {
            $export = $backup->export($kind);
            $bytes = (string) stream_get_contents($export['stream']);
            fclose($export['stream']);
            $plain = $kind === 'tar.gz' ? (string) gzdecode($bytes) : $this->zipNamesAndContents($export['path']);
            assertTrue(str_contains($plain, 'post-1'), $kind . ' archive has the committed post');
            assertFalse(str_contains($plain, 'usage_reports'), $kind . ' archive names the usage store');
            assertFalse(str_contains($plain, self::MARKER), $kind . ' archive holds report text');
        }
    }

    public function testOfflineSnapshotsLeaveOutReports(): void
    {
        $root = sys_get_temp_dir() . '/usage-snapshot-' . bin2hex(random_bytes(5));
        mkdir($root . '/state/private', 0777, true);
        $source = $root . '/read_model.sqlite3';
        $_COOKIE = [];
        ob_start();
        (new ForumRewrite\Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $source))->handle('GET', '/');
        ob_end_clean();
        $this->storeMarker($root . '/state/private/usage_reports.sqlite3');

        (new PublicOfflineSnapshotBuilder($root))->build($source, $root . '/snapshot.sqlite3');
        $snapshot = (string) file_get_contents($root . '/snapshot.sqlite3');
        assertTrue(strlen($snapshot) > 0, 'snapshot built');
        assertFalse(str_contains($snapshot, self::MARKER), 'snapshot holds report text');
        $tables = (new PDO('sqlite:' . $root . '/snapshot.sqlite3'))->query("SELECT name FROM sqlite_master WHERE name LIKE 'usage%'")->fetchAll(PDO::FETCH_COLUMN);
        assertSame([], $tables);
    }

    private function storeMarker(string $path): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $store = new UsageReportStore(new PDO('sqlite:' . $path));
        assertTrue($store->add("usage-log v1\nclick\t0\t/\tpost\t" . self::MARKER . "\t-\n", 'ilyag', 'client', time())->isStored());
    }

    private function zipNamesAndContents(string $path): string
    {
        $zip = new ZipArchive();
        assertTrue($zip->open($path) === true, 'zip opens');
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $text .= $zip->getNameIndex($i) . "\n" . (string) $zip->getFromIndex($i) . "\n";
        }
        $zip->close();

        return $text;
    }
}
