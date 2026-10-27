#!/usr/bin/env python3
"""Exercise the actual editor and writer with deterministic dialog responses."""
import os
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
CONFIG = """<?php return [
'LLM_PROVIDER'=>'custom', 'LLM_API_BASE_URL'=>'https://gateway.test',
'DEDALUS_MODEL'=>'custom-current', 'LLM_TIMEOUT_SECONDS'=>137,
'LLM_API_KEY'=>'fixture-secret', 'UNRELATED'=>'preserved'];
"""


def run_editor(answers, env=None):
    with tempfile.TemporaryDirectory(prefix='tui-editor-') as directory:
        path = Path(directory)
        config = path / 'secrets.php'
        config.write_text(CONFIG)
        (path / 'answers').write_text('\n'.join(answers) + '\n')
        fake = path / 'whiptail'
        fake.write_text('''#!/usr/bin/env python3
import os, sys
from pathlib import Path
p=Path(os.environ['CASE_DIR'])
with (p/'log').open('a') as f: f.write(repr(sys.argv[1:])+'\\n')
count=int((p/'count').read_text()) if (p/'count').exists() else 0
(p/'count').write_text(str(count+1))
answers=(p/'answers').read_text().splitlines()
answer=answers[count] if count<len(answers) else 'CANCEL'
if answer=='CANCEL': sys.exit(1)
os.write(3, answer.encode())
''')
        fake.chmod(0o700)
        clean_env = {k: v for k, v in os.environ.items() if not k.startswith(('LLM_', 'DEDALUS_'))}
        result = subprocess.run(['php', 'scripts/terminal_llm_editor.php'], cwd=ROOT,
            env={**clean_env, 'CASE_DIR': directory, 'FORUM_SECRETS_PATH': str(config),
                 'V3_TUI_WHIPTAIL': str(fake), **(env or {})}, capture_output=True, timeout=10)
        assert result.returncode == 0, result.stderr
        log = (path / 'log').read_text()
        assert 'fixture-secret' not in log + result.stdout.decode() + result.stderr.decode()
        return config.read_text(), log


saved, log = run_editor(['LLM_MODEL', 'new-model', 'review', 'CANCEL', 'CANCEL'])
assert saved == CONFIG, 'Canceled review wrote configuration'
assert "custom-current" in log and 'legacy DEDALUS_MODEL' in log and '137' in log
assert "'--inputbox', 'LLM_MODEL (legacy DEDALUS_MODEL)', '0', '0', 'custom-current'" in log
saved, log = run_editor(['LLM_MODEL', 'new-model', 'review', '', 'CANCEL'])
assert "'LLM_MODEL' => 'new-model'" in saved
assert "'LLM_API_KEY' => 'fixture-secret'" in saved and "'UNRELATED' => 'preserved'" in saved
assert "'LLM_TIMEOUT_SECONDS' => 137" in saved
saved, log = run_editor(['LLM_MODEL', '', 'CANCEL'], {'LLM_MODEL': 'locked-model'})
assert saved == CONFIG and 'environment override; locked' in log
assert 'change deployment configuration' in log
saved, log = run_editor(['LLM_TIMEOUT_SECONDS', '0', 'review', '', 'LLM_TIMEOUT_SECONDS', '99', 'review', '', 'CANCEL'])
assert 'Timeout must be a positive whole number.' in log
assert "'LLM_TIMEOUT_SECONDS' => 99" in saved
saved, log = run_editor(['LLM_API_KEY', '', 'CANCEL'])
assert saved == CONFIG
print('PASS: current values/sources, cancel, preserve unrelated/secret/timeout, locks, validation correction, blank secret')
