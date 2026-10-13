#!/usr/bin/env python3
"""PTY fixtures for a changed transaction after refresh; never executes APT/sudo."""
import os
from pathlib import Path
import pty
import select
import signal
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = r'''
set -euo pipefail
source "$ROOT/scripts/local_server_dependencies.sh"
source "$ROOT/scripts/local_server_dependency_install.sh"
local_server_dependencies_check() { return 0; }
install_assess_batch() { required_packages=(git); optional_packages=(); original_runtime=/usr/bin/php8.3; }
sudo() { printf 'privilege\n' >> "$V3_FIXTURE_EVENTS"; }
resolve_count=0
install_resolve() {
  resolve_count=$((resolve_count + 1))
  transaction=("git=${resolve_count}")
  printf 'Resolved git version %s\n' "$resolve_count"
}
install_privileged_apt() { printf '%s\n' "$1" >> "$V3_FIXTURE_EVENTS"; }
install_reassess() { printf 'Package result: %s\n' "$1"; [[ "$1" == succeeded ]]; }
local_server_dependencies_install
'''


def case(second_answer, startup=False):
    with tempfile.TemporaryDirectory() as directory:
        events = Path(directory) / 'events'
        master, slave = pty.openpty()
        script = SCRIPT.replace('local_server_dependencies_install\n', 'local_server_dependencies_install startup\n') if startup else SCRIPT
        process = subprocess.Popen(['bash', '-c', script], stdin=slave, stdout=slave,
            stderr=slave, env={**os.environ, 'ROOT': str(ROOT), 'V3_FIXTURE_EVENTS': str(events)})
        os.close(slave)
        output = b''
        sent = 0
        deadline = time.monotonic() + 10
        try:
            while time.monotonic() < deadline:
                if select.select([master], [], [], .1)[0]:
                    try:
                        chunk = os.read(master, 65536)
                    except OSError:
                        break
                    output += chunk
                    prompts = output.count(b'[Y/n]:' if startup else b'[type yes to continue]:')
                    if prompts > sent:
                        answer = [('y' if startup else 'yes'), second_answer][sent]
                        if answer in ('SIGINT', 'SIGTERM'):
                            os.kill(process.pid, getattr(signal, answer))
                        else:
                            os.write(master, (answer + '\n').encode())
                        sent += 1
                elif process.poll() is not None:
                    break
            process.wait(timeout=2)
            actions = events.read_text().splitlines()
            assert sent == 2 and b'Install this revised batch?' in output, output
            assert actions.count('update') == 1, actions
            assert ('--yes' in actions) == (second_answer in ('yes', 'y')), actions
            assert process.returncode == (0 if second_answer in ('yes', 'y') else 1), output
            if second_answer in ('no', 'n'):
                assert b'cancelled after metadata refresh; no install attempted' in output, output
        finally:
            if process.poll() is None:
                process.kill()
                process.wait()
            os.close(master)


case('no')
case('yes')
case('n', startup=True)
case('y', startup=True)
case('SIGINT', startup=True)
case('SIGTERM', startup=True)
print('PASS: changed APT batch requires new confirmation; decline never executes installation')

# Signals can arrive between printing the prompt and entering Bash's read.
# Exercise that boundary repeatedly, not only an already-blocked read.
for stop in (signal.SIGINT, signal.SIGTERM):
    for attempt in range(20):
        with tempfile.TemporaryDirectory() as directory:
            events = Path(directory) / 'events'
            master, slave = pty.openpty()
            script = SCRIPT.replace('local_server_dependencies_install\n', 'local_server_dependencies_install startup\n')
            process = subprocess.Popen(['bash', '-c', script], stdin=slave, stdout=slave, stderr=slave,
                env={**os.environ, 'ROOT': str(ROOT), 'V3_FIXTURE_EVENTS': str(events)})
            os.close(slave)
            output = b''
            try:
                deadline = time.monotonic() + 3
                while b'[Y/n]:' not in output and time.monotonic() < deadline:
                    if select.select([master], [], [], .1)[0]:
                        output += os.read(master, 65536)
                assert b'[Y/n]:' in output, output
                os.kill(process.pid, stop)
                process.wait(timeout=2)
                assert process.returncode == 1 and not events.exists(), output
            finally:
                if process.poll() is None:
                    process.kill()
                    process.wait()
                os.close(master)
print('PASS: 40 prompt-boundary signal cancellations exit without privilege/package operations')
