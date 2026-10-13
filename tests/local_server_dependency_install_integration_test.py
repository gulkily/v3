#!/usr/bin/env python3
"""Real APT recovery tests in a NEW, disposable Ubuntu Base 22.04 or 24.04 amd64 chroot.

Run as root with --base-image, --sha256 (published Ubuntu checksum), --work-dir.
The work directory must not exist. Host packages are never installed. Requires
Linux mount namespaces, chroot, tar, Python 3 and network access to Ubuntu APT.
Retains the isolated rootfs and transcripts for inspection; mounts are private.
"""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import pty
import select
import shutil
import signal
import subprocess
import time
import termios

SOURCE = Path(__file__).resolve().parents[1]
MOUNT_RUN = '''set -eu
root="$1"; shift
mount --make-rprivate /
mount -t proc proc "$root/proc"
mount --rbind /dev "$root/dev"
exec chroot "$root" "$@"
'''


class Target:
    def __init__(self, work):
        self.work = work
        self.root = work / 'rootfs'
        self.logs = work / 'logs'
        self.logs.mkdir()
        self.results = []

    def command(self, args):
        series = '8.1' if 'VERSION_ID="22.04"' in (self.root / 'etc/os-release').read_text() else '8.3'
        args = [a.replace('php8.3', 'php' + series) for a in args]
        return ['unshare', '--mount', '/bin/bash', '-c', MOUNT_RUN, 'v3-test', str(self.root), *args]

    def env(self):
        return {'PATH': '/usr/sbin:/usr/bin:/sbin:/bin', 'HOME': '/root',
                'LC_ALL': 'C', 'TERM': 'xterm', 'NO_COLOR': '1'}

    def record(self, name, status, output, expected):
        (self.logs / (name + '.log')).write_bytes(output)
        assert status == expected, (name, status, output[-5000:].decode(errors='replace'))
        self.results.append({'case': name, 'exit_status': status})
        print('PASS:', name, flush=True)
        return output.decode(errors='replace')

    def run(self, name, args, expected=0):
        p = subprocess.run(self.command(args), env=self.env(), input=b'',
                           stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=300)
        return self.record(name, p.returncode, p.stdout, expected)

    def shell(self, name, command):
        return self.run(name, ['/bin/bash', '-euc', command])

    def terminal(self, name, args, answers, expected=0, interrupt=None, password=None):
        master, slave = pty.openpty()
        p = subprocess.Popen(self.command(args), env=self.env(), stdin=slave,
                             stdout=slave, stderr=slave, preexec_fn=lambda: (os.setsid(), fcntl.ioctl(0, termios.TIOCSCTTY, 0)))
        os.close(slave)
        output = b''
        sent = 0
        signalled = False
        password_sent = False
        deadline = time.monotonic() + 300
        try:
            while time.monotonic() < deadline:
                if select.select([master], [], [], .1)[0]:
                    try:
                        chunk = os.read(master, 65536)
                    except OSError:
                        break
                    if not chunk:
                        break
                    output += chunk
                    if password and not password_sent and b'password for installer:' in output:
                        os.write(master, (password + '\n').encode())
                        password_sent = True
                    prompts = output.count(b'[type yes to continue]:')
                    if prompts > sent:
                        assert sent < len(answers), (name, 'unexpected extra confirmation', output[-2500:])
                        answer = answers[sent]
                        os.write(master, b'\x04' if answer is None else (answer + '\n').encode())
                        sent += 1
                if interrupt and not signalled and (self.root / 'tmp/installer-hook-active').exists():
                    os.kill(p.pid, interrupt)
                    signalled = True
                if p.poll() is not None:
                    # Drain terminal output on the following iteration.
                    if not select.select([master], [], [], .1)[0]:
                        break
            else:
                raise AssertionError((name, 'PTY timeout'))
            p.wait(timeout=10)
            if interrupt:
                assert signalled, 'APT hook was not reached'
            text = self.record(name, p.returncode, output, expected)
            assert '\x1b' not in text, 'NO_COLOR terminal output contained escapes'
            return text
        finally:
            if p.poll() is None:
                os.killpg(p.pid, signal.SIGTERM)
                try:
                    p.wait(timeout=15)
                except subprocess.TimeoutExpired:
                    p.kill()
                    p.wait()
            os.close(master)

    def snapshot(self, packages=True):
        paths = list((self.root / 'work').rglob('*'))
        if packages:
            paths += [self.root / 'var/lib/dpkg/status']
            paths += list((self.root / 'var/lib/apt/lists').glob('*'))
        return {str(p.relative_to(self.root)): hashlib.sha256(p.read_bytes()).hexdigest()
                for p in paths if p.is_file() and not p.is_symlink()}

    def hook(self, name, text):
        (self.root / 'etc/apt/apt.conf.d' / name).write_text(text + '\n')

    def unhook(self, name):
        (self.root / 'etc/apt/apt.conf.d' / name).unlink()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-image', type=Path, required=True)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--work-dir', type=Path, required=True)
    args = parser.parse_args()
    assert os.geteuid() == 0, 'Run this isolated-environment harness as root.'
    assert hashlib.sha256(args.base_image.read_bytes()).hexdigest() == args.sha256, 'Image checksum mismatch'
    args.work_dir.mkdir(parents=True, exist_ok=False)
    target = Target(args.work_dir.resolve())
    target.root.mkdir()
    subprocess.run(['tar', '-xzf', str(args.base_image.resolve()), '-C', str(target.root)], check=True)
    shutil.copyfile('/etc/resolv.conf', target.root / 'etc/resolv.conf')
    release = (target.root / 'etc/os-release').read_text()
    assert any('VERSION_ID="' + v + '"' in release for v in ('22.04', '24.04')) and '\nID=ubuntu\n' in release
    project = target.root / 'work'
    project.mkdir()
    for name in ('v3', 'autoload.php'):
        shutil.copy2(SOURCE / name, project / name)
    for name in ('scripts', 'src', 'templates', 'tests', 'public'):
        shutil.copytree(SOURCE / name, project / name, symlinks=True)
    target.shell('prepare-metadata', 'apt-get update -o APT::Update::Error-Mode=any')
    baseline = target.snapshot()
    for alias in ('check-dependencies', 'dependency-check'):
        cli = ['/work/v3', alias]
        target.run(alias + '-help-without-php', cli + ['--help'])
        target.run(alias + '-ordinary-missing', cli, 1)
        target.run(alias + '-noninteractive', cli + ['--install'], 1)
        text = target.terminal(alias + '-decline', cli + ['--install'], ['no', 'no'], 1)
        assert 'Cancelled before execution' in text
        target.terminal(alias + '-eof', cli + ['--install'], ['no', None], 1)
        assert target.snapshot() == baseline, 'Decline/EOF/diagnostics mutated application or package metadata'
    text = target.terminal('unavailable-privileges', ['/bin/su', '-s', '/bin/bash', 'nobody', '-c',
                           '/work/v3 check-dependencies --install'], ['no', 'yes'], 1)
    assert 'sudo is unavailable' in text
    assert target.snapshot() == baseline
    app_before = target.snapshot(packages=False)
    text = target.terminal('missing-php-git-install', ['/work/v3', 'check-dependencies', '--install'], ['no', 'yes'])
    assert 'Package operation: succeeded' in text and 'Result: READY' in text
    assert target.snapshot(packages=False) == app_before
    before_optional = target.snapshot()
    target.terminal('optional-only-decline', ['/work/v3', 'dependency-check', '--install'], ['no'])
    assert target.snapshot() == before_optional
    text = target.terminal('optional-only-install', ['/work/v3', 'dependency-check', '--install'], ['yes', 'yes'])
    assert 'Package operation: succeeded' in text
    ready = target.snapshot()
    for alias in ('check-dependencies', 'dependency-check'):
        target.run(alias + '-already-ready', ['/work/v3', alias, '--install'])
    assert target.snapshot() == ready

    # Non-package restrictions and custom runtime selection remain manual.
    target.shell('prepare-restriction', "printf 'disable_functions=proc_open\\n' > /tmp/restricted.ini")
    text = target.run('remaining-host-restriction', ['/usr/bin/env', 'PHPRC=/tmp/restricted.ini',
                       '/work/v3', 'check-dependencies', '--install'], 1)
    assert 'No installable package changes' in text
    target.shell('prepare-custom-runtime', 'mkdir /tmp/custom; cp /usr/bin/php8.3 /tmp/custom/php')
    text = target.run('custom-runtime', ['/usr/bin/env', 'PATH=/tmp/custom:/usr/bin:/bin',
                       '/work/v3', 'check-dependencies', '--install'], 1)
    assert 'not a verified' in text
    target.shell('remove-optional-for-failure-tests', 'apt-get remove -y php8.3-intl')
    cli = ['/work/v3', 'dependency-check', '--install']
    target.hook('99v3-network', 'Acquire::http::Proxy "http://127.0.0.1:9"; Acquire::Retries "0"; Acquire::http::Timeout "1";')
    try:
        text = target.terminal('network-failure', cli, ['yes', 'yes'], 1)
        assert 'metadata refresh failed' in text
    finally:
        target.unhook('99v3-network')
    with (target.root / 'var/lib/dpkg/lock-frontend').open('w') as lock:
        fcntl.lockf(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        text = target.terminal('dpkg-lock-failure', cli, ['yes', 'yes'], 1)
        assert 'Package operation: failed' in text
    for sig in (signal.SIGINT, signal.SIGTERM):
        marker = target.root / 'tmp/installer-hook-active'
        marker.unlink(missing_ok=True)
        target.hook('99v3-slow', 'DPkg::Pre-Invoke { "touch /tmp/installer-hook-active; sleep 60"; };')
        try:
            text = target.terminal('interrupt-' + sig.name, cli, ['yes', 'yes'], 1, interrupt=sig)
            assert 'Package operation: interrupted' in text and 'Fresh dependency assessment' in text
            target.shell('no-package-child-' + sig.name, '! (for p in /proc/[0-9]*; do [[ "$p/root" -ef / ]] || continue; tr "\\0" " " < "$p/cmdline" 2>/dev/null || true; echo; done) | grep -E "^[s]leep 60 ?$|^[/]usr/bin/apt-get"')
        finally:
            target.unhook('99v3-slow')
    target.hook('99v3-fail', 'DPkg::Post-Invoke { "false"; };')
    try:
        text = target.terminal('partial-install-failure', cli, ['yes', 'yes'], 1)
        assert 'Package operation: failed' in text and 'Result: READY' in text
    finally:
        target.unhook('99v3-fail')

    # Test-only provisioning: sudo and Python are not application requirements.
    target.shell('prepare-sudo-and-runtime-tests', '''DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends sudo python3
useradd -m -s /bin/bash installer
printf 'installer ALL=(root) ALL\\n' > /etc/sudoers.d/installer
printf 'installer:disposable-test-password\\n' | chpasswd
chmod 440 /etc/sudoers.d/installer
apt-get remove -y php8.3-mbstring''')
    text = target.terminal('sudo-required-install', ['/usr/bin/setpriv', '--reuid=installer',
                           '--regid=installer', '--init-groups', '/usr/bin/env', 'HOME=/home/installer',
                           '/work/v3', 'dependency-check', '--install'],
                           ['yes'], password='disposable-test-password')
    assert 'Package operation: succeeded' in text
    target.shell('prepare-sudo-interruption', 'apt-get remove -y php8.3-intl')
    (target.root / 'tmp/installer-hook-active').unlink(missing_ok=True)
    target.hook('99v3-slow', 'DPkg::Pre-Invoke { "touch /tmp/installer-hook-active; sleep 60"; };')
    try:
        text = target.terminal('sudo-interrupt-SIGTERM', ['/usr/bin/setpriv', '--reuid=installer',
            '--regid=installer', '--init-groups', '/usr/bin/env', 'HOME=/home/installer',
            '/work/v3', 'dependency-check', '--install'], ['yes', 'yes'], 1,
            interrupt=signal.SIGTERM, password='disposable-test-password')
        assert 'Package operation: interrupted' in text and 'Fresh dependency assessment' in text
        target.shell('no-sudo-package-child', '! (for p in /proc/[0-9]*; do [[ "$p/root" -ef / ]] || continue; tr "\\0" " " < "$p/cmdline" 2>/dev/null || true; echo; done) | grep -E "^[s]leep 60 ?$|^[/]usr/bin/apt-get"')
    finally:
        target.unhook('99v3-slow')
    target.terminal('restore-optional-after-interruption', ['/work/v3', 'check-dependencies', '--install'], ['yes', 'yes'])
    target.run('installed-runtime-http-and-shutdown', ['python3', '/work/tests/local_server_dependency_runtime_test.py'])
    target.run('shell-regressions', ['bash', '/work/tests/local_server_dependency_checks_test.sh'])
    target.run('installer-fixtures', ['bash', '/work/tests/local_server_dependency_install_test.sh'])
    identity = target.shell('environment', 'cat /etc/os-release; uname -m; bash --version; php --version; dpkg-query -W git php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-intl; apt-cache policy php8.3-cli')
    (target.work / 'evidence.json').write_text(json.dumps({'base_sha256': args.sha256,
        'environment': identity, 'results': target.results}, indent=2) + '\n')
    print('PASS: full install/recheck/manual-start/read/write/shutdown recovery; evidence:', target.work)


if __name__ == '__main__':
    main()
