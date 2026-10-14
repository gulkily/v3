#!/usr/bin/env python3
"""Failure gates in a disposable rootfs created by the startup integration test.

Run as root with --work-dir pointing to that retained test directory. Never use
an ordinary system root: the harness requires its startup evidence and /work/v3.
"""
import argparse
import fcntl
import os
from pathlib import Path
import shutil
import signal
import socket

from local_server_dependency_install_integration_test import SOURCE
from startup_dependency_recovery_integration_test import StartupTarget
from local_server_dependency_runtime_test import listening

NO_CHILD = '! (for p in /proc/[0-9]*; do [[ "$p/root" -ef / ]] || continue; tr "\\0" " " < "$p/cmdline" 2>/dev/null || true; echo; done) | grep -E "^[s]leep 60 ?$|^[/]usr/bin/apt-get"'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--work-dir', required=True, type=Path)
    args = parser.parse_args()
    assert os.geteuid() == 0
    work = args.work_dir.resolve()
    assert (work / 'evidence.json').is_file() and (work / 'rootfs/work/v3').is_file()
    t = StartupTarget.__new__(StartupTarget)
    t.work = work
    t.root = work / 'rootfs'
    t.logs = work / 'failure-logs'
    t.logs.mkdir(exist_ok=False)
    t.results = []
    shutil.copy2(SOURCE / 'v3', t.root / 'work/v3')
    shutil.copytree(SOURCE / 'scripts', t.root / 'work/scripts', dirs_exist_ok=True)
    with socket.socket() as reserved:
        reserved.bind(('127.0.0.1', 0))
        port = reserved.getsockname()[1]
    cli = ['/work/v3', 'start', str(port), '--quiet', '--log-file=/work/should-not-exist.log']
    t.shell('remove-required', 'apt-get remove -y php8.3-sqlite3 php8.3-mbstring')
    before = t.snapshot()
    for answer in ('n', 'N', 'invalid', None, 'SIGNAL_INT', 'SIGNAL_TERM'):
        text = t.terminal('required-refusal-' + str(answer), cli, ['', answer], 1)
        assert 'continuing the original startup' not in text
        assert not listening(port)
    for extra in (['--unknown'], ['0'], ['65536'], ['host:99999'], ['999999999999999999999'], ['--log-file=']):
        t.terminal('invalid-' + extra[0].replace('/', '_'), ['/work/v3', 'start', *extra], [], 1)
    text = t.run('redirected-input-output', cli, 1)
    assert '[Y/n]' not in text and 'Dependency installation review' not in text
    # Output terminal with redirected input, then input terminal with output redirected.
    t.terminal('redirected-input', ['/bin/bash', '-c', 'exec "$@" </dev/null', 'test', *cli], [], 1)
    t.terminal('redirected-output', ['/bin/bash', '-c', 'exec "$@" >/dev/null', 'test', *cli], [], 1)
    assert t.snapshot() == before, 'Pre-execution refusal mutated application/package metadata'
    assert not listening(port)

    # Configuration and custom-runtime refusals cannot offer a package transaction.
    t.run('disabled-installed-common', ['/usr/bin/env', 'PHP_INI_SCAN_DIR=/no-config', *cli], 1)
    t.shell('prepare-custom', 'mkdir -p /tmp/custom; cp /usr/bin/php8.3 /tmp/custom/php')
    t.terminal('custom-runtime', ['/usr/bin/env', 'PATH=/tmp/custom:/usr/bin:/bin', *cli], [], 1)
    assert t.snapshot() == before

    # Real authentication cancellation/denial, after the approved review.
    user = ['/usr/bin/setpriv', '--reuid=installer', '--regid=installer', '--init-groups', '/usr/bin/env', 'HOME=/home/installer']
    t.shell('clear-credentials', 'rm -f /run/sudo/ts/installer')
    t.terminal('cancel-password-sudo', [*user, *cli], ['', 'y'], 1, cancel_auth=True)
    t.shell('prepare-denied-user', "id denied >/dev/null 2>&1 || useradd -m denied; printf 'Defaults:denied !authenticate\\ndenied ALL=(ALL) !ALL\\n' > /etc/sudoers.d/denied; chmod 440 /etc/sudoers.d/denied")
    denied = ['/usr/bin/setpriv', '--reuid=denied', '--regid=denied', '--init-groups', '/usr/bin/env', 'HOME=/home/denied']
    t.terminal('denied-sudo', [*denied, *cli], ['', 'Y'], 1)
    t.shell('hide-sudo', 'mv /usr/bin/sudo /usr/bin/sudo-held-for-test')
    try:
        t.terminal('missing-sudo', [*user, *cli], [], 1)
    finally:
        t.shell('restore-sudo', 'mv /usr/bin/sudo-held-for-test /usr/bin/sudo')
    assert t.snapshot() == before and not listening(port)

    t.hook('99v3-network', 'Acquire::http::Proxy "http://127.0.0.1:9"; Acquire::Retries "0"; Acquire::http::Timeout "1";')
    try:
        text = t.terminal('network-failure', cli, ['', 'y'], 1)
        assert 'metadata refresh failed' in text
    finally:
        t.unhook('99v3-network')
    assert not listening(port)
    with (t.root / 'var/lib/dpkg/lock-frontend').open('w') as lock:
        fcntl.lockf(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        text = t.terminal('lock-failure', cli, ['', 'Y'], 1)
        assert 'Package operation: failed' in text
    assert not listening(port)
    for sig in (signal.SIGINT, signal.SIGTERM):
        (t.root / 'tmp/installer-hook-active').unlink(missing_ok=True)
        t.hook('99v3-slow', 'DPkg::Pre-Invoke { "touch /tmp/installer-hook-active; sleep 60"; };')
        try:
            text = t.terminal('package-' + sig.name, cli, ['', 'y'], 1, interrupt=sig)
            assert 'Package operation: interrupted' in text
            t.shell('no-child-' + sig.name, NO_CHILD)
        finally:
            t.unhook('99v3-slow')
        assert not listening(port)
    t.hook('99v3-fail', 'DPkg::Post-Invoke { "false"; };')
    try:
        text = t.terminal('partial-install', cli, ['', 'Y'], 1)
        assert 'Package operation: failed' in text and 'Result: READY' in text
    finally:
        t.unhook('99v3-fail')
    assert not listening(port)
    t.shell('no-child-after-failure', NO_CHILD)
    # A remaining non-package restriction after successful package installation blocks startup.
    t.shell('prepare-restriction', "apt-get remove -y php8.3-mbstring; printf 'disable_functions=proc_open\\n' >/tmp/restricted.ini")
    text = t.terminal('remaining-required-restriction', ['/usr/bin/env', 'PHPRC=/tmp/restricted.ini', *cli], ['', 'y'], 1)
    assert 'Package operation: succeeded' in text and 'Result: NOT READY' in text
    assert not listening(port) and not (t.root / 'work/should-not-exist.log').exists()
    # Optional-only normal startup must never ask permission or invoke installation.
    t.start('optional-only-no-prompt', '', sudo=True, recovery=False)
    import json
    (work / 'failure-evidence.json').write_text(json.dumps(t.results, indent=2) + '\n')
    print('PASS: startup refusal, authentication, package failure and interruption gates:', work)


if __name__ == '__main__':
    main()
