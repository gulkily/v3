<?php

declare(strict_types=1);

use ForumRewrite\Host\OpenPgpAssetSmokeProbe;

final class OpenPgpAssetSmokeProbeTest
{
    public function testProbesTheV5HttpAndV6HttpsBundlesDeclaredByTheirPages(): void
    {
        $requests = [];
        $probe = new OpenPgpAssetSmokeProbe(function (string $url) use (&$requests): array {
            $requests[] = $url;
            if (str_ends_with($url, '/')) {
                return $this->response(200, ['Content-Type: text/html; charset=utf-8'], $this->page([
                    'openpgpV5' => '/assets/openpgp.v5.abc.js',
                    'openpgpV6' => '/assets/openpgp.def.js',
                ]));
            }

            return $this->response(200, ['Content-Type: application/javascript; charset=utf-8'], '/* OpenPGP bundle */');
        });

        $results = $probe->probeHost('smoke.example');

        assertSame(true, $results['http']['passed']);
        assertSame('v5', $results['http']['selected_version']);
        assertSame('http://smoke.example/assets/openpgp.v5.abc.js', $results['http']['selected_url']);
        assertSame(true, $results['http']['legacy_bundles'][0]['passed']);
        assertSame('http://smoke.example/assets/openpgp.min.js', $results['http']['legacy_bundles'][1]['url']);
        assertSame(true, $results['https']['passed']);
        assertSame('v6', $results['https']['selected_version']);
        assertSame('https://smoke.example/assets/openpgp.def.js', $results['https']['selected_url']);
        assertSame([
            'http://smoke.example/',
            'http://smoke.example/assets/openpgp.v5.abc.js',
            'http://smoke.example/assets/openpgp.v5.11.3.min.js',
            'http://smoke.example/assets/openpgp.min.js',
            'https://smoke.example/',
            'https://smoke.example/assets/openpgp.def.js',
            'https://smoke.example/assets/openpgp.v5.11.3.min.js',
            'https://smoke.example/assets/openpgp.min.js',
        ], $requests);
    }

    public function testReportsMissingSelectedBundleDeclaration(): void
    {
        $probe = new OpenPgpAssetSmokeProbe(fn (string $_url): array => $this->response(200, [], $this->page([
            'openpgpV6' => '/assets/openpgp.v6.js',
        ])));

        $result = $probe->probeOrigin('http://smoke.example');

        assertSame(false, $result['passed']);
        assertSame('Page does not declare openpgpV5 in window.__forumAssetPaths.', $result['failure_reason']);
    }

    public function testReportsRedirectedSelectedBundle(): void
    {
        $probe = new OpenPgpAssetSmokeProbe(function (string $url): array {
            if (str_ends_with($url, '/')) {
                return $this->response(200, [], $this->page(['openpgpV5' => '/assets/openpgp.v5.js']));
            }

            return $this->response(302, ['Location: https://elsewhere.example/openpgp.js'], '');
        });

        $result = $probe->probeOrigin('http://smoke.example');

        assertSame(false, $result['passed']);
        assertSame(['https://elsewhere.example/openpgp.js'], $result['redirects']);
        assertSame('Selected bundle redirected to https://elsewhere.example/openpgp.js.', $result['failure_reason']);
    }

    public function testRejectsSuccessfulNonJavaScriptResponse(): void
    {
        $probe = new OpenPgpAssetSmokeProbe(function (string $url): array {
            if (str_ends_with($url, '/')) {
                return $this->response(200, [], $this->page(['openpgpV6' => '/assets/openpgp.v6.js']));
            }

            return $this->response(200, ['Content-Type: text/html; charset=utf-8'], '<!doctype html><html><body>Not found</body></html>');
        });

        $result = $probe->probeOrigin('https://smoke.example');

        assertSame(false, $result['passed']);
        assertSame('text/html', $result['content_type']);
        assertSame('Selected bundle is not JavaScript (text/html).', $result['failure_reason']);
    }

    public function testReportsAnUnavailableLegacyRawBundle(): void
    {
        $probe = new OpenPgpAssetSmokeProbe(function (string $url): array {
            if (str_ends_with($url, '/')) {
                return $this->response(200, [], $this->page(['openpgpV5' => '/assets/openpgp.v5.fingerprint.js']));
            }
            if (str_ends_with($url, 'openpgp.v5.fingerprint.js')) {
                return $this->response(200, ['Content-Type: application/javascript'], '/* selected bundle */');
            }

            return $this->response(404, ['Content-Type: text/html'], '<!doctype html><html>Not found</html>');
        });

        $result = $probe->probeOrigin('http://smoke.example');

        assertSame(false, $result['passed']);
        assertSame(false, $result['legacy_bundles'][0]['passed']);
        assertSame(
            'Legacy raw bundle failed: http://smoke.example/assets/openpgp.v5.11.3.min.js — Bundle returned HTTP 404.',
            $result['failure_reason'],
        );
    }

    public function testCliHelpAndUnknownOptionUseTheV3DispatcherContract(): void
    {
        [$helpExit, $helpStdout, $helpStderr] = $this->runV3('openpgp smoke --help');

        assertSame(0, $helpExit);
        assertStringContains('Usage: ./v3 openpgp smoke', $helpStdout);
        assertSame('', $helpStderr);

        [$invalidExit, $invalidStdout, $invalidStderr] = $this->runV3('openpgp smoke --not-an-option');

        assertSame(1, $invalidExit);
        assertSame('', $invalidStdout);
        assertStringContains('Unknown option: --not-an-option', $invalidStderr);
        assertStringContains('Usage: ./v3 openpgp smoke', $invalidStderr);
    }

    /** @param array<string, string> $paths */
    private function page(array $paths): string
    {
        return '<!doctype html><script>window.__forumAssetPaths = ' . json_encode($paths, JSON_THROW_ON_ERROR) . ';</script>';
    }

    /** @param list<string> $headers
     * @return array{status:?int,headers:list<string>,body:?string,error:?string}
     */
    private function response(int $status, array $headers, string $body): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'error' => null];
    }

    /** @return array{int,string,string} */
    private function runV3(string $arguments): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open('./v3 ' . $arguments, $descriptor, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run v3 command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
