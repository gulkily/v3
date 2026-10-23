<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\KeyRevocationRecordParser;
use PDO;
use RuntimeException;

/** Repository-owned acceptance evidence plus the exact operator-signed request. */
final class KeyRevocations
{
    public static function path(string $id): string { return 'records/key-revocations/' . $id . '.txt'; }
    public static function receiptPath(string $id): string { return 'records/key-revocation-acceptances/' . $id . '.json'; }

    public static function encodeReceipt(string $contents, string $acceptedAt): string
    {
        $h = (new KeyRevocationRecordParser())->parse($contents);
        KeyRevocationRecordParser::timestamp($acceptedAt);
        if ($acceptedAt < $h['Revocation-Cutoff']) throw new RuntimeException('Revocation acceptance precedes cutoff.');
        return json_encode(['version' => 1, 'record_id' => $h['Record-ID'], 'sha256' => hash('sha256', $contents),
            'accepted_at' => $acceptedAt], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    public static function read(string $root, string $id): array
    {
        if (preg_match('/^key-revocation-[a-f0-9]{32}$/D', $id) !== 1) throw new RuntimeException('Invalid revocation ID.');
        $path = self::path($id); $receipt = self::receiptPath($id);
        foreach ([$path, $path . '.asc', $receipt] as $file) {
            if (!is_file($root . '/' . $file) || is_link($root . '/' . $file)) throw new RuntimeException('Incomplete key revocation evidence.');
        }
        $contents = (string) file_get_contents($root . '/' . $path);
        $h = (new KeyRevocationRecordParser())->parse($contents);
        $data = json_decode((string) file_get_contents($root . '/' . $receipt), true, 32, JSON_THROW_ON_ERROR);
        if ($h['Record-ID'] !== $id || ($data['version'] ?? null) !== 1 || ($data['record_id'] ?? '') !== $id
            || ($data['sha256'] ?? '') !== hash('sha256', $contents)
            || !is_string($data['accepted_at'] ?? null)
            || !ApprovalPolicy::isOperator($root, $h['Author-Identity-ID'])
            || ApprovalPolicy::isOperator($root, $h['Target-Identity-ID'])
            || ReactionSignature::status($root, $path, $contents, $h['Author-Identity-ID']) !== 'verified') {
            throw new RuntimeException('Invalid key revocation evidence.');
        }
        KeyRevocationRecordParser::timestamp($data['accepted_at']);
        if ($data['accepted_at'] < $h['Revocation-Cutoff']) throw new RuntimeException('Invalid revocation acceptance time.');
        return ['identity_id' => $h['Target-Identity-ID'], 'username' => $h['Username'],
            'operator_identity_id' => $h['Author-Identity-ID'], 'revocation_cutoff' => $h['Revocation-Cutoff'],
            'accepted_at' => $data['accepted_at'], 'source_path' => $path, 'receipt_path' => $receipt, 'record_id' => $id];
    }

    /** Invalid or incomplete authority evidence fails closed, never silently disappears. */
    public static function all(string $root): array
    {
        $ids = [];
        foreach (glob($root . '/records/key-revocations/*.txt') ?: [] as $file) $ids[basename($file, '.txt')] = true;
        foreach (glob($root . '/records/key-revocation-acceptances/*.json') ?: [] as $file) $ids[basename($file, '.json')] = true;
        $rows = [];
        foreach (array_keys($ids) as $id) {
            $row = self::read($root, $id);
            if (isset($rows[$row['identity_id']])) throw new RuntimeException('Duplicate terminal key revocation.');
            $rows[$row['identity_id']] = $row;
        }
        return $rows;
    }

    /** Apply only after historical attribution/scoring; do not remove sponsors from the graph. */
    public static function project(PDO $pdo, string $root): void
    {
        $pdo->exec('UPDATE profiles SET was_approved = is_approved WHERE is_revoked = 0');
        $update = $pdo->prepare('UPDATE profiles SET is_approved = 0, is_revoked = 1, revoked_at = ?, revocation_cutoff = ?,
            revoked_by_identity_id = ?, revocation_source_path = ? WHERE identity_id = ? AND username_token = ?');
        foreach (self::all($root) as $row) {
            $update->execute([$row['accepted_at'], $row['revocation_cutoff'], $row['operator_identity_id'], $row['source_path'], $row['identity_id'], $row['username']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Revoked account claim is missing or changed.');
        }
    }
}
