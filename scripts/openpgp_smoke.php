<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\OpenPgpAssetSmokeProbe;

try {
    $options = parseOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        printUsage(STDOUT);
        exit(0);
    }

    $host = (string) ($options['origin'] ?? 'zenmemes.com');
    $results = (new OpenPgpAssetSmokeProbe())->probeHost($host);

    fwrite(STDOUT, "OpenPGP live asset smoke test (read-only)\n");
    fwrite(STDOUT, "Target host: " . OpenPgpAssetSmokeProbe::normalizeHost($host) . "\n\n");
    foreach ($results as $result) {
        printResult($result);
    }

    $passed = $results['http']['passed'] && $results['https']['passed'];
    fwrite(STDOUT, $passed ? "Overall result: PASS\n" : "Overall result: FAIL\n");
    exit($passed ? 0 : 2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error: ' . $throwable->getMessage() . "\n\n");
    printUsage(STDERR);
    exit(1);
}

/**
 * @param list<string> $arguments
 * @return array{origin?:string,help?:bool}
 */
function parseOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }
        if (str_starts_with($argument, '--origin=')) {
            $origin = substr($argument, strlen('--origin='));
            if ($origin === '') {
                throw new InvalidArgumentException('Option requires a value: --origin');
            }
            $options['origin'] = $origin;
            continue;
        }

        throw new InvalidArgumentException('Unknown option: ' . $argument);
    }

    return $options;
}

/** @param array<string, mixed> $result */
function printResult(array $result): void
{
    fwrite(STDOUT, $result['transport'] . ' selects OpenPGP ' . $result['selected_version'] . "\n");
    fwrite(STDOUT, '  Page: ' . $result['page_url'] . ' — ' . statusDescription($result['page_status']) . "\n");
    fwrite(STDOUT, '  Selected URL: ' . ($result['selected_url'] ?? 'not available') . "\n");
    fwrite(STDOUT, '  Bundle response: ' . statusDescription($result['asset_status'])
        . '; Content-Type: ' . ($result['content_type'] ?? 'not available') . "\n");
    foreach ($result['legacy_bundles'] as $legacy) {
        fwrite(STDOUT, '  Legacy raw URL: ' . $legacy['url'] . ' — ' . statusDescription($legacy['status'])
            . '; Content-Type: ' . ($legacy['content_type'] ?? 'not available')
            . '; ' . ($legacy['passed'] ? 'PASS' : 'FAIL — ' . $legacy['failure_reason']) . "\n");
    }
    fwrite(STDOUT, '  Redirects: ' . ($result['redirects'] === [] ? 'none' : implode(', ', $result['redirects'])) . "\n");
    fwrite(STDOUT, '  Result: ' . ($result['passed'] ? 'PASS' : 'FAIL')
        . ($result['failure_reason'] === null ? '' : ' — ' . $result['failure_reason']) . "\n\n");
}

function statusDescription(mixed $status): string
{
    return is_int($status) ? 'HTTP ' . $status : 'no HTTP status';
}

function printUsage($stream): void
{
    fwrite($stream, "Usage: ./v3 openpgp smoke [--origin=zenmemes.com]\n\n");
    fwrite($stream, "Read-only check of the browser-selected OpenPGP bundle on both HTTP and HTTPS.\n");
    fwrite($stream, "The default target is zenmemes.com. --origin accepts a staging host, optionally\n");
    fwrite($stream, "written as an http(s) origin; the command checks both schemes for that host.\n");
}
