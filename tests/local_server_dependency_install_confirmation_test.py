#!/usr/bin/env python3
"""PTY fixtures for a changed transaction after refresh; never executes APT/sudo."""
import os
from pathlib import Path
import pty
import select
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


def case(second_answer):
    with tempfile.TemporaryDirectory() as directory:
        events = Path(directory) / 'events'
        master, slave = pty.openpty()
        process = subprocess.Popen(['bash', '-c', SCRIPT], stdin=slave, stdout=slave,
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
                    prompts = output.count(b'[type yes to continue]:')
                    if prompts > sent:
                        answer = ['yes', second_answer][sent]
                        os.write(master, (answer + '\n').encode())
                        sent += 1
                elif process.poll() is not None:
                    break
            process.wait(timeout=2)
            actions = events.read_text().splitlines()
            assert sent == 2 and b'Install this revised batch?' in output, output
            assert actions.count('update') == 1, actions
            assert ('--yes' in actions) == (second_answer == 'yes'), actions
            assert process.returncode == (0 if second_answer == 'yes' else 1), output
            if second_answer == 'no':
                assert b'cancelled after metadata refresh; no install attempted' in output, output
        finally:
            if process.poll() is None:
                process.kill()
                process.wait()
            os.close(master)


case('no')
case('yes')
print('PASS: changed APT batch requires new confirmation; decline never executes installation')
