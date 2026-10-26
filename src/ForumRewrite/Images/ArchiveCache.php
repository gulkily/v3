<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** Private complete artifacts. A shared lock on the returned stream protects its lifetime. */
final class ArchiveCache
{
    public readonly string $root;

    public function __construct(string $directory, string $repository)
    {
        $this->root = rtrim($directory, '/') . '/archive-exports';
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Cannot prepare archive storage.');
        }
        for ($path = $this->root; $path !== '/' && $path !== '.'; $path = dirname($path)) {
            if (is_link($path)) throw new RuntimeException('Unsafe archive storage.');
        }
        $real = realpath($this->root);
        foreach ([$repository, dirname(__DIR__, 3) . '/public', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')] as $unsafe) {
            $unsafe = $unsafe === '' ? false : realpath($unsafe);
            if ($unsafe !== false && ($real === $unsafe || str_starts_with($real, $unsafe . '/'))) {
                throw new RuntimeException('Archive storage must be outside content and public directories.');
            }
        }
        chmod($this->root, 0700);
    }

    public function key(string $identity, string $kind): string
    {
        return hash('sha256', $identity . ':' . $kind);
    }

    public function open(string $identity, string $kind): ?array
    {
        $dir = $this->root . '/' . $this->key($identity, $kind);
        $extension = $kind === 'images' ? 'tar' : $kind;
        $path = $dir . '/archive.' . $extension;
        $metadata = $dir . '/metadata';
        if (is_link($dir) || is_link($path) || is_link($metadata) || !is_file($path) || !is_file($metadata)
            || filesize($metadata) > 4096) return null;
        $stream = @fopen($path, 'rb');
        if ($stream === false) return null;
        try {
            if (!flock($stream, LOCK_SH | LOCK_NB)) return null;
            $stat = fstat($stream);
            if ($stat === false || $stat['nlink'] < 1) return null;
            $raw = @file_get_contents($metadata);
            $meta = $raw === false ? null : json_decode($raw, true);
            if (!is_array($meta) || ($meta['identity'] ?? null) !== $identity || ($meta['kind'] ?? null) !== $kind
                || !is_string($meta['revision'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $meta['revision'])
                || ($meta['bytes'] ?? null) !== $stat['size'] || !is_string($meta['sha256'] ?? null)) return null;
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) !== $stat['size'] || !hash_equals($meta['sha256'], hash_final($hash))) return null;
            rewind($stream);
            $download = ['path' => $path, 'stream' => $stream, 'revision' => $meta['revision'], 'extension' => $extension, 'reused' => true];
            $stream = null; // Ownership passes to the caller; closing releases the reader lock.
            return $download;
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    /** Caller holds the build lock and has revalidated identity under the writer lock. */
    public function publish(array $built, string $identity, string $kind): array
    {
        $key = $this->key($identity, $kind);
        $stage = $this->root . '/.publish-' . bin2hex(random_bytes(10));
        if (!mkdir($stage, 0700)) throw new RuntimeException('Cannot stage archive.');
        try {
            $path = $stage . '/archive.' . $built['extension'];
            if (!rename($built['path'], $path)) throw new RuntimeException('Cannot publish archive.');
            $meta = ['identity' => $identity, 'kind' => $kind, 'revision' => $built['revision'],
                'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
            if ($meta['bytes'] === false || $meta['sha256'] === false
                || file_put_contents($stage . '/metadata', json_encode($meta, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Cannot identify complete archive.');
            }
            chmod($stage . '/metadata', 0600);
            $destination = $this->root . '/' . $key;
            // A corrupt artifact may still have a reader. Never replace its path in place.
            if (is_dir($destination)) {
                if (!rename($destination, $this->root . '/.obsolete-' . bin2hex(random_bytes(10)))) {
                    throw new RuntimeException('Cannot retire invalid archive.');
                }
            }
            if (!rename($stage, $destination)) throw new RuntimeException('Cannot publish complete archive.');
            $download = $this->open($identity, $kind);
            if ($download === null) throw new RuntimeException('Cannot open completed archive.');
            $download['reused'] = false;
            return $download;
        } finally {
            ImageBackup::removeTree($stage);
        }
    }
}
