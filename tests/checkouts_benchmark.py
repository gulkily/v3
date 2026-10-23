"""On-host timing/terminal acceptance; uses eight disposable clones, never live state."""
import argparse
from datetime import datetime, timezone
import hashlib
import fcntl
import json
import math
import platform
from pathlib import Path
import re
import statistics
import sys
import time

sys.dont_write_bytecode = True
import test_checkouts as checks


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', type=Path, required=True)
    args = parser.parse_args()
    args.output_dir.mkdir(parents=True, exist_ok=True)
    case = checks.CheckoutsTest()
    case.setUp()
    samples = []
    transcript = []
    evidence = {'recorded_at': datetime.now(timezone.utc).isoformat(),
                'source_sha256': {name: hashlib.sha256((checks.ROOT / name).read_bytes()).hexdigest() for name in [
                    'scripts/checkouts.php', 'src/ForumRewrite/Checkouts/CheckoutManager.php',
                    'src/ForumRewrite/Checkouts/CheckoutRenderer.php', 'src/ForumRewrite/Checkouts/CheckoutFetchPool.php',
                    'src/ForumRewrite/Checkouts/CheckoutRefreshLauncher.php']},
                'kernel': platform.release(), 'python': platform.python_version(),
                'checkouts': 8, 'development': 7, 'test': 1,
                'clock': 'perf_counter; complete CLI output/process return',
                'target_seconds': 1, 'samples': samples, 'failures': []}

    def sample(phase):
        start = time.perf_counter()
        result = case.cli(code=None, auto_refresh=True)
        elapsed = time.perf_counter() - start
        samples.append({'phase': phase, 'seconds': elapsed, 'refresh': result['refresh']['state'],
                        'actions': [r['action'] for r in result['checkouts']]})
        return result

    def wait_worker():
        deadline = time.monotonic() + 30
        while time.monotonic() < deadline:
            path = case.state / 'refresh.json'
            if path.exists():
                status = json.loads(path.read_text())
                if status['state'] not in ['queued', 'running']:
                    return status
            time.sleep(0.02)
        raise AssertionError('Worker did not finish within benchmark allowance')

    def record(command, *arguments, code=0):
        output = case.cli(*arguments, code=code, raw=True, auto_refresh=True)
        transcript.append('$ ./v3 checkouts' + (' ' + command if command else '') + '\n' + output)
        return output

    try:
        case.env['COLUMNS'] = '120'
        clones = [case.clone]
        for name in ['v3-claude', 'v3-claude-2', 'v3-codex', 'v3-codex-2', 'v3-codex-3', 'v3-codex-4', 'v3-test-kris']:
            path = case.base / name
            case.git(case.base, 'clone', '-q', str(case.up), str(path))
            clones.append(path)
        for index, path in enumerate(clones):
            case.add(path, role='test' if index == 7 else 'development')
            if index != 7:
                case.slow_fetch(path, delay=1.5)
        for _ in range(10):
            for cache in case.state.glob('overview-*.json'):
                cache.unlink()
            sample('cold')
        print('cold: 10 samples collected', flush=True)
        deadline = time.monotonic() + 10
        while not list(case.base.glob('started-*')) and time.monotonic() < deadline:
            time.sleep(0.02)
        for _ in range(10):
            sample('slow-running')
        status = wait_worker()
        assert status['state'] == 'complete', status
        for _ in range(20):
            sample('warm')
        record('')
        print('slow/warm: 30 samples collected', flush=True)
        for path in clones[:-1]:
            case.git(path, 'config', '--unset', 'remote.origin.uploadpack')
        case.up.rename(case.base / 'offline')
        case.cli('refresh', code=2)
        for _ in range(10):
            sample('offline')
        for cache_path in case.state.glob('overview-*.json'):
            cache = json.loads(cache_path.read_text())
            cache['refreshed_at'] = 0
            cache_path.write_text(json.dumps(cache))
        (case.state / 'refresh.json').unlink(missing_ok=True)
        with (case.state / 'registry.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            for _ in range(10):
                sample('manager-lock-held')
            status = wait_worker()
            assert status['state'] == 'deferred', status
        print('offline/busy: 20 samples collected', flush=True)
        (case.base / 'offline').rename(case.up)
        case.commit(case.up, 'next', 'new source for preview')
        preview = record('preview', 'preview')
        preview_id = re.search(r'Apply: ./v3 checkouts apply ([a-f0-9]{24})', preview).group(1)
        record('apply <preview-id> --sessions-paused', 'apply', preview_id, '--sessions-paused')
        result = case.cli(code=2)
        assert all(row['action'] == 'unchecked' for row in result['checkouts'][:-1])
        record('refresh', 'refresh')
        record('')
        for phase in dict.fromkeys(s['phase'] for s in samples):
            values = sorted(s['seconds'] for s in samples if s['phase'] == phase)
            evidence.setdefault('summary', {})[phase] = {'count': len(values), 'min': min(values),
                'median': statistics.median(values), 'p95': values[math.ceil(len(values) * .95) - 1],
                'max': max(values)}
        evidence['failures'] = [s for s in samples if s['seconds'] >= 1]
        evidence['pass'] = not evidence['failures']
        evidence['limitations'] = ['Host terminal, not an interactive IntelliJ UI test',
                                  'Scheduling/network-complete preview has no universal subsecond guarantee',
                                  'All Git working-tree updates used disposable clones']
    except BaseException as error:
        evidence['pass'] = False
        evidence['error'] = str(error)
        raise
    finally:
        (args.output_dir / 'timing_acceptance.json').write_text(json.dumps(evidence, indent=2) + '\n')
        (args.output_dir / 'terminal_acceptance.txt').write_text(''.join(transcript).replace(str(case.base), '$FIXTURE'))
        case.doCleanups()
    print(json.dumps(evidence['summary'], indent=2))
    if not evidence['pass']:
        raise SystemExit('Subsecond target failed; all samples retained in timing_acceptance.json')


if __name__ == '__main__':
    main()
