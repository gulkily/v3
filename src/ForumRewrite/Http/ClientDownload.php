<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

/**
 * Serves the Quill posting client, and tells an outdated copy that a newer one exists.
 *
 * Both entry points (Application and Host\FrontController) call serve() first on every request,
 * so the update notice reaches every response, API or page, without per-route code.
 */
final class ClientDownload
{
    /** /quill.py is the client's name; /frm.py is its old name and keeps working. */
    public const PATHS = ['/quill.py', '/frm.py'];
    public const FILE = 'quill.py';
    public const NOTICE_HEADER = 'X-Quill-Update';

    public static function serve(string $method, string $uri, string $publicRoot): bool
    {
        self::noticeOutdated($_SERVER['HTTP_USER_AGENT'] ?? '', $publicRoot);
        if (!in_array(parse_url($uri, PHP_URL_PATH), self::PATHS, true)) return false;
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405); header('Allow: GET, HEAD'); echo "method not allowed\n"; return true;
        }
        $file = $publicRoot . '/' . self::FILE;
        if (!is_file($file)) { http_response_code(503); echo "Client unavailable. Ask the operator to deploy public/quill.py.\n"; return true; }
        http_response_code(200);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache');
        if ($method === 'GET') readfile($file);
        return true;
    }

    /**
     * The notice for a request from Quill older than the copy this site serves, or null.
     * Requests without a readable Quill version get none, so other clients see no change.
     */
    public static function notice(string $userAgent, string $publicRoot): ?string
    {
        if (preg_match('#^quill/(\S+)#', $userAgent, $m) !== 1) return null;
        $running = self::versionKey($m[1]);
        $current = self::currentVersion($publicRoot);
        if ($running === null || $current === null || self::versionKey($current) === null) return null;
        if ($running >= self::versionKey($current)) return null;
        return 'You are running Quill ' . $m[1] . '; this site serves ' . $current . '. Fetch it from /quill.py.';
    }

    /** The QUILL_VERSION line of the served client, read from the top of the file. */
    public static function currentVersion(string $publicRoot): ?string
    {
        static $cache = [];
        $file = $publicRoot . '/' . self::FILE;
        $mtime = @filemtime($file);
        if ($mtime === false) return null;
        $key = $file . "\0" . $mtime;
        if (!array_key_exists($key, $cache)) {
            $head = (string) @file_get_contents($file, false, null, 0, 512);
            $cache[$key] = preg_match('/^QUILL_VERSION = "([^"\n]+)"/m', $head, $m) === 1 ? $m[1] : null;
        }
        return $cache[$key];
    }

    /**
     * YYYY-MM-DD, optionally .N for a second release that day, as a comparable string; null otherwise.
     */
    private static function versionKey(string $version): ?string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\.(\d{1,4}))?$/', $version, $m) !== 1) return null;
        return $m[1] . '.' . str_pad($m[2] ?? '0', 4, '0', STR_PAD_LEFT);
    }

    private static function noticeOutdated(string $userAgent, string $publicRoot): void
    {
        $notice = self::notice($userAgent, $publicRoot);
        if ($notice !== null && !headers_sent()) header(self::NOTICE_HEADER . ': ' . $notice);
    }
}
