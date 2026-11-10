<?php

declare(strict_types=1);

// Small, stepped read-load probe for a v3 site. PHP 8.1+ with the curl extension.
// Same options, output, record format and exit codes as stress_probe.py and stress_probe.pl;
// see that script or docs/runbooks/server_stress_probe.md for the full description.
//
//   php scripts/stress_probe.php https://zenmemes.com                       # show the plan
//   php scripts/stress_probe.php https://zenmemes.com --confirm zenmemes.com
//
// Nothing is sent unless --confirm repeats the target's host name exactly. GET requests only.

const USER_AGENT = 'v3-stress-probe/1 (operator load test)';
const MAX_STEP_SECONDS = 60;
const MAX_CONCURRENCY = 32;
const MAX_RPS = 200;

function usage_error(string $message): never
{
    fwrite(STDERR, "usage: stress_probe.php BASE_URL [--confirm HOST] [--mode cached|uncached|both] [options]\n");
    fwrite(STDERR, "stress_probe.php: error: {$message}\n");
    exit(2);
}

function help_text(): string
{
    return <<<'TEXT'
usage: stress_probe.php BASE_URL [options]

Small, stepped read-load probe for a v3 site. Sends nothing unless --confirm
repeats the target's host name exactly; without it the plan is printed.

options:
  --confirm HOST        repeat the target host name to actually send traffic
  --mode MODE           cached, uncached or both (default cached)
  --steps LIST          comma-separated concurrent readers per step (default 1,2,4,8; max 32)
  --seconds N           duration of each step (default 20, max 60)
  --max-rps N           cap on total requests per second (default 50, max 200)
  --pause N             rest between steps in seconds (default 5)
  --threads N           thread pages to discover from the home page (default 5)
  --path PATH           extra path to include (repeatable)
  --p95-ms N            stop when p95 exceeds this (default 2000)
  --max-error-rate N    stop when 5xx/network errors exceed this share (default 0.02)
  --max-shed-rate N     stop when 503 Retry-After responses exceed this share (default 0.25)
  --timeout N           per-request timeout in seconds (default 30)
  --record FILE         also write the result as a Key: value text record

TEXT;
}

/** @param list<string> $argv */
function parse_args(array $argv): array
{
    $args = ['confirm' => '', 'mode' => 'cached', 'steps' => '1,2,4,8', 'seconds' => '20', 'max-rps' => '50', 'pause' => '5',
        'threads' => '5', 'path' => [], 'p95-ms' => '2000', 'max-error-rate' => '0.02', 'max-shed-rate' => '0.25',
        'timeout' => '30', 'record' => null];
    $positional = [];
    for ($i = 0; $i < count($argv); $i++) {
        $argument = $argv[$i];
        if ($argument === '-h' || $argument === '--help') {
            echo help_text();
            exit(0);
        }
        if (!str_starts_with($argument, '--')) {
            $positional[] = $argument;
            continue;
        }
        [$name, $value] = str_contains($argument, '=') ? explode('=', substr($argument, 2), 2) : [substr($argument, 2), null];
        if (!array_key_exists($name, $args)) {
            usage_error('unrecognized arguments: ' . $argument);
        }
        if ($value === null) {
            if ($i + 1 >= count($argv)) {
                usage_error("argument --{$name}: expected one argument");
            }
            $value = $argv[++$i];
        }
        if ($name === 'path') {
            $args['path'][] = $value;
        } else {
            $args[$name] = $value;
        }
    }
    if (count($positional) !== 1) {
        usage_error(count($positional) === 0 ? 'the following arguments are required: base_url' : 'unrecognized arguments: ' . implode(' ', array_slice($positional, 1)));
    }
    $args['base_url'] = $positional[0];
    if (!in_array($args['mode'], ['cached', 'uncached', 'both'], true)) {
        usage_error("argument --mode: invalid choice: '{$args['mode']}' (choose from 'cached', 'uncached', 'both')");
    }

    $url = parse_url($args['base_url']);
    if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || ($url['host'] ?? '') === ''
        || !in_array($url['path'] ?? '', ['', '/'], true) || isset($url['query']) || isset($url['user'])) {
        usage_error('base_url must be a bare origin such as https://zenmemes.com');
    }
    $args['scheme'] = $url['scheme'];
    $args['host'] = strtolower(trim($url['host'], '[]'));
    $args['port'] = $url['port'] ?? null;

    $steps = [];
    foreach (explode(',', $args['steps']) as $part) {
        if (trim($part) === '') {
            continue;
        }
        if (preg_match('/^\s*[+-]?\d+\s*$/', $part) !== 1) {
            usage_error('--steps must be comma-separated integers');
        }
        $steps[] = (int) $part;
    }
    if ($steps === [] || array_filter($steps, fn (int $step): bool => $step < 1 || $step > MAX_CONCURRENCY) !== []) {
        usage_error('each step must be between 1 and ' . MAX_CONCURRENCY);
    }
    $args['step_list'] = $steps;
    foreach (['seconds', 'max-rps', 'pause', 'p95-ms', 'max-error-rate', 'max-shed-rate', 'timeout'] as $name) {
        if (!is_numeric($args[$name])) {
            usage_error("argument --{$name}: invalid float value: '{$args[$name]}'");
        }
        $args[$name] = (float) $args[$name];
    }
    if (preg_match('/^\s*[+-]?\d+\s*$/', $args['threads']) !== 1) {
        usage_error("argument --threads: invalid int value: '{$args['threads']}'");
    }
    $args['threads'] = (int) $args['threads'];
    if (!($args['seconds'] > 0 && $args['seconds'] <= MAX_STEP_SECONDS)) {
        usage_error('--seconds must be between 0 and ' . MAX_STEP_SECONDS);
    }
    if (!($args['max-rps'] > 0 && $args['max-rps'] <= MAX_RPS)) {
        usage_error('--max-rps must be between 0 and ' . MAX_RPS);
    }
    if ($args['pause'] < 0 || $args['threads'] < 0 || $args['timeout'] <= 0) {
        usage_error('--pause, --threads and --timeout must not be negative');
    }
    foreach ($args['path'] as $path) {
        if (!str_starts_with($path, '/')) {
            usage_error('--path values must start with /');
        }
    }
    return $args;
}

function now(): float
{
    return hrtime(true) / 1e9;
}

function origin(array $args): string
{
    $host = str_contains($args['host'], ':') ? '[' . $args['host'] . ']' : $args['host'];
    return $args['scheme'] . '://' . $host . ($args['port'] === null ? '' : ':' . $args['port']);
}

function new_handle(array $args, array &$headers)
{
    $handle = curl_init();
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT_MS => (int) ($args['timeout'] * 1000),
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    return $handle;
}

function half_up(float $value): int
{
    // Same rounding as the Python and Perl probes.
    return (int) floor($value + 0.5);
}

/** @param list<float> $sorted */
function percentile(array $sorted, float $fraction): float
{
    if ($sorted === []) {
        return 0.0;
    }
    $index = min(count($sorted) - 1, max(0, half_up($fraction * (count($sorted) - 1))));
    return $sorted[$index];
}

/** @return list<string> */
function discover_paths(array $args): array
{
    $headers = [];
    $handle = new_handle($args, $headers);
    curl_setopt($handle, CURLOPT_URL, origin($args) . '/');
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($body === false) {
        fwrite(STDERR, 'Cannot read the home page: ' . curl_error($handle) . "\n");
        exit(1);
    }
    curl_close($handle);
    if ($status !== 200) {
        fwrite(STDERR, "The home page returned HTTP {$status}; not starting.\n");
        exit(1);
    }
    $found = [];
    preg_match_all('~href="(/threads/[A-Za-z0-9][A-Za-z0-9_-]{0,127})"~', (string) $body, $matches);
    foreach ($matches[1] as $path) {
        if (!in_array($path, $found, true)) {
            $found[] = $path;
        }
        if (count($found) >= $args['threads']) {
            break;
        }
    }
    return $found;
}

/** @return list<array{string, string}> */
function route_plan(array $args, array $threads): array
{
    $base = array_merge(['/', '/threads/', '/about/'], $threads, $args['path']);
    $plan = [];
    if (in_array($args['mode'], ['cached', 'both'], true)) {
        foreach ($base as $path) {
            $plan[] = ['cached', $path];
        }
    }
    if (in_array($args['mode'], ['uncached', 'both'], true)) {
        foreach (array_merge($base, ['/recent/', '/search?q=the']) as $path) {
            $plan[] = ['uncached', $path];
        }
    }
    return $plan;
}

function concrete_path(string $label, string $path): string
{
    if ($label !== 'uncached') {
        return $path;
    }
    return $path . (str_contains($path, '?') ? '&' : '?') . 'stress=' . random_int(0, 10 ** 12 - 1);
}

function run_step(array $args, array $plan, int $concurrency): array
{
    $multi = curl_multi_init();
    $deadline = now() + $args['seconds'];
    $interval = 1.0 / $args['max-rps'];
    $nextSlot = now();
    $samples = []; // [label, status, ms, cache header, retry-after]
    $errors = [];
    $readers = [];
    for ($i = 0; $i < $concurrency; $i++) {
        $readers[$i] = ['index' => $i, 'busy' => false, 'retired' => false, 'headers' => []];
        $readers[$i]['handle'] = new_handle($args, $readers[$i]['headers']);
    }
    $byHandle = [];
    foreach ($readers as $i => $reader) {
        $byHandle[spl_object_id($reader['handle'])] = $i;
    }

    $started = now();
    while (true) {
        $time = now();
        // Shared pacing: requests start at evenly spaced times so the total rate stays capped.
        foreach ($readers as $i => &$reader) {
            if ($reader['busy'] || $reader['retired'] || $time >= $deadline || $time < $nextSlot) {
                continue;
            }
            $nextSlot = max($nextSlot, $time) + $interval;
            [$label, $path] = $plan[$reader['index'] % count($plan)];
            $reader['index']++;
            $reader['label'] = $label;
            $reader['headers'] = [];
            $reader['started'] = now();
            curl_setopt($reader['handle'], CURLOPT_URL, origin($args) . concrete_path($label, $path));
            curl_multi_add_handle($multi, $reader['handle']);
            $reader['busy'] = true;
        }
        unset($reader);

        $busy = count(array_filter($readers, fn (array $reader): bool => $reader['busy']));
        $active = count(array_filter($readers, fn (array $reader): bool => !$reader['retired']));
        if ($busy === 0 && (now() >= $deadline || $active === 0)) {
            break;
        }
        curl_multi_exec($multi, $running);
        while (($info = curl_multi_info_read($multi)) !== false) {
            $handle = $info['handle'];
            $i = $byHandle[spl_object_id($handle)];
            $reader = &$readers[$i];
            $elapsed = (now() - $reader['started']) * 1000;
            if ($info['result'] !== CURLE_OK) {
                $name = curl_strerror($info['result']) ?: 'curl error ' . $info['result'];
                $errors[$name] = ($errors[$name] ?? 0) + 1;
                $samples[] = [$reader['label'], 0, $elapsed, '', ''];
            } else {
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $samples[] = [$reader['label'], $status, $elapsed, $reader['headers']['x-v3-high-traffic'] ?? '', $reader['headers']['retry-after'] ?? ''];
                if ($status === 429) {
                    $reader['retired'] = true;
                }
            }
            curl_multi_remove_handle($multi, $handle);
            $reader['busy'] = false;
            unset($reader);
        }
        if ($busy > 0) {
            curl_multi_select($multi, max(0.001, min(0.05, $nextSlot - now())));
        } else {
            $wait = min($nextSlot, $deadline) - now();
            if ($wait > 0) {
                usleep((int) ($wait * 1e6));
            }
        }
    }
    $wall = now() - $started;
    foreach ($readers as $reader) {
        curl_close($reader['handle']);
    }
    curl_multi_close($multi);

    $statuses = [];
    $latencies = [];
    $shed = 0;
    $failures = 0;
    foreach ($samples as [$label, $status, $ms, $cache, $retryAfter]) {
        $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        $latencies[] = $ms;
        $isShed = $status === 503 && $retryAfter !== '';
        $shed += $isShed ? 1 : 0;
        $failures += ($status === 0 || ($status >= 500 && !$isShed)) ? 1 : 0;
    }
    sort($latencies);
    ksort($statuses);
    $total = count($samples);
    $labels = array_values(array_unique(array_map(fn (array $sample): string => $sample[0], $samples)));
    sort($labels);
    $routes = [];
    foreach ($labels as $label) {
        $values = [];
        $hits = 0;
        foreach ($samples as $sample) {
            if ($sample[0] === $label) {
                $values[] = $sample[2];
                $hits += $sample[3] === 'hit' ? 1 : 0;
            }
        }
        sort($values);
        $routes[$label] = ['requests' => count($values), 'p50_ms' => half_up(percentile($values, .5)),
            'p95_ms' => half_up(percentile($values, .95)), 'cache_hits' => $hits];
    }
    $statusNames = [];
    foreach ($statuses as $code => $count) {
        $statusNames[$code === 0 ? 'network' : (string) $code] = $count;
    }
    return [
        'concurrency' => $concurrency, 'seconds' => $wall, 'requests' => $total,
        'requests_per_second' => $wall > 0 ? $total / $wall : 0.0,
        'p50_ms' => half_up(percentile($latencies, .5)), 'p95_ms' => half_up(percentile($latencies, .95)),
        'p99_ms' => half_up(percentile($latencies, .99)), 'max_ms' => $latencies === [] ? 0 : half_up(end($latencies)),
        'statuses' => $statusNames, 'network_errors' => $errors, 'shed_503' => $shed, 'failures' => $failures,
        'error_rate' => $total > 0 ? $failures / $total : 1.0, 'shed_rate' => $total > 0 ? $shed / $total : 0.0,
        'routes' => $routes,
    ];
}

function stop_reason(array $args, array $step): string
{
    if ($step['requests'] === 0) {
        return 'no requests completed';
    }
    if (($step['statuses']['429'] ?? 0) > 0) {
        return 'the server answered 429 Too Many Requests';
    }
    if ($step['error_rate'] > $args['max-error-rate']) {
        return sprintf('error rate %.1f%% is over %.1f%%', $step['error_rate'] * 100, $args['max-error-rate'] * 100);
    }
    if ($step['shed_rate'] > $args['max-shed-rate']) {
        return sprintf('load-shedding 503s at %.1f%% are over %.1f%%', $step['shed_rate'] * 100, $args['max-shed-rate'] * 100);
    }
    if ($step['p95_ms'] > $args['p95-ms']) {
        return sprintf('p95 %d ms is over %d ms', $step['p95_ms'], $args['p95-ms']);
    }
    return '';
}

function joined_counts(array $counts): string
{
    return implode(', ', array_map(fn ($name, $count): string => $name . ' ' . $count, array_keys($counts), $counts));
}

function print_step(array $step): void
{
    $statuses = implode(' ', array_map(fn ($name, $count): string => $name . ':' . $count, array_keys($step['statuses']), $step['statuses']));
    printf("  c=%-3d %6d req %7.1f/s  p50 %5d  p95 %5d  p99 %5d  max %5d ms  [%s]\n", $step['concurrency'], $step['requests'],
        $step['requests_per_second'], $step['p50_ms'], $step['p95_ms'], $step['p99_ms'], $step['max_ms'], $statuses);
    foreach ($step['routes'] as $label => $route) {
        $hit = '';
        if ($label === 'cached' && $route['requests'] > 0) {
            $hit = sprintf('  cache hits %d%%', half_up(100 * $route['cache_hits'] / $route['requests']));
        }
        printf("        %-8s %6d req  p50 %5d  p95 %5d ms%s\n", $label, $route['requests'], $route['p50_ms'], $route['p95_ms'], $hit);
    }
    if ($step['network_errors'] !== []) {
        echo '        network errors: ' . joined_counts($step['network_errors']) . "\n";
    }
}

function record_text(array $result): string
{
    $rules = $result['stop_rules'];
    $lines = ['Target: ' . $result['target'], 'Mode: ' . $result['mode'], 'Started-At: ' . $result['started_at'],
        'User-Agent: ' . $result['user_agent'], sprintf('Max-Requests-Per-Second: %g', $result['max_rps']),
        sprintf('Stop-Rules: p95 %d ms; errors %g%%; shed %g%%', $rules['p95_ms'], $rules['max_error_rate'] * 100, $rules['max_shed_rate'] * 100),
        'Stopped: ' . ($result['stopped'] !== '' ? $result['stopped'] : 'no')];
    foreach ($result['steps'] as $step) {
        array_push($lines, '', sprintf('Step-Concurrency: %d', $step['concurrency']), sprintf('Seconds: %.1f', $step['seconds']),
            sprintf('Requests: %d', $step['requests']), sprintf('Requests-Per-Second: %.1f', $step['requests_per_second']),
            sprintf('Latency-Ms: p50 %d; p95 %d; p99 %d; max %d', $step['p50_ms'], $step['p95_ms'], $step['p99_ms'], $step['max_ms']),
            'Statuses: ' . joined_counts($step['statuses']),
            sprintf('Error-Rate: %.4f', $step['error_rate']), sprintf('Shed-Rate: %.4f', $step['shed_rate']));
        if ($step['network_errors'] !== []) {
            $lines[] = 'Network-Errors: ' . joined_counts($step['network_errors']);
        }
        foreach ($step['routes'] as $label => $route) {
            $lines[] = sprintf('Route-%s: %d requests; p50 %d ms; p95 %d ms; cache hits %d', ucfirst($label), $route['requests'],
                $route['p50_ms'], $route['p95_ms'], $route['cache_hits']);
        }
    }
    return implode("\n", $lines) . "\n";
}

function main(array $argv): int
{
    $args = parse_args($argv);
    $confirmed = $args['confirm'] === $args['host'];
    $steps = $args['step_list'];
    $totalSeconds = count($steps) * $args['seconds'] + (count($steps) - 1) * $args['pause'];
    echo 'Target:  ' . origin($args) . "\n";
    printf("Mode:    %s; steps %s readers, %gs each, %gs rest (at most %ds)\n", $args['mode'], implode(',', $steps),
        $args['seconds'], $args['pause'], $totalSeconds);
    printf("Rate:    at most %g requests per second in total\n", $args['max-rps']);
    printf("Stops:   any 429; errors > %g%%; shed 503s > %g%%; p95 > %d ms\n", $args['max-error-rate'] * 100,
        $args['max-shed-rate'] * 100, $args['p95-ms']);
    if (!$confirmed) {
        if ($args['confirm'] !== '') {
            printf("\n--confirm %s does not match the target host %s. Nothing was sent.\n", $args['confirm'], $args['host']);
            return 2;
        }
        printf("\nPlan only; nothing was sent. Add --confirm %s to run it.\n", $args['host']);
        return 0;
    }
    if (!function_exists('curl_multi_init')) {
        fwrite(STDERR, "The PHP curl extension is required to send requests.\n");
        return 1;
    }

    $threads = $args['threads'] > 0 ? discover_paths($args) : [];
    $plan = route_plan($args, $threads);
    $paths = array_values(array_unique(array_map(fn (array $entry): string => $entry[1], $plan)));
    sort($paths, SORT_STRING);
    echo 'Routes:  ' . implode(', ', $paths) . "\n";
    $result = ['target' => $args['base_url'], 'mode' => $args['mode'], 'started_at' => gmdate('Y-m-d\TH:i:s') . '+00:00',
        'user_agent' => USER_AGENT, 'max_rps' => $args['max-rps'],
        'stop_rules' => ['p95_ms' => $args['p95-ms'], 'max_error_rate' => $args['max-error-rate'], 'max_shed_rate' => $args['max-shed-rate']],
        'steps' => [], 'stopped' => ''];
    foreach ($steps as $number => $concurrency) {
        if ($number > 0) {
            usleep((int) ($args['pause'] * 1e6));
        }
        $step = run_step($args, $plan, $concurrency);
        $result['steps'][] = $step;
        print_step($step);
        $reason = stop_reason($args, $step);
        if ($reason !== '') {
            $result['stopped'] = sprintf('step c=%d: %s', $concurrency, $reason);
            echo 'Stopped: ' . $result['stopped'] . "\n";
            break;
        }
    }
    if ($result['stopped'] === '') {
        echo "Done: every step stayed within the stop rules.\n";
    }
    if ($args['record'] !== null) {
        if (file_put_contents($args['record'], record_text($result)) === false) {
            fwrite(STDERR, 'Cannot write ' . $args['record'] . "\n");
            return 1;
        }
        echo 'Saved:   ' . $args['record'] . "\n";
    }
    return $result['stopped'] !== '' ? 1 : 0;
}

exit(main(array_slice($argv, 1)));
