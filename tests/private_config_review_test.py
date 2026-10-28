#!/usr/bin/env python3
"""Conflict, atomic-write and legacy-stdin regressions using isolated config files."""
import fcntl
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
ENV = {k: v for k, v in os.environ.items() if not k.startswith(('LLM_', 'DEDALUS_'))}


def snapshot(path, env=None):
    result = subprocess.run(['php', '-r',
        'require "autoload.php"; echo ForumRewrite\\Support\\PrivateConfig::snapshot($argv[1])["revision"];', str(path)],
        cwd=ROOT, env={**ENV, **(env or {})}, capture_output=True, check=True)
    return result.stdout.decode()


def update(path, values, revision=None, env=None):
    request = {'values': values}
    if revision is not None:
        request['expected_revision'] = revision
    return subprocess.run(['./v3', 'private-config', 'update-llm', f'--path={path}'],
        cwd=ROOT, env={**ENV, **(env or {})}, input=json.dumps(request), capture_output=True, text=True, timeout=6)


with tempfile.TemporaryDirectory(prefix='tui-review-') as directory:
    path = Path(directory) / 'secrets.php'
    initial = "<?php return ['LLM_PROVIDER'=>'stub','LLM_MODEL'=>'old','LLM_API_KEY'=>'fixture-secret','OTHER'=>'keep'];\n"
    path.write_text(initial)
    revision = snapshot(path)
    assert update(path, {'LLM_MODEL': 'old', 'LLM_API_KEY': ''}, revision).returncode == 0
    assert path.read_text() == initial, 'No-op rewrote config'
    for patch in [{'LLM_TIMEOUT_SECONDS': 0}, {'LLM_MODEL': []}, {'UNKNOWN': 'x'}]:
        result = update(path, patch, revision)
        assert result.returncode == 2 and path.read_text() == initial, result
    path.write_text(initial.replace("'keep'", "'external'"))
    changed = path.read_text()
    result = update(path, {'LLM_MODEL': 'new'}, revision)
    assert result.returncode == 3 and path.read_text() == changed, result
    revision = snapshot(path)
    result = update(path, {'LLM_MODEL': 'new'}, revision, {'LLM_MODEL': 'override'})
    assert result.returncode == 3 and path.read_text() == changed, result
    with open(str(path) + '.lock', 'a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        start = time.monotonic()
        result = update(path, {'LLM_MODEL': 'new'}, revision)
        assert result.returncode == 1 and 'busy' in result.stderr and time.monotonic() - start < 5
        assert path.read_text() == changed
    result = update(path, {'LLM_MODEL': 'new', 'LLM_API_KEY': 'replacement-secret'}, revision)
    assert result.returncode == 0, result
    saved = path.read_text()
    assert "'LLM_MODEL' => 'new'" in saved and "'OTHER' => 'external'" in saved
    assert 'replacement-secret' in saved and 'replacement-secret' not in result.stdout + result.stderr
    assert path.stat().st_mode & 0o777 == 0o600
    # Prior clients omit revision; that contract remains supported.
    assert update(path, {'LLM_MODEL': 'legacy-client'}).returncode == 0
    blocked = Path(directory) / 'directory.php'
    blocked.mkdir()
    result = update(blocked, {'LLM_PROVIDER': 'stub'})
    assert result.returncode == 1 and blocked.is_dir(), result
    assert not list(Path(directory).glob('*.tmp-*')), 'Failed rename left secret temp files'
print('PASS: no-op, invalid input, stale/file/source conflicts, lock timeout, atomic permissions, secret redaction, legacy stdin, failed rename cleanup')
