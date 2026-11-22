<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

/**
 * Serves the Quill posting client, and tells an outdated copy that a newer one exists.
 *
 * Quill ships in Python, Perl and PHP (docs/reference/multi_language_scripts.md). Each version
 * names itself in its User-Agent, and its notice compares it with the file of the same language.
 *
 * Both entry points (Application and Host\FrontController) call serve() first on every request,
 * so the update notice reaches every response, API or page, without per-route code.
 */
final class ClientDownload
{
    /** Download path => file in the public root. /frm.py is quill.py's old name and keeps working. */
    public const PATHS = ['/quill.py' => 'quill.py', '/frm.py' => 'quill.py', '/quill.pl' => 'quill.pl', '/quill.php' => 'quill.php'];
    public const FILE = 'quill.py';
    /** User-Agent product => the file that version is compared with. */
    public const AGENTS = ['quill' => 'quill.py', 'quill-pl' => 'quill.pl', 'quill-php' => 'quill.php'];
    public const NOTICE_HEADER = 'X-Quill-Update';

    public static function serve(string $method, string $uri, string $publicRoot): bool
    {
        self::noticeOutdated($_SERVER['HTTP_USER_AGENT'] ?? '', $publicRoot);
        $name = self::PATHS[(string) parse_url($uri, PHP_URL_PATH)] ?? null;
        if ($name === null) return false;
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405); header('Allow: GET, HEAD'); echo "method not allowed\n"; return true;
        }
        $file = $publicRoot . '/' . $name;
        if (!is_file($file)) { http_response_code(503); echo "Client unavailable. Ask the operator to deploy public/$name.\n"; return true; }
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
        if (preg_match('#^(quill(?:-pl|-php)?)/(\S+)#', $userAgent, $m) !== 1) return null;
        $name = self::AGENTS[$m[1]];
        $running = self::versionKey($m[2]);
        $current = self::currentVersion($publicRoot, $name);
        if ($running === null || $current === null || self::versionKey($current) === null) return null;
        if ($running >= self::versionKey($current)) return null;
        return 'You are running Quill ' . $m[2] . '; this site serves ' . $current . '. Fetch it from /' . $name . '.';
    }

    /**
     * The QUILL_VERSION line of a served client, read from the top of the file: `QUILL_VERSION = "…"`
     * in Python, `const QUILL_VERSION = '…';` in PHP and `our $QUILL_VERSION = '…';` in Perl.
     */
    public static function currentVersion(string $publicRoot, string $name = self::FILE): ?string
    {
        static $cache = [];
        $file = $publicRoot . '/' . $name;
        $mtime = @filemtime($file);
        if ($mtime === false) return null;
        $key = $file . "\0" . $mtime;
        if (!array_key_exists($key, $cache)) {
            $head = (string) @file_get_contents($file, false, null, 0, 512);
            $cache[$key] = preg_match('/^(?:const |our \$)?QUILL_VERSION = ([\'"])([^\'"\n]+)\1/m', $head, $m) === 1 ? $m[2] : null;
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
