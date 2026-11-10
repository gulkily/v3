#!/usr/bin/env python3
"""Small, stepped read-load probe for a v3 site. Standard library only.

Nothing is sent unless --confirm repeats the target's host name exactly; without it the
script prints the plan and exits. Each step runs a fixed number of concurrent readers for
a short time, then reports latency, status codes and cache hits. The run stops at the
first step that breaks a stop rule (any 429, too many errors, or p95 over budget).

Read-only: GET requests only. Test writes separately and by hand (for example one frm.py
reply while a step runs).

  python3 scripts/stress_probe.py https://zenmemes.com                # show the plan
  python3 scripts/stress_probe.py https://zenmemes.com --confirm zenmemes.com
  python3 scripts/stress_probe.py https://zenmemes.com --mode uncached --steps 1,2,4 \
      --confirm zenmemes.com --record stress-2026-11-09.txt

Modes:
  cached    routes high-traffic mode serves from its cache (/, /threads/, /about/, threads)
  uncached  the same routes with a unique query string, plus /recent/ and /search, so
            every request reaches PHP
"""

from __future__ import annotations

import argparse
import http.client
import random
import re
import ssl
import sys
import threading
import time
import urllib.parse
from collections import Counter
from datetime import datetime, timezone

USER_AGENT = 'v3-stress-probe/1 (operator load test)'
MAX_STEP_SECONDS = 60
MAX_CONCURRENCY = 32
MAX_RPS = 200


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('base_url', help='site origin, e.g. https://zenmemes.com')
    parser.add_argument('--confirm', default='', metavar='HOST',
                        help='repeat the target host name to actually send traffic')
    parser.add_argument('--mode', choices=['cached', 'uncached', 'both'], default='cached')
    parser.add_argument('--steps', default='1,2,4,8',
                        help='comma-separated concurrent readers per step (default 1,2,4,8; max %d)' % MAX_CONCURRENCY)
    parser.add_argument('--seconds', type=float, default=20, help='duration of each step (default 20, max %d)' % MAX_STEP_SECONDS)
    parser.add_argument('--max-rps', type=float, default=50,
                        help='cap on total requests per second across all readers (default 50, max %d)' % MAX_RPS)
    parser.add_argument('--pause', type=float, default=5, help='rest between steps in seconds (default 5)')
    parser.add_argument('--threads', type=int, default=5, help='thread pages to discover from the home page (default 5)')
    parser.add_argument('--path', action='append', default=[], help='extra path to include (repeatable)')
    parser.add_argument('--p95-ms', type=float, default=2000, help='stop when p95 exceeds this (default 2000)')
    parser.add_argument('--max-error-rate', type=float, default=0.02,
                        help='stop when 5xx/network errors exceed this share, excluding load-shedding 503s (default 0.02)')
    parser.add_argument('--max-shed-rate', type=float, default=0.25,
                        help='stop when 503 Retry-After responses exceed this share (default 0.25)')
    parser.add_argument('--timeout', type=float, default=30, help='per-request timeout in seconds (default 30)')
    parser.add_argument('--record', metavar='FILE', help='also write the result as a Key: value text record')
    args = parser.parse_args(argv)

    url = urllib.parse.urlsplit(args.base_url)
    if url.scheme not in ('http', 'https') or not url.hostname or url.path not in ('', '/') or url.query:
        parser.error('base_url must be a bare origin such as https://zenmemes.com')
    args.scheme, args.host, args.port = url.scheme, url.hostname, url.port
    try:
        args.step_list = [int(part) for part in args.steps.split(',') if part.strip()]
    except ValueError:
        parser.error('--steps must be comma-separated integers')
    if not args.step_list or any(step < 1 or step > MAX_CONCURRENCY for step in args.step_list):
        parser.error('each step must be between 1 and %d' % MAX_CONCURRENCY)
    if not 0 < args.seconds <= MAX_STEP_SECONDS:
        parser.error('--seconds must be between 0 and %d' % MAX_STEP_SECONDS)
    if not 0 < args.max_rps <= MAX_RPS:
        parser.error('--max-rps must be between 0 and %d' % MAX_RPS)
    if args.pause < 0 or args.threads < 0 or args.timeout <= 0:
        parser.error('--pause, --threads and --timeout must not be negative')
    for path in args.path:
        if not path.startswith('/'):
            parser.error('--path values must start with /')
    return args


class Client:
    """One keep-alive connection per reader, reopened after errors."""

    def __init__(self, args: argparse.Namespace):
        self.args = args
        self.connection: http.client.HTTPConnection | None = None

    def get(self, path: str) -> tuple[int, dict[str, str], bytes]:
        for attempt in (0, 1):
            if self.connection is None:
                if self.args.scheme == 'https':
                    self.connection = http.client.HTTPSConnection(self.args.host, self.args.port, timeout=self.args.timeout,
                                                                  context=ssl.create_default_context())
                else:
                    self.connection = http.client.HTTPConnection(self.args.host, self.args.port, timeout=self.args.timeout)
            try:
                self.connection.request('GET', path, headers={'User-Agent': USER_AGENT, 'Accept': 'text/html'})
                response = self.connection.getresponse()
                body = response.read()
                headers = {name.lower(): value for name, value in response.getheaders()}
                if headers.get('connection', '').lower() == 'close':
                    self.close()
                return response.status, headers, body
            except (http.client.RemoteDisconnected, http.client.CannotSendRequest, BrokenPipeError, ConnectionResetError):
                # A reused keep-alive connection the server already closed: retry once on a new one.
                self.close()
                if attempt == 1:
                    raise
        raise RuntimeError('unreachable')

    def close(self) -> None:
        if self.connection is not None:
            self.connection.close()
            self.connection = None


def discover_paths(args: argparse.Namespace) -> list[str]:
    client = Client(args)
    try:
        status, _, body = client.get('/')
    finally:
        client.close()
    if status != 200:
        raise SystemExit('The home page returned HTTP %d; not starting.' % status)
    found: list[str] = []
    for match in re.finditer(r'href="(/threads/[A-Za-z0-9][A-Za-z0-9_-]{0,127})"', body.decode('utf-8', 'replace')):
        if match.group(1) not in found:
            found.append(match.group(1))
        if len(found) >= args.threads:
            break
    return found


def route_plan(args: argparse.Namespace, threads: list[str]) -> list[tuple[str, str]]:
    """(label, path) pairs; uncached paths get a fresh query string per request."""
    base = ['/', '/threads/', '/about/'] + threads + args.path
    plan: list[tuple[str, str]] = []
    if args.mode in ('cached', 'both'):
        plan += [('cached', path) for path in base]
    if args.mode in ('uncached', 'both'):
        plan += [('uncached', path) for path in base + ['/recent/', '/search?q=the']]
    return plan


def concrete_path(label: str, path: str) -> str:
    if label != 'uncached':
        return path
    separator = '&' if '?' in path else '?'
    return '%s%sstress=%d' % (path, separator, random.randrange(10 ** 12))


def percentile(sorted_values: list[float], fraction: float) -> float:
    if not sorted_values:
        return 0.0
    index = min(len(sorted_values) - 1, max(0, round(fraction * (len(sorted_values) - 1))))
    return sorted_values[index]


def run_step(args: argparse.Namespace, plan: list[tuple[str, str]], concurrency: int) -> dict:
    deadline = time.monotonic() + args.seconds
    lock = threading.Lock()
    samples: list[tuple[str, int, float, str, str]] = []  # label, status, ms, cache header, retry-after
    errors: Counter = Counter()
    interval = 1.0 / args.max_rps
    next_slot = [time.monotonic()]

    def wait_for_slot() -> bool:
        # Shared pacing: readers take evenly spaced start times so the total rate stays capped.
        with lock:
            slot = max(next_slot[0], time.monotonic())
            next_slot[0] = slot + interval
        delay = slot - time.monotonic()
        if delay > 0:
            time.sleep(delay)
        return time.monotonic() < deadline

    def reader(offset: int) -> None:
        client = Client(args)
        index = offset
        try:
            while wait_for_slot():
                label, path = plan[index % len(plan)]
                index += 1
                started = time.monotonic()
                try:
                    status, headers, _ = client.get(concrete_path(label, path))
                except Exception as error:  # network or protocol failure counts against the step
                    client.close()
                    with lock:
                        errors[type(error).__name__] += 1
                        samples.append((label, 0, (time.monotonic() - started) * 1000, '', ''))
                    continue
                elapsed = (time.monotonic() - started) * 1000
                with lock:
                    samples.append((label, status, elapsed, headers.get('x-v3-high-traffic', ''), headers.get('retry-after', '')))
                if status == 429:
                    return
        finally:
            client.close()

    started = time.monotonic()
    workers = [threading.Thread(target=reader, args=(i,), daemon=True) for i in range(concurrency)]
    for worker in workers:
        worker.start()
    for worker in workers:
        worker.join(args.seconds + args.timeout + 5)
    wall = time.monotonic() - started

    statuses = Counter(sample[1] for sample in samples)
    latencies = sorted(sample[2] for sample in samples)
    total = len(samples)
    shed = sum(1 for sample in samples if sample[1] == 503 and sample[4] != '')
    failures = sum(1 for sample in samples if sample[1] == 0 or (sample[1] >= 500 and not (sample[1] == 503 and sample[4] != '')))
    by_label = {}
    for label in sorted({sample[0] for sample in samples}):
        values = sorted(sample[2] for sample in samples if sample[0] == label)
        hits = sum(1 for sample in samples if sample[0] == label and sample[3] == 'hit')
        by_label[label] = {'requests': len(values), 'p50_ms': round(percentile(values, .5)), 'p95_ms': round(percentile(values, .95)),
                           'cache_hits': hits}
    return {
        'concurrency': concurrency, 'seconds': round(wall, 1), 'requests': total,
        'requests_per_second': round(total / wall, 1) if wall > 0 else 0,
        'p50_ms': round(percentile(latencies, .5)), 'p95_ms': round(percentile(latencies, .95)),
        'p99_ms': round(percentile(latencies, .99)), 'max_ms': round(latencies[-1]) if latencies else 0,
        'statuses': {('network' if code == 0 else str(code)): count for code, count in sorted(statuses.items())},
        'network_errors': dict(errors), 'shed_503': shed, 'failures': failures,
        'error_rate': round(failures / total, 4) if total else 1.0,
        'shed_rate': round(shed / total, 4) if total else 0.0,
        'routes': by_label,
    }


def stop_reason(args: argparse.Namespace, step: dict) -> str:
    if step['requests'] == 0:
        return 'no requests completed'
    if step['statuses'].get('429'):
        return 'the server answered 429 Too Many Requests'
    if step['error_rate'] > args.max_error_rate:
        return 'error rate %.1f%% is over %.1f%%' % (step['error_rate'] * 100, args.max_error_rate * 100)
    if step['shed_rate'] > args.max_shed_rate:
        return 'load-shedding 503s at %.1f%% are over %.1f%%' % (step['shed_rate'] * 100, args.max_shed_rate * 100)
    if step['p95_ms'] > args.p95_ms:
        return 'p95 %d ms is over %d ms' % (step['p95_ms'], args.p95_ms)
    return ''


def print_step(step: dict) -> None:
    statuses = ' '.join('%s:%d' % item for item in step['statuses'].items())
    print('  c=%-3d %6d req %7.1f/s  p50 %5d  p95 %5d  p99 %5d  max %5d ms  [%s]' % (
        step['concurrency'], step['requests'], step['requests_per_second'], step['p50_ms'], step['p95_ms'],
        step['p99_ms'], step['max_ms'], statuses))
    for label, route in step['routes'].items():
        hit = ''
        if label == 'cached' and route['requests']:
            hit = '  cache hits %d%%' % round(100 * route['cache_hits'] / route['requests'])
        print('        %-8s %6d req  p50 %5d  p95 %5d ms%s' % (label, route['requests'], route['p50_ms'], route['p95_ms'], hit))
    if step['network_errors']:
        print('        network errors: %s' % ', '.join('%s %d' % item for item in step['network_errors'].items()))


def record_text(result: dict) -> str:
    rules = result['stop_rules']
    lines = ['Target: %s' % result['target'], 'Mode: %s' % result['mode'], 'Started-At: %s' % result['started_at'],
             'User-Agent: %s' % result['user_agent'],
             'Max-Requests-Per-Second: %g' % result['max_rps'],
             'Stop-Rules: p95 %d ms; errors %g%%; shed %g%%' % (rules['p95_ms'], rules['max_error_rate'] * 100, rules['max_shed_rate'] * 100),
             'Stopped: %s' % (result['stopped'] or 'no')]
    for step in result['steps']:
        lines += ['', 'Step-Concurrency: %d' % step['concurrency'], 'Seconds: %s' % step['seconds'],
                  'Requests: %d' % step['requests'], 'Requests-Per-Second: %s' % step['requests_per_second'],
                  'Latency-Ms: p50 %d; p95 %d; p99 %d; max %d' % (step['p50_ms'], step['p95_ms'], step['p99_ms'], step['max_ms']),
                  'Statuses: %s' % ', '.join('%s %d' % item for item in step['statuses'].items()),
                  'Error-Rate: %s' % step['error_rate'], 'Shed-Rate: %s' % step['shed_rate']]
        if step['network_errors']:
            lines.append('Network-Errors: %s' % ', '.join('%s %d' % item for item in step['network_errors'].items()))
        for label, route in step['routes'].items():
            lines.append('Route-%s: %d requests; p50 %d ms; p95 %d ms; cache hits %d' % (
                label.capitalize(), route['requests'], route['p50_ms'], route['p95_ms'], route['cache_hits']))
    return '\n'.join(lines) + '\n'


def main(argv: list[str]) -> int:
    args = parse_args(argv)
    confirmed = args.confirm == args.host
    total_seconds = len(args.step_list) * args.seconds + (len(args.step_list) - 1) * args.pause
    print('Target:  %s://%s%s' % (args.scheme, args.host, '' if args.port is None else ':%d' % args.port))
    print('Mode:    %s; steps %s readers, %gs each, %gs rest (at most %ds)' % (
        args.mode, ','.join(map(str, args.step_list)), args.seconds, args.pause, total_seconds))
    print('Rate:    at most %g requests per second in total' % args.max_rps)
    print('Stops:   any 429; errors > %g%%; shed 503s > %g%%; p95 > %d ms' % (
        args.max_error_rate * 100, args.max_shed_rate * 100, args.p95_ms))
    if not confirmed:
        if args.confirm:
            print('\n--confirm %s does not match the target host %s. Nothing was sent.' % (args.confirm, args.host))
            return 2
        print('\nPlan only; nothing was sent. Add --confirm %s to run it.' % args.host)
        return 0

    threads = discover_paths(args) if args.threads else []
    plan = route_plan(args, threads)
    print('Routes:  %s' % ', '.join(sorted({path for _, path in plan})))
    result = {'target': args.base_url, 'mode': args.mode, 'started_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
              'user_agent': USER_AGENT, 'max_rps': args.max_rps, 'stop_rules': {'p95_ms': args.p95_ms, 'max_error_rate': args.max_error_rate,
                                                       'max_shed_rate': args.max_shed_rate},
              'steps': [], 'stopped': ''}
    try:
        for number, concurrency in enumerate(args.step_list):
            if number:
                time.sleep(args.pause)
            step = run_step(args, plan, concurrency)
            result['steps'].append(step)
            print_step(step)
            reason = stop_reason(args, step)
            if reason:
                result['stopped'] = 'step c=%d: %s' % (concurrency, reason)
                print('Stopped: %s' % result['stopped'])
                break
        else:
            print('Done: every step stayed within the stop rules.')
    except KeyboardInterrupt:
        result['stopped'] = 'interrupted'
        print('\nInterrupted.')
    if args.record:
        with open(args.record, 'w', encoding='utf-8') as handle:
            handle.write(record_text(result))
        print('Saved:   %s' % args.record)
    return 1 if result['stopped'] else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
