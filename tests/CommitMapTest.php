<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\Retraction\CommitMap;

final class CommitMapTest
{
    private const A = '1111111111111111111111111111111111111111';
    private const B = '2222222222222222222222222222222222222222';
    private const C = '3333333333333333333333333333333333333333';
    private const D = '4444444444444444444444444444444444444444';
    private const E = '5555555555555555555555555555555555555555';

    public function testEncodeRoundTripsSortsAndDropsUnchangedIdentities(): void
    {
        $files = CommitMap::encode('2026-11-01T10:15:00Z', 2, [self::B => self::D, self::A => self::C, self::E => self::E]);
        assertSame(['records/history-rewrites/history-rewrite-20261101T101500Z.txt'], array_keys($files));
        $bytes = $files['records/history-rewrites/history-rewrite-20261101T101500Z.txt'];
        assertSame("Format: history-rewrite-v1\nRewritten-At: 2026-11-01T10:15:00Z\nReason: purge\nReceipt-Count: 2\nPart: 1\nParts: 1\n\n"
            . 'Old-Commit: ' . self::A . "\nNew-Commit: " . self::C . "\n\n"
            . 'Old-Commit: ' . self::B . "\nNew-Commit: " . self::D . "\n\n", $bytes);
        $decoded = CommitMap::decode($bytes, 'records/history-rewrites/history-rewrite-20261101T101500Z.txt');
        assertSame([self::A => self::C, self::B => self::D], $decoded['map']);
        assertSame('2', $decoded['headers']['Receipt-Count']);
        // A run with nothing to map is still a valid, empty record.
        $empty = CommitMap::encode('2026-11-01T10:15:01Z', 0, [self::A => self::A]);
        assertSame([], CommitMap::decode(reset($empty))['map']);
        assertSame(true, SourcePathValidator::isValidCanonicalRecordPath('records/history-rewrites/history-rewrite-20261101T101500Z.txt'));
        assertSame(true, SourcePathValidator::isValidCanonicalRecordPath('records/history-rewrites/history-rewrite-20261101T101500Z-2.txt'));
        assertSame(false, SourcePathValidator::isValidCanonicalRecordPath('records/history-rewrites/notes.txt'));
    }

    public function testEncodeSplitsLargeRunsIntoParts(): void
    {
        $map = [];
        for ($i = 0; $i < CommitMap::BLOCKS_PER_PART + 2; $i++) {
            $map[sprintf('%040x', $i + 1)] = sprintf('%040x', 0x1000000 + $i);
        }
        $files = CommitMap::encode('2026-11-01T10:15:00Z', 1, $map);
        assertSame(['records/history-rewrites/history-rewrite-20261101T101500Z.txt', 'records/history-rewrites/history-rewrite-20261101T101500Z-2.txt'], array_keys($files));
        $first = CommitMap::decode($files['records/history-rewrites/history-rewrite-20261101T101500Z.txt'], 'records/history-rewrites/history-rewrite-20261101T101500Z.txt');
        $second = CommitMap::decode($files['records/history-rewrites/history-rewrite-20261101T101500Z-2.txt'], 'records/history-rewrites/history-rewrite-20261101T101500Z-2.txt');
        assertSame(CommitMap::BLOCKS_PER_PART, count($first['map']));
        assertSame(2, count($second['map']));
        assertSame(['1', '2'], [$first['headers']['Part'], $second['headers']['Part']]);
        assertSame(['2', '2'], [$first['headers']['Parts'], $second['headers']['Parts']]);
        assertSame(true, strlen($files['records/history-rewrites/history-rewrite-20261101T101500Z.txt']) < 16 * 1024 * 1024);
    }

    public function testDecodeRejectsMalformedRecords(): void
    {
        $good = CommitMap::encode('2026-11-01T10:15:00Z', 1, [self::A => self::C, self::B => self::D]);
        $bytes = reset($good);
        $cases = [
            'format' => str_replace('history-rewrite-v1', 'history-rewrite-v2', $bytes),
            'reason' => str_replace('Reason: purge', 'Reason: squash', $bytes),
            'timestamp' => str_replace('2026-11-01T10:15:00Z', '2026-11-01 10:15:00', $bytes),
            'count' => str_replace('Receipt-Count: 1', 'Receipt-Count: 01', $bytes),
            'part' => str_replace("Part: 1\nParts: 1", "Part: 2\nParts: 1", $bytes),
            'header order' => str_replace("Reason: purge\nReceipt-Count: 1", "Receipt-Count: 1\nReason: purge", $bytes),
            'extra header' => str_replace("Parts: 1\n", "Parts: 1\nNote: x\n", $bytes),
            'identity mapping' => str_replace('New-Commit: ' . self::C, 'New-Commit: ' . self::A, $bytes),
            'duplicate old' => str_replace('Old-Commit: ' . self::B, 'Old-Commit: ' . self::A, $bytes),
            'duplicate new' => str_replace('New-Commit: ' . self::D, 'New-Commit: ' . self::C, $bytes),
            'out of order' => str_replace(['Old-Commit: ' . self::A, 'Old-Commit: ' . self::B], ['Old-Commit: ' . self::B, 'Old-Commit: ' . self::A], $bytes),
            'uppercase' => str_replace(self::C, strtoupper(str_replace('3', 'c', self::C)), $bytes),
            'short sha' => str_replace(self::C, substr(self::C, 1), $bytes),
            'unknown block field' => str_replace('New-Commit: ' . self::D, 'Next-Commit: ' . self::D, $bytes),
            'unterminated block' => substr($bytes, 0, -1),
            'crlf' => str_replace("\n", "\r\n", $bytes),
        ];
        foreach ($cases as $name => $bad) {
            try { CommitMap::decode($bad); throw new LogicException('Accepted ' . $name); }
            catch (RuntimeException $e) { assertSame(true, $e->getMessage() !== ''); }
        }
        try { CommitMap::decode($bytes, 'records/history-rewrites/history-rewrite-20261101T101501Z.txt'); throw new LogicException('Accepted mismatched path'); }
        catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'History rewrite record path does not match')); }
        try { CommitMap::encode('2026-11-01T10:15:00Z', 1, [self::A => self::C, self::B => self::C]); throw new LogicException('Accepted many to one'); }
        catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'Commit map is not one to one')); }
        try { CommitMap::encode('2026-11-01T10:15:00Z', -1, []); throw new LogicException('Accepted negative count'); }
        catch (RuntimeException $e) { assertSame('Invalid receipt count.', $e->getMessage()); }
    }

    public function testResolveChainsAcrossRecordsAndRefusesToOverwrite(): void
    {
        $root = sys_get_temp_dir() . '/commit-map-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        assertSame(null, CommitMap::resolve($root, self::A));
        assertSame([], CommitMap::records($root));
        // First purge: A -> B, D -> E. Second purge, later: B -> C.
        $first = CommitMap::write($root, CommitMap::encode('2026-11-01T10:15:00Z', 1, [self::A => self::B, self::D => self::E]));
        $second = CommitMap::write($root, CommitMap::encode('2026-11-02T00:00:00Z', 1, [self::B => self::C]));
        assertSame(['records/history-rewrites/history-rewrite-20261101T101500Z.txt'], $first);
        assertSame(['records/history-rewrites/history-rewrite-20261102T000000Z.txt'], $second);
        assertSame(array_merge($first, $second), CommitMap::records($root));
        assertSame(self::C, CommitMap::resolve($root, self::A));
        assertSame(self::C, CommitMap::resolve($root, strtoupper(self::B)));
        assertSame(self::E, CommitMap::resolve($root, self::D));
        assertSame(null, CommitMap::resolve($root, self::C));
        assertSame(null, CommitMap::resolve($root, 'not-a-sha'));
        // Files that do not follow the path rule are ignored, not resolved.
        file_put_contents($root . '/records/history-rewrites/notes.txt', 'Old-Commit: ' . self::C . "\nNew-Commit: " . self::D . "\n\n");
        assertSame(null, CommitMap::resolve($root, self::C));
        try { CommitMap::write($root, CommitMap::encode('2026-11-02T00:00:00Z', 1, [self::A => self::D])); throw new LogicException('Overwrote'); }
        catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'History rewrite record already exists')); }
        assertSame(self::C, CommitMap::resolve($root, self::B));
        exec('rm -rf ' . escapeshellarg($root));
    }
}
