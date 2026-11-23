<?php

declare(strict_types=1);

namespace ForumRewrite\Contacts;

use RuntimeException;

final class ContactBook
{
    private const HEADER = 'Contacts-Version: 1';
    private const MAX_BYTES = 32 * 1024 * 1024;
    private const MAX_CONTACTS = 30000;
    private const PREVIEW_TTL = 900;
    private const FIELDS = [
        'first_name' => 'First-Name',
        'last_name' => 'Last-Name',
        'url' => 'Profile-URL',
        'email' => 'Email',
        'company' => 'Company',
        'position' => 'Position',
        'connected_on' => 'Connected-On',
        'last_contacted' => 'Last-Contacted',
    ];

    private readonly string $previewPrefix;

    public function __construct(private readonly string $directory, string $launchSecret)
    {
        $this->previewPrefix = $directory . '/preview-' . hash('sha256', $launchSecret) . '-';
    }

    public function read(): array
    {
        $file = $this->directory . '/contacts.txt';
        $text = $this->readPrivateFile($file) ?? self::HEADER . "\n";
        if (!str_ends_with($text, "\n") || !preg_match('//u', $text)) {
            throw new RuntimeException('Invalid contacts snapshot.');
        }
        $blocks = explode("\n%%\n", substr($text, 0, -1));
        if (array_shift($blocks) !== self::HEADER || count($blocks) > self::MAX_CONTACTS) {
            throw new RuntimeException('Invalid contacts snapshot.');
        }
        $contacts = [];
        $fields = array_flip(self::FIELDS);
        foreach ($blocks as $block) {
            [$headers, $note] = array_pad(explode("\n\n", $block, 2), 2, null);
            $lines = explode("\n", $headers);
            $first = array_shift($lines);
            if ($note === null || !preg_match('/^Contact: ([a-f0-9]{32})$/D', $first, $match)
                || isset($contacts[$match[1]])) {
                throw new RuntimeException('Invalid contacts snapshot.');
            }
            $contact = ['id' => $match[1], 'note' => preg_replace('/^%%/m', '%', $note)];
            foreach ($lines as $line) {
                [$label, $value] = array_pad(explode(': ', $line, 2), 2, null);
                if ($value === null || !isset($fields[$label]) || isset($contact[$fields[$label]])) {
                    throw new RuntimeException('Invalid contacts snapshot.');
                }
                $contact[$fields[$label]] = $value;
            }
            if (($contact['first_name'] ?? '') === '' && ($contact['last_name'] ?? '') === '') {
                throw new RuntimeException('Invalid contacts snapshot.');
            }
            $contacts[$contact['id']] = $contact;
        }
        return ['contacts' => $contacts, 'revision' => hash('sha256', $text)];
    }

    public function preview(array $parsed): array
    {
        $current = $this->read();
        ['rows' => $rows, 'counts' => $counts, 'changes' => $changes] = LinkedInImportPlan::build($current['contacts'], $parsed);
        $id = bin2hex(random_bytes(16));
        $this->clearPreviews();
        if ($changes !== []) {
            $this->writePrivateFile($this->previewPath($id), json_encode([
                'revision' => $current['revision'], 'changes' => $changes, 'counts' => $counts,
            ], JSON_THROW_ON_ERROR));
        }
        return ['id' => $changes === [] ? null : $id, 'counts' => $counts, 'rows' => $rows];
    }

    public function confirm(string $id): array
    {
        $path = $this->previewPath($id);
        $text = $this->readPrivateFile($path);
        if ($text === null || filemtime($path) <= time() - self::PREVIEW_TTL) {
            @unlink($path);
            throw new RuntimeException('This preview has expired. Preview the file again.', 409);
        }
        $preview = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        $this->change(function (array $current) use ($preview): array {
            if (!hash_equals($current['revision'], $preview['revision'])) {
                throw new RuntimeException('Contacts changed since this preview. Preview the file again.', 409);
            }
            return array_replace($current['contacts'], $preview['changes']);
        });
        @unlink($path);
        return $preview['counts'];
    }

    public function cancel(string $id): void
    {
        @unlink($this->previewPath($id));
    }

    public function clearPreviews(): void
    {
        $prefix = basename($this->previewPrefix);
        foreach (@scandir($this->directory) ?: [] as $name) {
            if (str_starts_with($name, $prefix) && preg_match('/^[a-f0-9]{32}\.json$/D', substr($name, strlen($prefix)))) {
                @unlink($this->directory . '/' . $name);
            }
        }
    }

    public function clearExpiredPreviews(): void
    {
        foreach (@scandir($this->directory) ?: [] as $name) {
            if (!preg_match('/^preview-[a-f0-9]{64}-[a-f0-9]{32}\.json$/D', $name)) {
                continue;
            }
            $file = $this->directory . '/' . $name;
            clearstatcache(true, $file);
            if (@filemtime($file) <= time() - self::PREVIEW_TTL) {
                @unlink($file);
            }
        }
    }

    private function previewPath(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new RuntimeException('Preview the file again before importing.', 409);
        }
        return $this->previewPrefix . $id . '.json';
    }

    private function change(callable $patch): void
    {
        $path = $this->directory . '/contacts.lock';
        $this->checkPrivateFile($path);
        $lock = @fopen($path, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Could not open the Contacts lock.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not lock Contacts.');
            }
            $contacts = $patch($this->read());
            if (count($contacts) > self::MAX_CONTACTS) {
                throw new RuntimeException('The Contacts book can hold up to 30,000 contacts.', 413);
            }
            $blocks = [self::HEADER];
            foreach ($contacts as $contact) {
                $lines = ['Contact: ' . $contact['id']];
                foreach (self::FIELDS as $key => $header) {
                    if (isset($contact[$key]) && $contact[$key] !== '') {
                        $lines[] = $header . ': ' . $contact[$key];
                    }
                }
                $blocks[] = implode("\n", $lines) . "\n\n" . preg_replace('/^%/m', '%%', $contact['note'] ?? '');
            }
            $this->writePrivateFile($this->directory . '/contacts.txt', implode("\n%%\n", $blocks) . "\n");
        } finally {
            fclose($lock);
        }
    }

    private function checkPrivateFile(string $path): bool
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) {
            return false;
        }
        if (($stat['mode'] & 0170777) !== 0100600 || $stat['nlink'] !== 1
            || $stat['uid'] !== fileowner($this->directory)) {
            throw new RuntimeException('Unsafe Contacts file. Check that it is an owner-only regular file (0600).');
        }
        return true;
    }

    private function readPrivateFile(string $path): ?string
    {
        if (!$this->checkPrivateFile($path)) {
            return null;
        }
        $value = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if ($value === false || strlen($value) > self::MAX_BYTES) {
            throw new RuntimeException('Could not read the Contacts file.');
        }
        return $value;
    }

    private function writePrivateFile(string $path, string $value): void
    {
        if (strlen($value) > self::MAX_BYTES) {
            throw new RuntimeException('This change exceeds the Contacts storage limit.', 413);
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException('Contacts storage is not writable.');
        }
        $temporary = @tempnam($this->directory, '.contacts-');
        if ($temporary === false) {
            throw new RuntimeException('Could not prepare Contacts storage.');
        }
        try {
            if (dirname($temporary) !== $this->directory) {
                throw new RuntimeException('Could not prepare Contacts storage.');
            }
            $stream = @fopen($temporary, 'wb');
            if ($stream === false) {
                throw new RuntimeException('Could not open Contacts storage.');
            }
            try {
                if (@fwrite($stream, $value) !== strlen($value) || !@fflush($stream) || !@fsync($stream)) {
                    throw new RuntimeException('Could not write Contacts storage.');
                }
            } finally {
                fclose($stream);
            }
            if (!@rename($temporary, $path)) {
                throw new RuntimeException('Could not replace Contacts storage.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
