<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

final class ClientDownload
{
    public static function serve(string $method, string $uri, string $publicRoot): bool
    {
        if (parse_url($uri, PHP_URL_PATH) !== '/frm.py') return false;
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405); header('Allow: GET, HEAD'); echo "method not allowed\n"; return true;
        }
        $file = $publicRoot . '/frm.py';
        if (!is_file($file)) { http_response_code(503); echo "Client unavailable. Ask the operator to deploy public/frm.py.\n"; return true; }
        http_response_code(200);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache');
        if ($method === 'GET') readfile($file);
        return true;
    }
}
