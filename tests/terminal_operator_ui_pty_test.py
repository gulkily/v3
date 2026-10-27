#!/usr/bin/env python3
"""Real PTY lifecycle regression, using only Python's standard library."""
import errno
import os
import pty
import select
import signal
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def start(command):
    pid, fd = pty.fork()
    if pid == 0:
        os.chdir(ROOT)
        os.execv('/bin/bash', ['bash', '-c', command])
    return pid, fd


def until(fd, needle, timeout=5):
    output = b''
    end = time.monotonic() + timeout
    while needle not in output and time.monotonic() < end:
        if select.select([fd], [], [], .1)[0]:
            try:
                output += os.read(fd, 65536)
            except OSError as e:
                if e.errno == errno.EIO:
                    break
                raise
    assert needle in output, (needle, output)
    return output


def finish(pid, fd):
    os.close(fd)
    deadline = time.monotonic() + 3
    while time.monotonic() < deadline:
        result, status = os.waitpid(pid, os.WNOHANG)
        if result:
            assert os.waitstatus_to_exitcode(status) == 0, status
            return
        time.sleep(.02)
    os.kill(pid, signal.SIGKILL)
    os.waitpid(pid, 0)
    raise AssertionError('TUI did not exit')


prefix = 'source scripts/terminal_operator_ui_lib.sh; before=$(stty -g); '
suffix = '; [[ "$before" == "$(stty -g)" ]] || exit 4; echo RESTORED'
pid, fd = start(prefix + "show_command_output Test bash -c 'echo EARLY; sleep 1; echo LATE'" + suffix)
t0 = time.monotonic()
until(fd, b'EARLY')
assert time.monotonic() - t0 < .8, 'Output buffered until completion'
until(fd, b'Enter: return')
os.write(fd, b'\n')
until(fd, b'RESTORED')
finish(pid, fd)

pid, fd = start(prefix + "show_command_output Test bash -c 'sleep 30 & echo CHILD:$!; wait'" + suffix)
output = until(fd, b'CHILD:')
while b'\n' not in output.split(b'CHILD:')[1]:
    output += os.read(fd, 1024)
child = int(output.split(b'CHILD:')[1].split()[0])
os.write(fd, b'\x03')
until(fd, b'Enter: return')
# A killed child may briefly remain a zombie until PID 1 reaps it.
stat = Path(f'/proc/{child}/stat')
assert not stat.exists() or stat.read_text().split()[2] == 'Z', 'Descendant survived'
os.write(fd, b'\n')
until(fd, b'RESTORED')
finish(pid, fd)

pid, fd = start(prefix + "show_command_output Test bash -c 'echo ERROR; exit 7'" + suffix)
until(fd, b'Failed (exit 7)')
os.write(fd, b'\n')
until(fd, b'RESTORED')
finish(pid, fd)
print('PASS: live output, success, failure, interruption, descendant cleanup, terminal restoration')
