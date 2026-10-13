#!/usr/bin/env python3
"""Real startup recovery in a NEW disposable Ubuntu 22.04/24.04 Base chroot.

Run as root with --base-image, --sha256 and a nonexistent --work-dir.
APT changes only the chroot. Tests terminal consent, original caller/options,
HTTP reads/writes and shutdown after missing-PHP and missing-extension recovery.
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
import socket
import subprocess
import termios
import time
import urllib.parse

from local_server_dependency_install_integration_test import Target, SOURCE
from local_server_dependency_runtime_test import listening, request


class StartupTarget(Target):
    def env(self):
        return {**super().env(), 'FORUM_SITE_ID': 'zenmemes',
                'FORUM_SECRETS_PATH': '/no-secrets.php',
                'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED': 'false', 'FAST_SCORING_ENABLED': 'false'}

    def start(self, name, answer, sudo=False):
        with socket.socket() as reserved:
            reserved.bind(('127.0.0.1', 0))
            port = reserved.getsockname()[1]
        log = '/work/' + name + ' log with spaces.txt'
        args = ['/work/v3', 'start', str(port), '--verbose', '--log-file=' + log]
        if sudo:
            args = ['/usr/bin/setpriv', '--reuid=installer', '--regid=installer', '--init-groups',
                    '/usr/bin/env', 'HOME=/home/installer', *args]
        master, slave = pty.openpty()
        p = subprocess.Popen(self.command(args), env=self.env(), stdin=slave, stdout=slave, stderr=slave,
            preexec_fn=lambda: (os.setsid(), fcntl.ioctl(0, termios.TIOCSCTTY, 0)))
        os.close(slave)
        output = b''
        optional_sent = confirmations = passwords = 0
        served = False
        try:
            deadline = time.monotonic() + 300
            while time.monotonic() < deadline:
                if select.select([master], [], [], .1)[0]:
                    try:
                        chunk = os.read(master, 65536)
                    except OSError:
                        break
                    if not chunk:
                        break
                    output += chunk
                    if output.count(b'[y/N]:') > optional_sent:
                        os.write(master, b'\n')  # Optional intl defaults off.
                        optional_sent += 1
                    if output.count(b'[Y/n]:') > confirmations:
                        os.write(master, (answer + '\n').encode())
                        confirmations += 1
                    if output.count(b'password for installer:') > passwords:
                        assert sudo
                        os.write(master, b'disposable-test-password\n')
                        passwords += 1
                if listening(port):
                    assert b'Package operation: succeeded' in output, output[-3000:]
                    assert output.count(b'continuing the original startup request') == 1, output[-3000:]
                    assert confirmations >= 1
                    assert bool(passwords) == sudo
                    expected_uid = (self.root / 'work').stat().st_uid
                    launchers = []
                    for proc in Path('/proc').glob('[0-9]*'):
                        try:
                            if not os.path.samefile(proc / 'root', self.root):
                                continue
                            cmd = (proc / 'cmdline').read_bytes().split(b'\0')
                            if b'/work/scripts/dev_server.php' in cmd:
                                launchers.append(proc)
                                assert (proc / 'status').read_text().split('Uid:')[1].split()[0] == str(expected_uid)
                                assert ('--address=127.0.0.1:' + str(port)).encode() in cmd
                                assert ('--log-file=' + log).encode() in cmd
                        except (FileNotFoundError, PermissionError, ProcessLookupError):
                            continue
                    assert len(launchers) == 1, launchers
                    assert 'Hello world' in request(port, '/threads/root-001')
                    created = request(port, '/api/create_thread?' + urllib.parse.urlencode({
                        'board_tags': 'general', 'subject': 'Startup recovery ' + name,
                        'body': 'Real package recovery write'}), 'POST')
                    fields = dict(line.split('=', 1) for line in created.splitlines() if '=' in line)
                    assert fields.get('status') == 'ok' and len(fields['commit_sha']) == 40, created
                    thread = fields['thread_id']
                    assert 'Startup recovery ' + name in request(port, '/threads/' + thread)
                    reply = request(port, '/api/create_reply?' + urllib.parse.urlencode({
                        'thread_id': thread, 'parent_id': thread, 'body': 'Recovery reply'}), 'POST')
                    assert 'status=ok' in reply and 'Recovery reply' in request(port, '/threads/' + thread)
                    served = True
                    os.kill(p.pid, signal.SIGTERM)
                    break
                if p.poll() is not None:
                    break
            # Continue draining the PTY while shutdown completes.
            deadline = time.monotonic() + 10
            while time.monotonic() < deadline:
                if select.select([master], [], [], .1)[0]:
                    try:
                        chunk = os.read(master, 65536)
                    except OSError:
                        break
                    if not chunk:
                        break
                    output += chunk
                elif p.poll() is not None:
                    break
            p.wait(timeout=2)
            assert served, output[-5000:]
            assert not listening(port), 'Listening child survived shutdown'
            assert b'stopped' in output
            assert b'\x1b' not in output.split(b'Dependencies recovered;')[0]
            plain = (self.root / log.lstrip('/')).read_bytes()
            assert b'/api/create_thread' in plain and b'\x1b' not in plain
            assert not (self.root / 'etc/php' / ('8.1' if '22.04' in (self.root / 'etc/os-release').read_text() else '8.3') / 'cli/conf.d/20-intl.ini').exists()
            self.record(name, p.returncode, output, 0)
        finally:
            (self.logs / (name + '.log')).write_bytes(output)
            if p.poll() is None:
                os.kill(p.pid, signal.SIGTERM)
                try:
                    p.wait(timeout=15)
                except subprocess.TimeoutExpired:
                    os.killpg(p.pid, signal.SIGKILL)
                    p.wait()
            os.close(master)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-image', required=True, type=Path)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--work-dir', required=True, type=Path)
    args = parser.parse_args()
    assert os.geteuid() == 0
    assert hashlib.sha256(args.base_image.read_bytes()).hexdigest() == args.sha256
    args.work_dir.mkdir(parents=True, exist_ok=False)
    t = StartupTarget(args.work_dir.resolve())
    t.root.mkdir()
    subprocess.run(['tar', '-xzf', str(args.base_image.resolve()), '-C', str(t.root)], check=True)
    shutil.copyfile('/etc/resolv.conf', t.root / 'etc/resolv.conf')
    release = (t.root / 'etc/os-release').read_text()
    assert any('VERSION_ID="' + v + '"' in release for v in ('22.04', '24.04')) and '\nID=ubuntu\n' in release
    project = t.root / 'work'
    project.mkdir()
    for name in ('v3', 'autoload.php'):
        shutil.copy2(SOURCE / name, project / name)
    for name in ('scripts', 'src', 'templates', 'tests', 'public'):
        shutil.copytree(SOURCE / name, project / name, symlinks=True, ignore=shutil.ignore_patterns('__pycache__'))
    t.shell('prepare-metadata', 'apt-get update -o APT::Update::Error-Mode=any')
    t.start('root-missing-php-enter', '')
    t.shell('remove-extensions', 'apt-get remove -y php8.3-sqlite3 php8.3-mbstring')
    t.start('root-missing-extensions-Y', 'Y')
    t.shell('prepare-password-sudo', '''DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends sudo
useradd -m -s /bin/bash installer
printf 'installer ALL=(root) ALL\\n' > /etc/sudoers.d/installer
printf 'installer:disposable-test-password\\n' | chpasswd
chmod 440 /etc/sudoers.d/installer
chown -R installer:installer /work
apt-get remove -y php8.3-cli php8.3-sqlite3 php8.3-mbstring''')
    t.start('sudo-missing-php-y', 'y', sudo=True)
    t.shell('remove-extensions-again', 'apt-get remove -y php8.3-sqlite3 php8.3-mbstring; rm -f /run/sudo/ts/installer')
    t.start('sudo-missing-extensions-Y', 'Y', sudo=True)
    identity = t.shell('environment', 'cat /etc/os-release; uname -m; php --version; dpkg-query -W git php8.3-cli php8.3-sqlite3 php8.3-mbstring')
    (t.work / 'evidence.json').write_text(json.dumps({'base_sha256': args.sha256, 'environment': identity, 'results': t.results}, indent=2) + '\n')
    print('PASS: startup recovery, caller identity, original options, HTTP read/write and shutdown:', t.work)


if __name__ == '__main__':
    main()
