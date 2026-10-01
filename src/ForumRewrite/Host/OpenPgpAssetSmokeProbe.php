<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use Closure;
use InvalidArgumentException;

/**
 * Read-only inspection of the page-to-OpenPGP-runtime asset contract.
 *
 * The browser selects v5 for an insecure HTTP page and v6 for HTTPS. Rather
 * than duplicate the asset filenames here, this probe reads the paths emitted
 * by the live page and verifies the bundle the browser would select.
 */
final class OpenPgpAssetSmokeProbe
{
    /** @var list<string> */
    private const LEGACY_RAW_BUNDLE_PATHS = [
        '/assets/openpgp.v5.11.3.min.js',
        '/assets/openpgp.min.js',
    ];

    /** @var Closure(string): array{status:?int,headers:list<string>,body:?string,error:?string} */
    private Closure $request;

    /**
     * @param null|callable(string): array{status:?int,headers:list<string>,body:?string,error:?string} $request
     */
    public function __construct(?callable $request = null)
    {
        $this->request = $request === null
            ? Closure::fromCallable([$this, 'requestUrl'])
            : Closure::fromCallable($request);
    }

    /**
     * @return array{
     *   origin:string,
     *   transport:string,
     *   selected_version:string,
     *   page_url:string,
     *   page_status:?int,
     *   selected_url:?string,
     *   asset_status:?int,
     *   content_type:?string,
     *   redirects:list<string>,
     *   legacy_bundles:list<array{url:string,status:?int,content_type:?string,redirects:list<string>,failure_reason:?string,passed:bool}>,
     *   failure_reason:?string,
     *   passed:bool
     * }
     */
    public function probeOrigin(string $origin): array
    {
        $origin = self::normalizeOrigin($origin);
        $transport = (string) parse_url($origin, PHP_URL_SCHEME);
        $selectedVersion = $transport === 'https' ? 'v6' : 'v5';
        $assetKey = $selectedVersion === 'v6' ? 'openpgpV6' : 'openpgpV5';
        $pageUrl = $origin . '/';
        $pageResponse = ($this->request)($pageUrl);
        $pageRedirects = self::redirects($pageResponse['headers']);

        $result = [
            'origin' => $origin,
            'transport' => strtoupper($transport),
            'selected_version' => $selectedVersion,
            'page_url' => $pageUrl,
            'page_status' => $pageResponse['status'],
            'selected_url' => null,
            'asset_status' => null,
            'content_type' => null,
            'redirects' => $pageRedirects,
            'legacy_bundles' => [],
            'failure_reason' => null,
            'passed' => false,
        ];

        if ($pageResponse['error'] !== null) {
            $result['failure_reason'] = 'Unable to load the page: ' . $pageResponse['error'];

            return $result;
        }
        if ($pageRedirects !== []) {
            $result['failure_reason'] = 'Page request redirected to ' . $pageRedirects[0] . '.';

            return $result;
        }
        if (!self::isSuccessful($pageResponse['status'])) {
            $result['failure_reason'] = 'Page returned ' . self::statusDescription($pageResponse['status']) . '.';

            return $result;
        }

        $assetPaths = self::assetPathsFromPage((string) $pageResponse['body']);
        if (!isset($assetPaths[$assetKey])) {
            $result['failure_reason'] = 'Page does not declare ' . $assetKey . ' in window.__forumAssetPaths.';

            return $result;
        }

        $assetPath = $assetPaths[$assetKey];
        if (!self::isSiteRootRelativePath($assetPath)) {
            $result['failure_reason'] = 'Page declares an unsafe ' . $assetKey . ' path: ' . $assetPath . '.';

            return $result;
        }

        $assetUrl = $origin . $assetPath;
        $result['selected_url'] = $assetUrl;
        $assetResponse = ($this->request)($assetUrl);
        $assetRedirects = self::redirects($assetResponse['headers']);
        $result['asset_status'] = $assetResponse['status'];
        $result['content_type'] = self::contentType($assetResponse['headers']);
        $result['redirects'] = array_merge($pageRedirects, $assetRedirects);

        $selectedFailure = self::bundleFailure($assetResponse, $result['content_type'], 'Selected bundle');
        if ($selectedFailure !== null) {
            $result['failure_reason'] = $selectedFailure;

            return $result;
        }

        foreach (self::LEGACY_RAW_BUNDLE_PATHS as $legacyPath) {
            $legacy = $this->probeBundle($origin . $legacyPath);
            $result['legacy_bundles'][] = $legacy;
            $result['redirects'] = array_merge($result['redirects'], $legacy['redirects']);
            if (!$legacy['passed']) {
                $result['failure_reason'] = 'Legacy raw bundle failed: ' . $legacy['url'] . ' — ' . $legacy['failure_reason'];

                return $result;
            }
        }

        $result['passed'] = true;

        return $result;
    }

    /**
     * @return array{http:array<string,mixed>,https:array<string,mixed>}
     */
    public function probeHost(string $host): array
    {
        $host = self::normalizeHost($host);

        return [
            'http' => $this->probeOrigin('http://' . $host),
            'https' => $this->probeOrigin('https://' . $host),
        ];
    }

    public static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            throw new InvalidArgumentException('--origin requires a host name.');
        }
        if (str_contains($host, '://')) {
            $parts = parse_url($host);
            if (!is_array($parts) || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')
                || isset($parts['query']) || isset($parts['fragment'])) {
                throw new InvalidArgumentException('--origin must be a host name or an http(s) origin without a path.');
            }
            $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }
        if (preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
            throw new InvalidArgumentException('--origin must be a host name, optionally with a port.');
        }

        return $host;
    }

    public static function normalizeOrigin(string $origin): string
    {
        $origin = rtrim(trim($origin), '/');
        $parts = parse_url($origin);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (($parts['path'] ?? '') !== '') || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Origin must be an http(s) origin without a path.');
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** @return array<string, string> */
    private static function assetPathsFromPage(string $body): array
    {
        if (preg_match('/window\\.__forumAssetPaths\\s*=\\s*(\{[^;]*\})\\s*;/', $body, $matches) !== 1) {
            return [];
        }
        try {
            $decoded = json_decode($matches[1], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        $paths = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $paths[$key] = $value;
            }
        }

        return $paths;
    }

    private static function isSiteRootRelativePath(string $path): bool
    {
        return str_starts_with($path, '/assets/') && !str_starts_with($path, '//') && !str_contains($path, '?') && !str_contains($path, '#');
    }

    /** @param list<string> $headers */
    private static function redirects(array $headers): array
    {
        $redirects = [];
        foreach ($headers as $header) {
            if (preg_match('/^Location:\s*(.+)$/i', $header, $matches) === 1) {
                $redirects[] = trim($matches[1]);
            }
        }

        return $redirects;
    }

    /** @param list<string> $headers */
    private static function contentType(array $headers): ?string
    {
        foreach (array_reverse($headers) as $header) {
            if (preg_match('/^Content-Type:\s*([^;\s]+)/i', $header, $matches) === 1) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private static function isJavaScriptContentType(?string $contentType): bool
    {
        return $contentType !== null && in_array($contentType, [
            'application/javascript',
            'text/javascript',
            'application/ecmascript',
            'text/ecmascript',
            'application/x-javascript',
        ], true);
    }

    private static function looksLikeHtml(string $body): bool
    {
        return preg_match('/^\s*(?:<!doctype\s+html|<html\b|<head\b|<body\b)/i', substr($body, 0, 512)) === 1;
    }

    private static function isSuccessful(?int $status): bool
    {
        return $status !== null && $status >= 200 && $status < 300;
    }

    private static function statusDescription(?int $status): string
    {
        return $status === null ? 'no HTTP status' : 'HTTP ' . $status;
    }

    /**
     * @return array{url:string,status:?int,content_type:?string,redirects:list<string>,failure_reason:?string,passed:bool}
     */
    private function probeBundle(string $url): array
    {
        $response = ($this->request)($url);
        $contentType = self::contentType($response['headers']);
        $failure = self::bundleFailure($response, $contentType, 'Bundle');

        return [
            'url' => $url,
            'status' => $response['status'],
            'content_type' => $contentType,
            'redirects' => self::redirects($response['headers']),
            'failure_reason' => $failure,
            'passed' => $failure === null,
        ];
    }

    /** @param array{status:?int,headers:list<string>,body:?string,error:?string} $response */
    private static function bundleFailure(array $response, ?string $contentType, string $subject): ?string
    {
        $redirects = self::redirects($response['headers']);
        if ($response['error'] !== null) {
            return 'Unable to load ' . strtolower($subject) . ': ' . $response['error'];
        }
        if ($redirects !== []) {
            return $subject . ' redirected to ' . $redirects[0] . '.';
        }
        if (!self::isSuccessful($response['status'])) {
            return $subject . ' returned ' . self::statusDescription($response['status']) . '.';
        }
        if (!self::isJavaScriptContentType($contentType)) {
            return $subject . ' is not JavaScript (' . ($contentType ?? 'missing Content-Type') . ').';
        }
        if (self::looksLikeHtml((string) $response['body'])) {
            return $subject . ' returned an HTML error page despite its successful status.';
        }

        return null;
    }

    /** @return array{status:?int,headers:list<string>,body:?string,error:?string} */
    private function requestUrl(string $url): array
    {
        $error = null;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 15,
                'follow_location' => 0,
                'max_redirects' => 0,
                'header' => "Connection: close\r\nRange: bytes=0-2047\r\n",
            ],
        ]);
        set_error_handler(static function (int $_severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
        $headers = $http_response_header ?? [];
        $first = $headers[0] ?? '';
        preg_match('#^HTTP/\\S+\\s+(\d{3})(?:\s|$)#', $first, $matches);

        return [
            'status' => isset($matches[1]) ? (int) $matches[1] : null,
            'headers' => $headers,
            'body' => is_string($body) ? $body : null,
            'error' => $body === false ? ($error ?? 'Request failed.') : null,
        ];
    }
}
