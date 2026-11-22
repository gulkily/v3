<?php
declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\GenericTextRecordParser;
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

    /** Read every candidate before making any destination request. */
    public function collect(): array
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

    public function preview(PublicKeyPublishDestination $destination): array
    {
        $rows = $this->collect();
        foreach ($rows as &$row) {
            if ($row['state'] !== 'ready') continue;
            $row = $this->destinationState($row, $destination);
        }
        return $rows;
    }

    public function writePlan(string $path, PublicKeyPublishDestination $destination, array $rows): void
    {
        $parent = realpath(dirname($path));
        if ($parent === false || $parent === $this->root || str_starts_with($parent, $this->root . '/')) {
            throw new RuntimeException('Save the plan outside the canonical repository, in an existing directory.');
        }
        $text = "Version: 1\nDestination: {$destination->url}\nRepository: {$this->root}\n\n";
        $blocks = [];
        foreach ($rows as $row) {
            $blocks[] = "Fingerprint: {$row['fingerprint']}\nName: {$row['name']}\nRegistration-Name: {$row['registration_name']}\nSource-SHA256: {$row['source_hash']}\nState: {$row['state']}";
        }
        if ($blocks !== []) $text .= implode("\n\n", $blocks) . "\n";
        if (strlen($text) > 2097152) throw new RuntimeException('Too many identities for a bounded plan. Choose a smaller source repository.');
        $handle = @fopen($parent . '/' . basename($path), 'xb');
        if ($handle === false) throw new RuntimeException('Cannot create plan. Choose a new filename; existing files are never overwritten.');
        try {
            if (fwrite($handle, $text) !== strlen($text) || !fflush($handle)) throw new RuntimeException('Cannot save plan; create a new preview.');
        } catch (\Throwable $error) { fclose($handle); unlink($parent . '/' . basename($path)); throw $error; }
        fclose($handle);
    }

    public function publish(string $path, array $fingerprints, PublicKeyPublishDestination $destination): array
    {
        $plan = $this->readPlan($path, $destination);
        $selected = [];
        foreach ($fingerprints as $fp) {
            $fp = strtoupper($fp);
            if (!preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/D', $fp) || !isset($plan[$fp])) {
                throw new RuntimeException('Select full fingerprints from this plan; create a new preview for additional identities.');
            }
            if (!in_array($plan[$fp]['State'], ['missing', 'existing'], true)) {
                throw new RuntimeException('Selection was refused or unknown at preview. Resolve it and create a new plan.');
            }
            $selected[$fp] = $plan[$fp];
        }
        if ($selected === []) throw new RuntimeException('Choose at least one --key=FINGERPRINT from the reviewed plan.');
        $inventory = $this->collect();
        // Validate the entire selection before any write, not just the first key.
        foreach ($selected as $fp => $entry) $this->checkEntry($inventory[$fp] ?? null, $entry);
        $results = [];
        foreach ($selected as $fp => $entry) {
            $row = $inventory[$fp];
            $row = $this->destinationState($row, $destination);
            if ($row['state'] === 'missing') {
                try {
                    $current = $this->collect();
                    $this->checkEntry($current[$fp] ?? null, $entry);
                } catch (RuntimeException) {
                    $row['state'] = 'refused'; $row['detail'] = 'Source changed during publication. Create a new preview.';
                    $results[$fp] = $row;
                    // Do not write the rest of a selection whose source is changing.
                    foreach (array_diff_key($selected, $results) as $pending => $_) {
                        $results[$pending] = $inventory[$pending] + ['destination_name' => ''];
                        $results[$pending]['state'] = 'unknown';
                        $results[$pending]['detail'] = 'Publication stopped after a source change. Create a new preview.';
                    }
                    return $results;
                }
                $attempt = $destination->register($current[$fp]['armor']);
                $row = $this->destinationState($row, $destination);
                if ($row['state'] === 'existing') {
                    $row['state'] = 'published'; $row['detail'] = 'Confirmed by destination read-back';
                } elseif ($row['state'] === 'missing') {
                    $row['state'] = $attempt['refused'] ? 'refused' : 'unknown';
                    $row['detail'] = $attempt['detail'] . ' Not confirmed; rerun the same selection after resolving the destination issue.';
                }
            }
            $results[$fp] = $row;
        }
        return $results;
    }

    private function checkEntry(?array $row, array $entry): void
    {
        if ($row === null || $row['state'] !== 'ready' || !hash_equals($entry['Source-SHA256'], $row['source_hash'])
            || $entry['Name'] !== $row['name'] || $entry['Registration-Name'] !== $row['registration_name']) {
            throw new RuntimeException('Selected source identity changed or is ineligible. Create a new preview before publishing.');
        }
    }

    private function readPlan(string $path, PublicKeyPublishDestination $destination): array
    {
        if (!is_file($path) || filesize($path) > 2097152) throw new RuntimeException('Choose an existing bounded publication plan.');
        $text = file_get_contents($path, false, null, 0, 2097153);
        if ($text === false || strlen($text) > 2097152) throw new RuntimeException('Cannot read plan; create a new preview.');
        try {
            $record = (new GenericTextRecordParser())->parse($text);
            if (array_keys($record->headers) !== ['Version', 'Destination', 'Repository'] || $record->headers['Version'] !== '1'
                || $record->headers['Destination'] !== $destination->url || $record->headers['Repository'] !== $this->root) {
                throw new RuntimeException();
            }
            $entries = [];
            foreach (trim($record->body) === '' ? [] : explode("\n\n", trim($record->body)) as $block) {
                $h = (new GenericTextRecordParser())->parse($block . "\n\n")->headers;
                if (array_keys($h) !== ['Fingerprint', 'Name', 'Registration-Name', 'Source-SHA256', 'State']
                    || !preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/D', $h['Fingerprint'])
                    || !preg_match('/^[a-f0-9]{64}$/D', $h['Source-SHA256']) || isset($entries[$h['Fingerprint']])) throw new RuntimeException();
                $entries[$h['Fingerprint']] = $h;
            }
            return $entries;
        } catch (RuntimeException) {
            throw new RuntimeException('Plan is invalid or names another source/destination. Create a new preview.');
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
