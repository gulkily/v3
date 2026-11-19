<?php

declare(strict_types=1);

namespace ForumRewrite\Port;

use RuntimeException;

/**
 * Port files sent by the bookmarklet and not yet ported. The bookmarklet's form
 * arrives from another site, so the session cookie (SameSite=Lax) is missing:
 * the file waits here and the preview, a plain GET, knows who is looking.
 *
 * Lives outside the records tree, the read model and every export, like the
 * staged-post store. One folder per held port: `port.txt` is the port file and
 * `held.txt` holds `Key: value` lines. Held ports expire after a week.
 */
final class HeldPortStore
{
    public const LIFETIME_SECONDS = 7 * 86400;
    public const MAX_HELD = 200;

    public function __construct(private readonly string $directory) {}

    /** Keeps a port file and returns its short ID. A file already held returns the same ID. */
    public function hold(PortFile $port, int $now): string
    {
        $this->expire($now);
        $text = $port->toText();
        $id = substr(hash('sha256', $text), 0, 12);
        if ($this->find($id) !== null) {
            return $id;
        }
        if (count($this->ids()) >= self::MAX_HELD) {
            throw new RuntimeException('Too many ports are waiting. Try again later.');
        }
        $folder = $this->directory . '/' . $id;
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
            throw new RuntimeException('Unable to keep the port.');
        }
        $meta = 'Held-At: ' . gmdate('Y-m-d\TH:i:s\Z', $now) . "\nSource: " . PortFile::itemAddress($port->root()->id) . "\n";
        if (file_put_contents($folder . '/port.txt', $text, LOCK_EX) === false || file_put_contents($folder . '/held.txt', $meta, LOCK_EX) === false) {
            throw new RuntimeException('Unable to keep the port.');
        }
        return $id;
    }

    /** @return array{id:string, port:PortFile, held_at:string, error:string}|null */
    public function find(string $id): ?array
    {
        if (preg_match('/^[a-f0-9]{12}$/D', $id) !== 1) {
            return null;
        }
        $folder = $this->directory . '/' . $id;
        $text = @file_get_contents($folder . '/port.txt');
        $meta = @file_get_contents($folder . '/held.txt');
        if ($text === false || $meta === false) {
            return null;
        }
        $fields = [];
        foreach (explode("\n", $meta) as $line) {
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $fields[$key] = $value;
            }
        }
        try {
            $port = PortFile::parse($text);
        } catch (RuntimeException) {
            return null;
        }
        return ['id' => $id, 'port' => $port, 'held_at' => $fields['Held-At'] ?? '', 'error' => $fields['Error'] ?? ''];
    }

    /** Records why Port failed, so the preview shows it beside the retry button. */
    public function recordError(string $id, string $message): void
    {
        $held = $this->find($id);
        if ($held === null) {
            return;
        }
        $line = trim(str_replace(["\r", "\n"], ' ', $message));
        file_put_contents($this->directory . '/' . $id . '/held.txt',
            'Held-At: ' . $held['held_at'] . "\nSource: " . PortFile::itemAddress($held['port']->root()->id) . "\nError: " . mb_substr($line, 0, 300) . "\n", LOCK_EX);
    }

    public function discard(string $id): void
    {
        if (preg_match('/^[a-f0-9]{12}$/D', $id) !== 1) {
            return;
        }
        foreach (['port.txt', 'held.txt'] as $file) {
            @unlink($this->directory . '/' . $id . '/' . $file);
        }
        @rmdir($this->directory . '/' . $id);
    }

    /** @return list<array{id:string, port:PortFile, held_at:string, error:string}> newest first */
    public function all(int $now): array
    {
        $this->expire($now);
        $items = array_values(array_filter(array_map(fn (string $id): ?array => $this->find($id), $this->ids())));
        usort($items, static fn (array $a, array $b): int => strcmp($b['held_at'], $a['held_at']));
        return $items;
    }

    private function expire(int $now): void
    {
        foreach ($this->ids() as $id) {
            $held = $this->find($id);
            $at = $held === null ? false : strtotime($held['held_at']);
            if ($held === null || $at === false || $at < $now - self::LIFETIME_SECONDS) {
                $this->discard($id);
            }
        }
    }

    /** @return list<string> */
    private function ids(): array
    {
        $ids = [];
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            if (preg_match('/^[a-f0-9]{12}$/D', basename($folder)) === 1) {
                $ids[] = basename($folder);
            }
        }
        return $ids;
    }
}
