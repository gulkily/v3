<?php
declare(strict_types=1);
namespace ForumRewrite\Messaging;
use PDO;
use RuntimeException;
final class PrivateMessageHistorySyncDatabaseConfig
{
    public static function path(string $root, array $config = []): string
    {
        return trim((string) ($config['PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH'] ?? '')) ?: rtrim($root, '/\\') . '/state/private/message_history_sync.sqlite3';
    }
    public static function open(string $root, array $config = []): PDO
    {
        $path = self::path($root, $config);
        $messages = PrivateMessageDatabaseConfig::path($root, $config);
        $canonical = static function (string $file): string {
            if (realpath($file) !== false) return realpath($file);
            $parent = dirname($file); $tail = basename($file);
            while (!is_dir($parent) && dirname($parent) !== $parent) { $tail = basename($parent) . '/' . $tail; $parent = dirname($parent); }
            $parts = [];
            foreach (explode('/', (realpath($parent) ?: $parent) . '/' . $tail) as $part) {
                if ($part === '..') array_pop($parts); elseif ($part !== '' && $part !== '.') $parts[] = $part;
            }
            return '/' . implode('/', $parts);
        };
        if ($canonical($path) === $canonical($messages)
            || (is_file($path) && is_file($messages) && stat($path)['dev'] === stat($messages)['dev'] && stat($path)['ino'] === stat($messages)['ino'])) {
            throw new RuntimeException('History synchronization needs a separate private database.');
        }
        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) throw new RuntimeException('History synchronization storage unavailable.');
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT=>1]);
        @chmod($path, 0600);
        // Keep rollback journaling: compatible with existing deployment filesystems.
        // Writes are short and callers can retry after the bounded busy timeout.
        $pdo->exec('PRAGMA busy_timeout=1000');
        $pdo->exec('PRAGMA synchronous=FULL');
        return $pdo;
    }
}
