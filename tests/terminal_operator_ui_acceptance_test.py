#!/usr/bin/env python3
"""Real whiptail keyboard journeys in an isolated WSL-compatible PTY."""
import errno
import fcntl
import os
from pathlib import Path
import pty
import select
import signal
import struct
import subprocess
import tempfile
import termios
import time

ROOT = Path(__file__).resolve().parents[1]
DOWN = b'\x1b[B'
UP = b'\x1b[A'
ESC = b'\x1b\x1b'


class Terminal:
    def __init__(self, environment, rows=24, columns=80):
        self.pid, self.fd = pty.fork()
        if self.pid == 0:
            os.chdir(ROOT)
            os.environ.update(environment)
            os.execv('./v3', ['./v3', 'tui'])
        self.pending = b''
        self.history = b''
        self.resize(rows, columns)

    def resize(self, rows, columns):
        fcntl.ioctl(self.fd, termios.TIOCSWINSZ, struct.pack('HHHH', rows, columns, 0, 0))

    def read(self, timeout=.1):
        if select.select([self.fd], [], [], timeout)[0]:
            try:
                data = os.read(self.fd, 65536)
            except OSError as error:
                if error.errno == errno.EIO:
                    return
                raise
            self.pending += data
            self.history += data

    def expect(self, value, timeout=8):
        deadline = time.monotonic() + timeout
        while value not in self.pending and time.monotonic() < deadline:
            self.read()
        assert value in self.pending, (value, self.pending[-1800:])
        self.pending = self.pending.split(value, 1)[1]

    def send(self, value):
        self.read(.15)
        self.pending = b''
        os.write(self.fd, value)

    def close(self):
        os.close(self.fd)
        deadline = time.monotonic() + 3
        while time.monotonic() < deadline:
            pid, _ = os.waitpid(self.pid, os.WNOHANG)
            if pid:
                return
            time.sleep(.05)
        os.kill(self.pid, signal.SIGTERM)
        os.waitpid(self.pid, 0)


with tempfile.TemporaryDirectory(prefix='tui-acceptance-') as directory:
    folder = Path(directory)
    config = folder / 'secrets.php'
    config.write_text("<?php return ['LLM_PROVIDER'=>'stub','LLM_MODEL'=>'current-model','LLM_API_KEY'=>'fixture-secret','LLM_TIMEOUT_SECONDS'=>137,'OTHER'=>'keep'];\n")
    repository = folder / 'repository'
    repository.mkdir()
    subprocess.run(['git', 'init', '-q', str(repository)], check=True)
    environment = {
        'TERM': 'xterm-256color', 'FORUM_SECRETS_PATH': str(config),
        'FORUM_REPOSITORY_ROOT': str(repository),
        'FORUM_DATABASE_PATH': str(folder / 'missing.sqlite3'),
        'FORUM_TASK_QUEUE_DATABASE_PATH': str(folder / 'tasks.sqlite3'),
        'SSH_TTY': '/dev/pts/test',  # SSH-style environment, not a remote SSH claim.
    }
    terminal = Terminal(environment)
    try:
        terminal.expect(b'Observed:')
        terminal.expect(b'<Cancel>')
        terminal.send(b'')
        terminal.resize(18, 50)
        terminal.expect(b'\x1b[0m\x1b[?25h\x1b[2J\x1b[H')
        terminal.expect(b'<Cancel>')
        terminal.send(b'')
        terminal.resize(24, 80)
        terminal.expect(b'\x1b[0m\x1b[?25h\x1b[2J\x1b[H')
        terminal.expect(b'<Cancel>')
        terminal.send(DOWN + b'\r')
        terminal.expect(b'Enter: return')
        terminal.send(b'\r')
        terminal.expect(b'Observed:')
        terminal.send(DOWN * 6 + b'\r')
        terminal.expect(b'Read-only commands')
        terminal.send(UP + b'\r')
        terminal.expect(b'Search')
        terminal.expect(b'<Cancel>')
        terminal.send(b'no-such-command\r')
        terminal.expect(b'No matches')
        terminal.send(b'\r')
        terminal.expect(b'Search')
        terminal.expect(b'<Cancel>')
        terminal.send(b'\x15queue\r')
        terminal.expect(b'Read-only commands')
        terminal.send(DOWN + b'\r')
        terminal.expect(b'Enter: return')
        terminal.send(b'\r')
        terminal.expect(b'Search: queue')
        terminal.send(ESC)
        terminal.expect(b'Observed:')
        terminal.send(UP * 2 + b'\r')
        terminal.expect(b'LLM connection')
        terminal.expect(b'current-model')
        terminal.send(b'\r')
        terminal.expect(b'LLM_MODEL')
        terminal.send(b'\x15pty-model\r')
        terminal.expect(b'LLM connection')
        terminal.send(DOWN * 3 + b'\r')
        terminal.expect(b'Save LLM changes?')
        terminal.send(b'\r')
        terminal.expect(b'LLM connection')
        assert "'LLM_MODEL' => 'pty-model'" in config.read_text()
        assert "'LLM_TIMEOUT_SECONDS' => 137" in config.read_text()
        assert "'OTHER' => 'keep'" in config.read_text()
        terminal.send(ESC)
        terminal.expect(b'Observed:')
        # Reopen from the retained home selection; current values survived.
        saved = config.read_bytes()
        terminal.send(b'\r')
        terminal.expect(b'pty-model')
        terminal.send(b'\r')
        terminal.expect(b'LLM_MODEL')
        terminal.send(b'\x15cancel-me\r')
        terminal.expect(b'LLM connection')
        terminal.send(ESC)
        terminal.expect(b'Observed:')
        assert config.read_bytes() == saved
        assert b'fixture-secret' not in terminal.history
        terminal.send(ESC)
    finally:
        terminal.close()
    print('PASS: real whiptail status, search/no-match, diagnostic/return, current-value edit/review/save/reopen/cancel')

    terminal = Terminal(environment, 18, 50)
    try:
        terminal.expect(b'Observed:')
        terminal.expect(b'<Cancel>')
        terminal.send(DOWN + b'\r')
        terminal.expect(b'Enter: return')
        terminal.resize(12, 40)
        terminal.send(b'\r')
        terminal.expect(b'Resize to at least')
        terminal.resize(18, 50)
        terminal.send(b'\r')
        terminal.expect(b'Observed:')
        terminal.expect(b'<Cancel>')
        terminal.send(DOWN * 4 + b'\r')
        terminal.expect(b'LLM connection')
        terminal.expect(b'<Cancel>')
        terminal.send(ESC)
        terminal.expect(b'Observed:')
        terminal.send(ESC)
    finally:
        terminal.close()
    print('PASS: real 50x18 home/editor, 40x12 recovery, resize/re-entry and SSH-style environment')

    # Long values remain editable and the full review can scroll on a narrow terminal.
    long_config = "<?php return ['LLM_PROVIDER'=>'stub','LLM_MODEL'=>'" + 'old-' + 'x' * 600 + "','LLM_API_KEY'=>'fixture-secret'];\n"
    config.write_text(long_config)
    terminal = Terminal(environment, 18, 50)
    try:
        terminal.expect(b'Observed:')
        terminal.send(DOWN * 5 + b'\r')
        terminal.expect(b'LLM connection')
        terminal.expect(b'Model (file)')
        terminal.send(b'\r')
        terminal.expect(b'LLM_MODEL')
        terminal.send(b'\x15' + b'new-' + b'y' * 600 + b'\r')
        terminal.expect(b'LLM connection')
        terminal.send(b'')
        terminal.resize(24, 80)
        terminal.expect(b'\x1b[0m\x1b[?25h\x1b[2J\x1b[H')
        terminal.expect(b'LLM connection')
        terminal.send(DOWN * 3 + b'\r')
        terminal.expect(b'Review changes')
        terminal.expect(b'<Ok>')
        terminal.send(b'\x1b[6~')
        terminal.send(b'\t\r')
        terminal.expect(b'Save LLM changes?')
        terminal.send(ESC)
        terminal.expect(b'LLM connection')
        terminal.send(ESC)
        terminal.expect(b'Observed:')
        assert config.read_text() == long_config
        assert b'fixture-secret' not in terminal.history
        terminal.send(ESC)
    finally:
        terminal.close()
    print('PASS: long-value editing, source visibility, live editor resize, scrolling review and canceled save')
