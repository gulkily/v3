<?php
declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\ReadModel\ProfileNames;
use ForumRewrite\Security\KeyRetirement;
use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

final class PublicKeyPublisher
{
    public readonly string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || preg_match('/[\p{C}]/u', $real) !== 0 || !is_dir($real . '/records/identity')) {
            throw new RuntimeException('Choose an existing canonical repository with --repository-root.');
        }
        $this->root = $real;
    }

    /** Inspect all candidates initially; revalidate one identity before its write. */
    public function collect(?string $fingerprint = null): array
    {
        foreach (['public-keys', 'identity', 'profile-names', 'key-retirements', 'key-retirement-acceptances'] as $family) {
            $dir = $this->root . '/records/' . $family;
            if (is_link($dir) || is_link($this->root . '/records')) throw new RuntimeException('Canonical input must not use symlinks.');
            if (file_exists($dir) && !is_dir($dir)) throw new RuntimeException('Canonical record family must be a directory.');
            foreach (glob($dir . '/*') ?: [] as $path) $this->read($path);
        }
        $names = ProfileNames::history($this->root);
        $rows = [];
        foreach (glob($this->root . '/records/identity/*.txt') ?: [] as $path) {
            $bytes = $this->read($path);
            $record = (new IdentityBootstrapRecordParser())->parse($bytes);
            if (basename($path) !== $record->postId . '.txt') throw new RuntimeException('Identity path does not match its record.');
            $fp = $record->signerFingerprint;
            if ($fingerprint !== null && $fp !== $fingerprint) continue;
            $key = $this->read($this->root . '/records/public-keys/openpgp-' . $fp . '.asc');
            if (trim($key) !== trim($record->armoredPublicKey)) throw new RuntimeException('Identity and public-key bytes disagree; repair the source before publishing.');
            $inspection = (new OpenPgpKeyInspector())->inspectPublicOnly($key);
            if ($inspection['fingerprint'] !== $fp || isset($rows[$fp])) throw new RuntimeException('Ambiguous or mismatched source fingerprint.');
            $history = $names[$record->identityId] ?? [];
            $name = $history === [] ? $record->username : $history[array_key_last($history)]['Username'];
            $state = 'ready'; $detail = '';
            try { $retired = KeyRetirement::read($this->root, $record->identityId); }
            catch (RuntimeException $error) { $retired = null; $state = 'refused'; $detail = 'Cannot verify source retirement evidence; repair it before previewing.'; }
            if ($retired !== null) { $state = 'refused'; $detail = 'Source key is retired'; }
            if ($name !== $inspection['username']) { $state = 'refused'; $detail = 'Source name differs from key registration name; transfer name history separately.'; }
            $rows[$fp] = ['fingerprint' => $fp, 'name' => $name, 'registration_name' => $inspection['username'],
                'armor' => $key, 'source_hash' => hash('sha256', $bytes . "\0" . $key . "\0" . serialize([$history, $retired, $state])),
                'state' => $state, 'detail' => $detail];
        }
        ksort($rows);
        return $rows;
    }

    public function preview(PublicKeyPublishDestination $destination, array $fingerprints = []): array
    {
        $rows = $this->select($this->collect(), $fingerprints);
        foreach ($rows as &$row) {
            if ($row['state'] !== 'ready') continue;
            $row = $this->destinationState($row, $destination);
        }
        return $rows;
    }

    public function publish(PublicKeyPublishDestination $destination, array $fingerprints = []): array
    {
        // Inspect the complete source and validate explicit selection before any request.
        $inventory = $this->select($this->collect(), $fingerprints);
        $results = [];
        foreach ($inventory as $fp => $entry) {
            $row = $entry;
            if ($row['state'] !== 'ready') { $results[$fp] = $row; continue; }
            $row = $this->destinationState($row, $destination);
            if ($row['state'] === 'missing') {
                try {
                    $current = $this->collect($fp);
                    $this->checkEntry($current[$fp] ?? null, $entry);
                } catch (RuntimeException) {
                    $row['state'] = 'refused'; $row['detail'] = 'Source changed during publication. Rerun the command.';
                    $results[$fp] = $row;
                    // Do not write the rest of a selection whose source is changing.
                    foreach (array_diff_key($inventory, $results) as $pending => $_) {
                        $results[$pending] = $inventory[$pending] + ['destination_name' => ''];
                        if ($results[$pending]['state'] === 'ready') {
                            $results[$pending]['state'] = 'unknown';
                            $results[$pending]['detail'] = 'Publication stopped after a source change. Rerun the command.';
                        }
                    }
                    return $results;
                }
                $attempt = $destination->register($current[$fp]['armor']);
                $row = $this->destinationState($row, $destination);
                if ($row['state'] === 'existing') {
                    $row['state'] = 'published'; $row['detail'] = 'Confirmed by destination read-back';
                } elseif ($row['state'] === 'missing') {
                    $row['state'] = $attempt['refused'] ? 'refused' : 'unknown';
                    $row['detail'] = $attempt['detail'] . ' Not confirmed; rerun the command after resolving the destination issue.';
                }
            }
            $results[$fp] = $row;
        }
        return $results;
    }

    private function select(array $inventory, array $fingerprints): array
    {
        if ($fingerprints === []) return $inventory;
        $selected = [];
        foreach ($fingerprints as $fp) {
            $fp = strtoupper($fp);
            if (!preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/D', $fp) || !isset($inventory[$fp])) {
                throw new RuntimeException('Choose a full fingerprint from the local repository; use --dry-run to list identities.');
            }
            $selected[$fp] = $inventory[$fp];
        }
        return $selected;
    }

    private function checkEntry(?array $row, array $entry): void
    {
        if ($row === null || $row['state'] !== 'ready' || !hash_equals($entry['source_hash'], $row['source_hash'])
            || $entry['name'] !== $row['name'] || $entry['registration_name'] !== $row['registration_name']) {
            throw new RuntimeException('Source changed during publication. Rerun the command.');
        }
    }

    private function destinationState(array $row, PublicKeyPublishDestination $destination): array
    {
        $remote = $destination->profile($row['fingerprint']);
        if (isset($remote['name']) && $remote['name'] !== $row['name']) {
            $remote['state'] = 'refused'; $remote['detail'] = 'Destination name differs; existing identity will not be changed.';
        }
        $row['destination_name'] = $remote['name'] ?? '';
        $row['state'] = $remote['state']; $row['detail'] = $remote['detail'];
        return $row;
    }

    private function read(string $path): string
    {
        $relative = substr($path, strlen($this->root) + 1);
        $current = $this->root;
        foreach (explode('/', $relative) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) throw new RuntimeException('Canonical input must not use symlinks.');
        }
        if (!is_file($path) || filesize($path) > 1048576) throw new RuntimeException('Canonical input is missing, not a file, or oversized.');
        $bytes = file_get_contents($path, false, null, 0, 1048577);
        if ($bytes === false || strlen($bytes) > 1048576) throw new RuntimeException('Cannot read canonical input.');
        return $bytes;
    }
}
