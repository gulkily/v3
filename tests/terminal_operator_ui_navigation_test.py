#!/usr/bin/env python3
"""Chooser state and literal search regression without terminal dependencies."""
import os
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='tui-navigation-') as directory:
    path = Path(directory)
    fake = path / 'whiptail'
    fake.write_text('''#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CASE_DIR/log"
n=$(cat "$CASE_DIR/count" 2>/dev/null || echo 0)
n=$((n+1)); echo "$n" > "$CASE_DIR/count"
case "$n" in
  1|3) echo search >&2 ;;
  2) echo '$(touch SHOULD_NOT_EXIST)' >&2 ;;
  4) echo QUEUE >&2 ;;
  5) echo task-queue >&2 ;;
  *) exit 1 ;;
esac
''')
    fake.chmod(0o700)
    result = subprocess.run(['bash', '-c', '''
source scripts/terminal_operator_ui_lib.sh
export V3_TUI_WHIPTAIL="$CASE_DIR/whiptail"; root="$PWD"
terminal_ready() { tui_rows=24; tui_cols=80; }
show_command_output() { printf '%s\\n' "$*" >> "$CASE_DIR/run"; }
show_command_launcher
[[ "$chooser_filter" == QUEUE && "$chooser_selection" == task-queue ]]
'''], cwd=ROOT, env={**os.environ, 'CASE_DIR': directory}, capture_output=True, timeout=5)
    assert result.returncode == 0, result.stderr
    log = (path / 'log').read_text()
    assert 'No matches' in log
    assert '--default-item task-queue' in log
    assert 'Search: QUEUE' in log
    assert (path / 'run').read_text() == f'Task queue status {ROOT}/v3 task-queue status\n'
    assert not (ROOT / 'SHOULD_NOT_EXIST').exists()
print('PASS: literal/case-insensitive search, no-match recovery, selection/filter retention, allowlisted execution')
