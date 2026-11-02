<?php
declare(strict_types=1);
namespace ForumRewrite\Security;

use ForumRewrite\Canonical\GenericTextRecordParser;
use PDO;
use RuntimeException;

/** Signed intent plus repository-owned acceptance, never reconstructed from today's authority. */
final class KeyRetirement
{
    public static function path(string $identity): string
    {
        if (!preg_match('/^openpgp:([a-f0-9]{40})$/D', $identity, $m)) throw new RuntimeException('Invalid retirement identity.');
        return 'records/key-retirements/openpgp-' . $m[1] . '.txt';
    }
    public static function acceptancePath(string $identity): string
    {
        return str_replace('/key-retirements/', '/key-retirement-acceptances/', self::path($identity));
    }
    public static function parse(string $bytes): array
    {
        if (strlen($bytes) > 16384) throw new RuntimeException('Retirement record is too large.');
        $record = (new GenericTextRecordParser())->parse($bytes);
        $h = $record->headers;
        if (array_keys($h) !== ['Version','Action-ID','Created-At','Actor-Identity-ID','Target-Identity-ID','Account','Authority','Replacement-Identity-ID','Context-SHA256'] || $record->body !== '' || $h['Version'] !== '1') throw new RuntimeException('Invalid retirement record.');
        foreach (['Actor-Identity-ID','Target-Identity-ID','Replacement-Identity-ID'] as $field) self::path($h[$field]);
        if ($h['Actor-Identity-ID'] === $h['Target-Identity-ID'] || $h['Replacement-Identity-ID'] === $h['Target-Identity-ID']
            || !preg_match('/^key-retirement-[a-f0-9]{32}$/D', $h['Action-ID'])
            || !preg_match('/^[a-z0-9._-]{1,80}$/D', $h['Account'])
            || !in_array($h['Authority'], ['member','operator'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $h['Context-SHA256'])) throw new RuntimeException('Invalid retirement authority or target.');
        self::date($h['Created-At']);
        return $h;
    }
    private static function date(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $date) throw new RuntimeException('Invalid retirement timestamp.');
    }
    public static function acceptance(string $bytes, string $signature, string $at): string
    {
        $record = self::parse($bytes); self::date($at);
        if ($at < $record['Created-At']) throw new RuntimeException('Retirement acceptance predates its record.');
        return "Version: 1\nAction-ID: {$record['Action-ID']}\nRecord-SHA256: " . hash('sha256', $bytes)
            . "\nSignature-SHA256: " . hash('sha256', $signature) . "\nAccepted-At: {$at}\n\n";
    }
    private static function readFile(string $root, string $path, int $limit): string
    {
        $full = $root;
        foreach (explode('/', $path) as $part) {
            $full .= '/' . $part;
            if (is_link($full)) throw new RuntimeException('Retirement evidence must not contain symlinks.');
        }
        if (!is_file($full) || filesize($full) > $limit) throw new RuntimeException('Retirement evidence is missing or oversized. Restore the accepted record, signature and receipt.');
        $bytes = file_get_contents($full, false, null, 0, $limit + 1);
        if ($bytes === false || strlen($bytes) > $limit) throw new RuntimeException('Unable to read retirement evidence.');
        return $bytes;
    }
    public static function read(string $root, string $identity): ?array
    {
        $path = self::path($identity); $receipt = self::acceptancePath($identity);
        if (!file_exists($root . '/' . $path) && !is_link($root . '/' . $path)
            && !file_exists($root . '/' . $receipt) && !is_link($root . '/' . $receipt)
            && !file_exists($root . '/' . $path . '.asc') && !is_link($root . '/' . $path . '.asc')) return null;
        $bytes = self::readFile($root, $path, 16384);
        $signature = self::readFile($root, $path . '.asc', 65536);
        $record = self::parse($bytes);
        if ($record['Target-Identity-ID'] !== $identity) throw new RuntimeException('Retirement target does not match its filename.');
        $accepted = self::readFile($root, $receipt, 4096);
        $headers = (new GenericTextRecordParser())->parse($accepted)->headers;
        $at = $headers['Accepted-At'] ?? '';
        if (!hash_equals(self::acceptance($bytes, $signature, $at), $accepted)
            || ReactionSignature::status($root, $path, $bytes, $record['Actor-Identity-ID']) !== 'verified') throw new RuntimeException('Invalid retirement signature or acceptance. Restore the accepted evidence.');
        return $record + ['Accepted-At' => $at, 'source_path' => $path, 'canonical_record' => $bytes];
    }
    public static function assertActive(string $root, string $identity): void
    {
        if ($identity !== '' && self::read($root, $identity) !== null) throw new RuntimeException('This key is retired. Use another approved key or ask an operator to approve a replacement.');
    }
    public static function all(string $root): iterable
    {
        $paths = array_unique(array_merge(glob($root . '/records/key-retirements/*.txt*') ?: [], glob($root . '/records/key-retirement-acceptances/*.txt') ?: []));
        $seen = [];
        foreach ($paths as $path) {
            if (!preg_match('/^openpgp-([a-f0-9]{40})\.txt$/D', preg_replace('/\.asc$/D', '', basename($path)), $m)) throw new RuntimeException('Invalid retirement evidence filename.');
            $id = 'openpgp:' . $m[1];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            yield $id => self::read($root, $id);
        }
    }
    /** Apply only after historical grant/attribution derivation. */
    public static function apply(PDO $pdo, string $root): void
    {
        $pdo->exec('UPDATE profiles SET was_approved=is_approved WHERE retired_at IS NULL AND was_approved IS NOT is_approved');
        $q = $pdo->prepare('UPDATE profiles SET is_approved=0, was_approved=1, retired_at=?, retired_by_identity_id=? WHERE identity_id=? AND (is_approved!=0 OR retired_at IS NOT ? OR retired_by_identity_id IS NOT ?)');
        foreach (self::all($root) as $id => $record) {
            $q->execute([$record['Accepted-At'], $record['Actor-Identity-ID'], $id, $record['Accepted-At'], $record['Actor-Identity-ID']]);
        }
    }
}
