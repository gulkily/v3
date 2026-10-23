"""Real-Git acceptance tests for the operator checkout command (standard library only)."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class CheckoutsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='v3-checkouts-')
        self.addCleanup(self.temp.cleanup)
        self.addCleanup(self.stop_background)
        self.base = Path(self.temp.name)
        self.env = dict(os.environ, GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL='/dev/null',
                        GIT_AUTHOR_NAME='Checkout test', GIT_AUTHOR_EMAIL='test@example.invalid',
                        GIT_COMMITTER_NAME='Checkout test', GIT_COMMITTER_EMAIL='test@example.invalid')
        self.registry = self.base / 'config' / 'checkouts.json'
        self.state = self.base / 'state'
        self.up = self.base / 'upstream'
        self.git(self.base, 'init', '-q', '-b', 'main', str(self.up))
        self.commit(self.up, 'base.txt', 'base')
        self.clone = self.base / 'clone'
        self.git(self.base, 'clone', '-q', str(self.up), str(self.clone))

    def stop_background(self):
        import signal
        import time
        state = self.base / 'state' / 'refresh.json'
        for _ in range(60):
            if not state.exists():
                return
            try:
                status = json.loads(state.read_text())
                pid = status.get('pid')
                if pid and status.get('state') == 'running' and os.getpgid(pid) == pid:
                    os.killpg(pid, signal.SIGTERM)
                    time.sleep(0.1)
                elif status.get('state') != 'queued':
                    return
            except (ProcessLookupError, json.JSONDecodeError):
                return
            time.sleep(0.05)

    def wait_refresh(self):
        import time
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            path = self.state / 'refresh.json'
            if path.exists():
                status = json.loads(path.read_text())
                if status['state'] not in ['queued', 'running']:
                    return status
            time.sleep(0.05)
        self.fail('Refresh did not finish')

    def git(self, directory, *args, check=True):
        return subprocess.run(['git', '-C', str(directory), *args], env=self.env,
                              text=True, capture_output=True, check=check).stdout.strip()

    def commit(self, directory, name, text):
        (directory / name).write_text(text)
        self.git(directory, 'add', '--', name)
        self.git(directory, 'commit', '-qm', text)
        return self.git(directory, 'rev-parse', 'HEAD')

    def cli(self, *args, code=0, raw=False, cwd=None, auto_refresh=False):
        if not auto_refresh and (not args or str(args[0]).startswith('--') or args[0] == 'overview') and '--help' not in args:
            args = (*args, '--no-refresh')
        result = subprocess.run([str(ROOT / 'v3'), 'checkouts', *map(str, args),
                                 '--registry=' + str(self.registry), '--state-dir=' + str(self.state),
                                 *([] if raw else ['--json'])], cwd=cwd or ROOT,
                                env=self.env, text=True, capture_output=True)
        if code is None:
            self.assertIn(result.returncode, [0, 2], result.stdout + result.stderr)
        else:
            self.assertEqual(code, result.returncode, result.stdout + result.stderr)
        return result.stdout if raw else json.loads(result.stdout)

    def add(self, path=None, **options):
        return self.cli('add', path or self.clone, *[f'--{key}={value}' for key, value in options.items()])

    def test_registry_lifecycle_and_private_permissions(self):
        self.add()
        self.assertEqual(str(self.clone), self.cli('list')['checkouts'][0]['path'])
        self.assertEqual(0o600, self.registry.stat().st_mode & 0o777)
        self.cli('remove', self.clone)
        self.assertEqual([], self.cli('list')['checkouts'])
        self.assertTrue(self.clone.exists())

    def test_alias_and_shared_storage_rejected(self):
        self.add()
        alias = self.base / 'alias'
        alias.symlink_to(self.clone)
        self.assertIn('already registered', self.cli('add', alias, code=1)['error'])
        linked = self.base / 'linked'
        self.git(self.clone, 'worktree', 'add', '-q', '-b', 'topic', str(linked))
        self.assertIn('shared Git storage', self.cli('add', linked, code=1)['error'])

    def test_missing_moved_and_malformed_registry(self):
        self.add()
        self.clone.rename(self.base / 'moved')
        self.cli('remove', self.clone)
        self.registry.write_text('{broken')
        self.assertIn('error', self.cli('list', code=1))

    def test_help_errors_and_roles(self):
        self.assertIn('checkouts', self.cli('--help', raw=True))
        self.cli('--bogus', code=1)
        self.cli('add', self.clone, '--role=oops', code=1)
        self.add(role='test')
        self.assertEqual('test', self.cli('list')['checkouts'][0]['role'])

    def test_credential_remote_not_saved(self):
        self.git(self.clone, 'remote', 'set-url', 'origin', 'https://user:secret@example.invalid/repo.git')
        response = self.cli('add', self.clone, code=1)
        self.assertNotIn('secret', json.dumps(response))
        self.assertFalse(self.registry.exists())

    def preview(self, **options):
        return self.cli('preview', *[f'--{key}={value}' for key, value in options.items()], code=None)

    def test_history_forward_and_ahead(self):
        self.add()
        self.assertEqual('current', self.preview()['checkouts'][0]['action'])
        self.commit(self.up, 'new.txt', 'new')
        old = self.git(self.clone, 'rev-parse', 'HEAD')
        self.assertEqual('fast-forward', self.preview()['checkouts'][0]['action'])
        self.assertEqual(old, self.git(self.clone, 'rev-parse', 'HEAD'))

    def test_unknown_divergence_blocks_but_known_feature_can_merge(self):
        self.add()
        self.preview()
        self.git(self.clone, 'switch', '-qc', 'feature')
        self.commit(self.clone, 'local.txt', 'local')
        self.commit(self.up, 'remote.txt', 'remote')
        self.assertEqual('merge', self.preview()['checkouts'][0]['action'])
        for file in self.state.glob('history-*.json'):
            file.unlink()
        self.assertEqual('migration-required', self.preview()['checkouts'][0]['action'])

    def test_rewrite_detected_and_remains_blocked_after_fetch(self):
        self.add()
        self.preview()
        self.commit(self.up, 'old.txt', 'old history')
        self.preview()
        self.git(self.up, 'reset', '--hard', 'HEAD~1')
        self.commit(self.up, 'replacement.txt', 'rewritten history')
        for _ in range(2):
            self.assertEqual('migration-required', self.preview()['checkouts'][0]['action'])

    def test_already_fetched_rewrite_without_baseline_blocks(self):
        self.commit(self.up, 'old.txt', 'old')
        self.git(self.clone, 'pull', '--ff-only')
        self.git(self.up, 'reset', '--hard', 'HEAD~1')
        self.commit(self.up, 'replacement.txt', 'replacement')
        self.git(self.clone, 'fetch', 'origin')
        self.add()
        self.assertEqual('migration-required', self.preview()['checkouts'][0]['action'])

    def test_source_dirty_busy_and_test_exclusion(self):
        self.add(role='test')
        self.assertEqual('excluded', self.preview()['checkouts'][0]['action'])
        self.assertEqual('current', self.preview(path=self.clone)['checkouts'][0]['action'])
        (self.clone / 'untracked').write_text('unfinished')
        self.assertEqual('dirty', self.preview(path=self.clone)['checkouts'][0]['action'])
        (self.clone / '.git' / 'MERGE_HEAD').write_text(self.git(self.clone, 'rev-parse', 'HEAD'))
        self.assertEqual('busy', self.preview(path=self.clone)['checkouts'][0]['action'])
        (self.clone / '.git' / 'MERGE_HEAD').unlink()
        self.git(self.clone, 'remote', 'set-url', 'origin', str(self.base / 'different'))
        self.assertEqual('unavailable', self.preview(path=self.clone)['checkouts'][0]['action'])

    def test_saved_preview_and_human_output(self):
        self.add()
        result = self.preview()
        plan = self.state / 'previews' / (result['preview_id'] + '.json')
        self.assertEqual(0o600, plan.stat().st_mode & 0o777)
        self.assertEqual(result['preview_id'], json.loads(plan.read_text())['plan']['id'])
        output = self.cli('preview', raw=True)
        self.assertIn('  current', output)
        self.assertNotIn('Source:', output)
        self.assertIn('no checked-out files changed', output)
        self.assertIn('--sessions-paused', output)
        (self.clone / 'new').write_text('dirty')
        result = self.cli('preview', code=2)
        self.assertEqual(2, result['exit_code'])

    def test_missing_path_and_bad_selector_visible(self):
        self.add()
        self.clone.rename(self.base / 'moved')
        result = self.cli('preview', code=2)
        self.assertEqual('unavailable', result['checkouts'][0]['action'])
        self.cli('preview', '--path=/not/registered', code=1)

    def apply(self, preview, code=0):
        return self.cli('apply', preview['preview_id'], '--sessions-paused', code=code)

    def test_apply_fast_forward_and_consume(self):
        self.add()
        self.preview()
        target = self.commit(self.up, 'new.txt', 'new')
        preview = self.preview()
        self.cli('apply', preview['preview_id'], code=1)
        result = self.apply(preview)
        self.assertEqual('updated', result['checkouts'][0]['action'])
        self.assertEqual(target, self.git(self.clone, 'rev-parse', 'HEAD'))
        self.apply(preview, code=1)
        self.assertEqual('current', self.preview()['checkouts'][0]['action'])

    def test_apply_rejects_changed_or_corrupt_preview_and_registry(self):
        self.add()
        preview = self.preview()
        path = self.state / 'previews' / (preview['preview_id'] + '.json')
        data = json.loads(path.read_text())
        data['plan']['checkouts'][0]['path'] = '/unintended'
        path.write_text(json.dumps(data))
        self.assertIn('damaged', self.apply(preview, code=1)['error'])
        preview = self.preview()
        self.cli('remove', self.clone)
        self.assertIn('Registry changed', self.apply(preview, code=1)['error'])
        self.cli('apply', '../bad', '--sessions-paused', code=1)

    def test_apply_skips_changed_head_branch_remote_dirty_and_target(self):
        for change in ['branch', 'dirty', 'remote', 'target', 'head']:
            with self.subTest(change=change):
                self.git(self.clone, 'reset', '--hard', 'origin/main')
                self.git(self.clone, 'clean', '-fd')
                self.git(self.clone, 'switch', '-q', 'main')
                self.git(self.clone, 'remote', 'set-url', 'origin', str(self.up))
                if not self.registry.exists():
                    self.add()
                self.commit(self.up, 'new.txt', 'new ' + change)
                preview = self.preview()
                if change == 'branch':
                    self.git(self.clone, 'switch', '-qc', 'other')
                elif change == 'dirty':
                    (self.clone / 'unfinished').write_text('unfinished')
                elif change == 'remote':
                    self.git(self.clone, 'remote', 'set-url', 'origin', str(self.base / 'wrong'))
                elif change == 'target':
                    self.commit(self.up, 'further', 'further')
                    self.git(self.clone, 'fetch', 'origin')
                else:
                    self.commit(self.clone, 'local', 'local')
                old = self.git(self.clone, 'rev-parse', 'HEAD')
                self.assertEqual('failed', self.apply(preview, code=2)['checkouts'][0]['action'])
                self.assertEqual(old, self.git(self.clone, 'rev-parse', 'HEAD'))

    def test_interrupted_journal_never_replayed(self):
        self.add()
        preview = self.preview()
        path = self.state / 'previews' / (preview['preview_id'] + '.result.json')
        path.write_text(json.dumps({'complete': False, 'checkouts': [{'action': 'attempting'}]}))
        self.assertIn('interrupted', self.apply(preview, code=1)['error'])

    def test_tool_lock_contention(self):
        import fcntl
        self.add()
        with (self.state / 'registry.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.assertIn('Another checkout command', self.cli('list', code=1)['error'])

    def make_diverged(self, conflict=False):
        self.add()
        self.preview()
        self.git(self.clone, 'switch', '-qc', 'feature')
        local = self.commit(self.clone, 'base.txt' if conflict else 'local.txt', 'local feature')
        source = self.commit(self.up, 'base.txt' if conflict else 'remote.txt', 'remote change')
        return local, source

    def test_feature_merge_preserves_parents(self):
        local, source = self.make_diverged()
        result = self.apply(self.preview())
        self.assertEqual('updated', result['checkouts'][0]['action'])
        parents = self.git(self.clone, 'show', '-s', '--format=%P', 'HEAD').split()
        self.assertEqual([local, source], parents)
        self.assertEqual('', self.git(self.clone, 'status', '--porcelain'))
        self.assertEqual('ahead', self.preview()['checkouts'][0]['action'])

    def test_conflict_independent_success_abort_and_retry(self):
        local, source = self.make_diverged(conflict=True)
        other = self.base / 'other'
        self.git(self.base, 'clone', '-q', str(self.up), str(other))
        self.add(other)
        self.commit(self.up, 'new.txt', 'more')
        result = self.apply(self.preview(), code=2)
        self.assertEqual(['conflict', 'updated'], [r['action'] for r in result['checkouts']])
        self.assertIn('merge --abort', result['checkouts'][0]['reason'])
        self.assertEqual(local, self.git(self.clone, 'rev-parse', 'HEAD'))
        self.assertEqual('busy', self.preview()['checkouts'][0]['action'])
        self.git(self.clone, 'merge', '--abort')
        self.git(self.clone, 'merge', '-s', 'ours', '--no-edit', 'origin/main')
        self.assertEqual('ahead', self.preview()['checkouts'][0]['action'])

    def test_failing_merge_hook_and_missing_identity(self):
        self.make_diverged()
        hook = self.clone / '.git' / 'hooks' / 'pre-merge-commit'
        hook.write_text('#!/bin/sh\nexit 1\n')
        hook.chmod(0o700)
        result = self.apply(self.preview(), code=2)
        self.assertEqual('failed', result['checkouts'][0]['action'])
        self.assertIn('hooks', result['checkouts'][0]['reason'])
        self.git(self.clone, 'merge', '--abort')
        hook.unlink()
        self.git(self.clone, 'config', 'user.useConfigOnly', 'true')
        for key in ['GIT_AUTHOR_NAME', 'GIT_AUTHOR_EMAIL', 'GIT_COMMITTER_NAME', 'GIT_COMMITTER_EMAIL']:
            self.env.pop(key)
        result = self.apply(self.preview(), code=2)
        self.assertEqual('failed', result['checkouts'][0]['action'])

    def test_main_divergence_is_attention_and_never_merged(self):
        self.add()
        self.preview()
        old = self.commit(self.clone, 'local.txt', 'local')
        self.commit(self.up, 'remote.txt', 'remote')
        preview = self.cli('preview', code=2)
        self.assertEqual('diverged', preview['checkouts'][0]['action'])
        self.apply(preview, code=2)
        self.assertEqual(old, self.git(self.clone, 'rev-parse', 'HEAD'))

    def test_reconciled_rewrite_reenrollment(self):
        self.add()
        self.preview()
        self.commit(self.up, 'old', 'old')
        self.preview()
        self.git(self.up, 'reset', '--hard', 'HEAD~1')
        target = self.commit(self.up, 'new', 'replacement')
        self.assertEqual('migration-required', self.preview()['checkouts'][0]['action'])
        self.git(self.clone, 'reset', '--hard', target)
        self.cli('remove', self.clone)
        self.add()
        self.assertEqual('current', self.preview()['checkouts'][0]['action'])
        self.assertTrue(list(self.state.glob('history-*.retired-*')))

    def test_help_option_scope_and_custom_apply_command(self):
        self.add()
        self.cli('apply', 'bad', '--path=' + str(self.clone), code=1)
        self.cli('list', '--target=other', code=1)
        output = self.cli('preview', raw=True)
        self.assertIn('--registry=', output)
        self.assertIn('--state-dir=', output)
        self.assertIn('Exit: 0', self.cli('--help', raw=True))

    def test_registry_remove_readd_invalidates_old_preview(self):
        self.add()
        preview = self.preview()
        self.cli('remove', self.clone)
        self.add()
        self.assertIn('Registry changed', self.apply(preview, code=1)['error'])

    def test_shared_registry_from_two_project_directories(self):
        self.add()
        self.assertEqual(str(self.clone), self.cli('list', cwd=self.up)['checkouts'][0]['path'])
        preview = self.cli('preview', cwd=self.clone)
        self.assertEqual('current', preview['checkouts'][0]['action'])
        self.assertEqual(0, self.apply(preview)['exit_code'])

    def test_ignored_local_file_is_never_overwritten(self):
        self.commit(self.up, '.gitignore', 'private.local\n')
        self.git(self.clone, 'pull', '--ff-only')
        self.add()
        self.preview()
        (self.clone / 'private.local').write_text('private local configuration')
        (self.up / 'private.local').write_text('tracked upstream content')
        self.git(self.up, 'add', '-f', 'private.local')
        self.git(self.up, 'commit', '-qm', 'track previously ignored file')
        old = self.git(self.clone, 'rev-parse', 'HEAD')
        result = self.apply(self.preview(), code=2)
        self.assertEqual('failed', result['checkouts'][0]['action'])
        self.assertEqual(old, self.git(self.clone, 'rev-parse', 'HEAD'))
        self.assertEqual('private local configuration', (self.clone / 'private.local').read_text())

    def test_post_merge_hook_changes_require_attention(self):
        self.add()
        self.preview()
        target = self.commit(self.up, 'new', 'new')
        hook = self.clone / '.git' / 'hooks' / 'post-merge'
        hook.write_text('#!/bin/sh\nprintf hook-change > hook-created-file\n')
        hook.chmod(0o700)
        result = self.apply(self.preview(), code=2)
        self.assertEqual('failed', result['checkouts'][0]['action'])
        self.assertIn('HEAD may have advanced', result['checkouts'][0]['reason'])
        self.assertEqual(target, self.git(self.clone, 'rev-parse', 'HEAD'))
        self.assertTrue((self.clone / 'hook-created-file').exists())

    def test_registry_uses_xdg_defaults_across_project_directories(self):
        self.env['XDG_CONFIG_HOME'] = str(self.base / 'config-home')
        self.env['XDG_STATE_HOME'] = str(self.base / 'state-home')
        self.addCleanup(self.env.pop, 'XDG_CONFIG_HOME')
        self.addCleanup(self.env.pop, 'XDG_STATE_HOME')
        for cwd, args in [(self.clone, ['add', str(self.clone)]), (self.up, ['list'])]:
            result = subprocess.run([str(ROOT / 'v3'), 'checkouts', *args, '--json'], cwd=cwd,
                                    env=self.env, text=True, capture_output=True)
            self.assertEqual(0, result.returncode, result.stderr + result.stdout)
            self.assertEqual(str(self.clone), json.loads(result.stdout)['checkouts'][0]['path'])
        self.assertTrue((self.base / 'config-home' / 'v3' / 'checkouts.json').exists())

    def test_remote_failure_and_existing_busy_skip_apply(self):
        self.add()
        self.preview()
        before = self.git(self.clone, 'rev-parse', 'HEAD')
        self.up.rename(self.base / 'offline')
        preview = self.cli('preview', code=2)
        self.assertEqual('unavailable', preview['checkouts'][0]['action'])
        self.apply(preview, code=2)
        self.assertEqual(before, self.git(self.clone, 'rev-parse', 'HEAD'))
        (self.base / 'offline').rename(self.up)
        (self.clone / '.git' / 'CHERRY_PICK_HEAD').write_text(before)
        preview = self.cli('preview', code=2)
        self.assertEqual('busy', preview['checkouts'][0]['action'])
        self.apply(preview, code=2)
        self.assertTrue((self.clone / '.git' / 'CHERRY_PICK_HEAD').exists())

    def test_no_live_paths_and_shared_storage_identity_change(self):
        self.add()
        self.commit(self.up, 'new', 'new')
        preview = self.preview()
        old = self.git(self.clone, 'rev-parse', 'HEAD')
        self.git(self.clone, 'worktree', 'add', '-q', '-b', 'linked', str(self.base / 'linked'))
        self.clone.rename(self.base / 'preserved-original')
        self.git(self.base, 'clone', '-q', str(self.up), str(self.clone))
        self.git(self.clone, 'reset', '--hard', old)
        result = self.apply(preview, code=2)
        self.assertEqual('failed', result['checkouts'][0]['action'])
        self.assertIn('identity changed', result['checkouts'][0]['reason'])
        self.assertEqual(old, self.git(self.base / 'preserved-original', 'rev-parse', 'HEAD'))

    def test_external_fetch_rewrite_detected_beyond_older_branch_baseline(self):
        self.add()
        self.preview()
        self.git(self.clone, 'switch', '-qc', 'feature')
        self.commit(self.clone, 'local', 'local feature')
        self.commit(self.up, 'removed', 'soon rewritten')
        self.assertEqual('merge', self.preview()['checkouts'][0]['action'])
        self.git(self.up, 'reset', '--hard', 'HEAD~1')
        self.commit(self.up, 'replacement', 'replacement history')
        self.git(self.clone, 'fetch', 'origin')
        self.assertEqual('migration-required', self.preview()['checkouts'][0]['action'])

    def test_empty_preview_has_setup_guidance(self):
        self.assertIn('No clones registered', self.cli('preview', code=1)['error'])

    def test_real_interruption_preserves_attempt_and_blocks_replay(self):
        import shlex
        import signal
        import time
        self.make_diverged()
        marker = self.base / 'hook-started'
        hook = self.clone / '.git' / 'hooks' / 'pre-merge-commit'
        hook.write_text('#!/bin/sh\ntouch ' + shlex.quote(str(marker)) + '\nsleep 30\n')
        hook.chmod(0o700)
        preview = self.preview()
        process = subprocess.Popen([str(ROOT / 'v3'), 'checkouts', 'apply', preview['preview_id'],
                                    '--sessions-paused', '--json', '--registry=' + str(self.registry),
                                    '--state-dir=' + str(self.state)], env=self.env,
                                   stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                   text=True, start_new_session=True)
        try:
            deadline = time.monotonic() + 10
            while not marker.exists() and time.monotonic() < deadline:
                if process.poll() is not None:
                    self.fail('Apply exited before hook started: ' + process.communicate()[0])
                time.sleep(0.05)
            self.assertTrue(marker.exists(), 'Merge hook did not start')
        finally:
            if process.poll() is None:
                os.killpg(process.pid, signal.SIGKILL)
            process.communicate(timeout=5)
        journal = json.loads((self.state / 'previews' / (preview['preview_id'] + '.result.json')).read_text())
        self.assertFalse(journal['complete'])
        self.assertEqual('attempting', journal['checkouts'][0]['action'])
        self.assertIn('interrupted', self.apply(preview, code=1)['error'])
        self.assertIn(self.preview()['checkouts'][0]['action'], ['busy', 'dirty'])

    def test_explicit_preview_with_one_line_per_checkout(self):
        self.add()
        other = self.base / 'other'
        self.git(self.base, 'clone', '-q', str(self.up), str(other))
        self.add(other)
        old = self.git(self.clone, 'rev-parse', 'HEAD')
        target = self.commit(self.up, 'new', 'new source')
        (other / 'unfinished').write_text('unfinished')
        result = self.cli('preview', code=2)
        self.assertEqual('preview', result['command'])
        self.assertEqual(['fast-forward', 'dirty'], [r['action'] for r in result['checkouts']])
        output = self.cli('preview', raw=True, code=2)
        rows = [line for line in output.splitlines() if line.startswith(('clone ', 'other '))]
        self.assertEqual(2, len(rows))
        self.assertIn('fast-forward', rows[0])
        self.assertIn('dirty', rows[1])
        self.assertIn('preserve changes', rows[1])
        self.assertNotIn('Source:', output)
        self.assertEqual(6, len(output.splitlines()))
        self.assertEqual(old, self.git(self.clone, 'rev-parse', 'HEAD'))
        self.assertEqual(target, self.git(self.clone, 'rev-parse', 'origin/main'))

    def test_overview_cold_read_never_fetches_or_waits_on_manager_lock(self):
        import fcntl
        import time
        self.add()
        self.up.rename(self.base / 'offline')
        with (self.state / 'registry.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            start = time.monotonic()
            result = self.cli(code=2)
            self.assertLess(time.monotonic() - start, 1)
        self.assertEqual('overview', result['command'])
        self.assertEqual('unchecked', result['checkouts'][0]['action'])
        self.assertNotIn('preview_id', result)
        self.assertFalse((self.state / 'previews').exists())

    def test_overview_invalid_cache_and_registry_changes_do_not_reuse_rows(self):
        self.add()
        first = self.cli(code=2)
        cache = self.state / ('overview-' + first['cache_key'] + '.json')
        cache.write_text('{broken')
        self.assertEqual('unchecked', self.cli(code=2)['checkouts'][0]['action'])
        self.cli('remove', self.clone)
        self.assertEqual([], self.cli()['checkouts'])
        self.add(role='test')
        self.assertEqual('excluded', self.cli()['checkouts'][0]['action'])
        self.assertEqual('unchecked', self.cli('--path=' + str(self.clone), code=2)['checkouts'][0]['action'])

    def test_columns_width_labels_controls_and_full_json(self):
        self.add()
        other_parent = self.base / 'another-parent'
        other_parent.mkdir()
        other = other_parent / 'clone'
        self.git(self.base, 'clone', '-q', str(self.up), str(other))
        self.add(other)
        self.env['COLUMNS'] = '80'
        result = self.cli(code=2)
        self.assertEqual(str(other), result['checkouts'][1]['path'])
        for width in [30, 80, 120]:
            self.env['COLUMNS'] = str(width)
            output = self.cli(raw=True, code=2)
            lines = output.splitlines()
            self.assertIn('ACTION', lines[0])
            self.assertLessEqual(len(lines[0]), width)
            self.assertEqual(2, sum('unchecked' in line for line in lines[1:3]))
            self.assertNotEqual(lines[1].split()[0], lines[2].split()[0])
            self.assertTrue(all(len(line) <= width for line in lines[:3]))

    def test_renderer_sanitizes_values_and_retains_json_independently(self):
        program = r"""require 'autoload.php';
        $row=['path'=>'/one/界界界界界界/clone', 'role'=>'development', 'remote'=>'origin', 'target'=>'main',
        'action'=>'conflict', 'reason'=>'full recovery', 'snapshot'=>['branch'=>"feature/long\n\033[31mname"]];
        echo json_encode((new ForumRewrite\Checkouts\CheckoutRenderer(80))->render([$row]));"""
        result = subprocess.run(['php', '-r', program], cwd=ROOT, text=True, capture_output=True)
        self.assertEqual(0, result.returncode, result.stderr)
        rendered = json.loads(result.stdout)
        self.assertEqual(2, len(rendered['lines']))
        self.assertNotIn('\n', rendered['lines'][1])
        self.assertNotIn('\x1b', rendered['lines'][1])

    def test_detached_refresh_populates_cache_and_coalesces(self):
        import time
        self.add()
        start = time.monotonic()
        first = self.cli(code=2, auto_refresh=True)
        self.assertLess(time.monotonic() - start, 1)
        self.assertIn(first['refresh']['state'], ['queued', 'running'])
        token = first['refresh'].get('token')
        second = self.cli(code=None, auto_refresh=True)
        self.assertNotIn('preview_id', second)
        status = self.wait_refresh()
        self.assertEqual('complete', status['state'], status)
        self.assertEqual(token, status['token'])
        result = self.cli(auto_refresh=True)
        self.assertEqual('current', result['checkouts'][0]['action'])
        self.assertEqual('fresh', result['refresh']['state'])
        self.assertFalse((self.state / 'previews').exists())

    def test_refresh_failure_preserves_good_observation_and_age(self):
        self.add()
        result = self.cli('refresh')
        observed = result['checkouts'][0]['observed_at']
        self.up.rename(self.base / 'offline')
        result = self.cli('refresh', code=2)
        self.assertEqual('current', result['checkouts'][0]['action'])
        self.assertEqual(observed, result['checkouts'][0]['observed_at'])
        self.assertIn('last_error', result['checkouts'][0])
        self.assertIn('stale:', self.cli(raw=True, code=2))

    def test_background_deferred_on_apply_lock_and_spawn_failure(self):
        import fcntl
        self.add()
        with (self.state / 'registry.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.cli(code=2, auto_refresh=True)
            self.assertEqual('deferred', self.wait_refresh()['state'])
        (self.state / 'refresh.json').unlink()
        result = subprocess.run(['php', '-d', 'disable_functions=proc_open', str(ROOT / 'scripts/checkouts.php'),
                                 '--json', '--registry=' + str(self.registry), '--state-dir=' + str(self.state)],
                                env=self.env, text=True, capture_output=True)
        self.assertEqual(2, result.returncode, result.stderr)
        self.assertEqual('failed', json.loads(result.stdout)['refresh']['state'])

    def test_cold_worker_failure_and_retry(self):
        self.add()
        self.up.rename(self.base / 'offline')
        self.cli(code=2, auto_refresh=True)
        self.assertEqual('failed', self.wait_refresh()['state'])
        self.assertEqual('unavailable', self.cli(code=2)['checkouts'][0]['action'])
        (self.base / 'offline').rename(self.up)
        self.cli('refresh')
        self.assertEqual('current', self.cli()['checkouts'][0]['action'])

    def slow_fetch(self, path, delay=0.4, marker=None):
        import shlex
        wrapper = self.base / ('upload-' + path.name)
        marker = marker or self.base / ('started-' + path.name)
        wrapper.write_text('#!/bin/sh\ndate +%s%N > ' + shlex.quote(str(marker))
                           + '\nsleep ' + str(delay) + '\nexec git-upload-pack "$@"\n')
        wrapper.chmod(0o700)
        self.git(path, 'config', 'remote.origin.uploadpack', str(wrapper))
        return marker

    def test_independent_fetches_overlap_and_foreground_stays_fast(self):
        import time
        clones = [self.clone]
        for name in ['second', 'third']:
            path = self.base / name
            self.git(self.base, 'clone', '-q', str(self.up), str(path))
            clones.append(path)
        markers = []
        for path in clones:
            self.add(path)
            markers.append(self.slow_fetch(path, delay=1))
        self.cli(code=2, auto_refresh=True)
        deadline = time.monotonic() + 5
        while not all(p.exists() for p in markers) and time.monotonic() < deadline:
            time.sleep(0.02)
        self.assertTrue(all(p.exists() for p in markers))
        stamps = [int(p.read_text()) for p in markers]
        self.assertLess((max(stamps) - min(stamps)) / 1e9, 0.4)
        start = time.monotonic()
        result = self.cli(code=2, auto_refresh=True)
        self.assertLess(time.monotonic() - start, 1)
        self.assertEqual('running', result['refresh']['state'])
        self.assertEqual('complete', self.wait_refresh()['state'])

    def test_registry_change_during_refresh_discards_cache(self):
        import time
        self.add()
        marker = self.slow_fetch(self.clone, delay=0.5)
        first = self.cli(code=2, auto_refresh=True)
        deadline = time.monotonic() + 5
        while not marker.exists() and time.monotonic() < deadline:
            time.sleep(0.02)
        self.assertTrue(marker.exists())
        registry = json.loads(self.registry.read_text())
        registry['revision'] = 'manually-changed-generation'
        self.registry.write_text(json.dumps(registry))
        status = self.wait_refresh()
        self.assertEqual('failed', status['state'])
        self.assertIn('Registry changed', status['reason'])
        self.assertFalse((self.state / ('overview-' + first['cache_key'] + '.json')).exists())
        self.assertEqual('unchecked', self.cli(code=2)['checkouts'][0]['action'])

    def test_cancelled_worker_cannot_publish_cache_or_replay_old_token(self):
        import signal
        import time
        self.add()
        marker = self.slow_fetch(self.clone, delay=10)
        first = self.cli(code=2, auto_refresh=True)
        deadline = time.monotonic() + 5
        while not marker.exists() and time.monotonic() < deadline:
            time.sleep(0.02)
        self.assertTrue(marker.exists())
        status = json.loads((self.state / 'refresh.json').read_text())
        self.assertEqual(status['pid'], os.getpgid(status['pid']))
        os.kill(status['pid'], signal.SIGTERM)
        self.assertEqual('failed', self.wait_refresh()['state'])
        self.assertFalse((self.state / ('overview-' + first['cache_key'] + '.json')).exists())
        self.cli('__refresh', first['refresh']['token'], code=2, raw=True)

    def test_fresh_preview_warms_cache_but_only_preview_is_actionable(self):
        self.add()
        preview = self.preview()
        overview = self.cli()
        self.assertEqual('current', overview['checkouts'][0]['action'])
        self.assertNotIn('preview_id', overview)
        self.assertIn('preview_id', preview)
        self.assertNotIn('Apply:', self.cli(raw=True))

    def test_apply_invalidates_observations_before_branch_change(self):
        self.add()
        self.preview()
        self.commit(self.up, 'new', 'new')
        preview = self.preview()
        self.assertEqual('fast-forward', self.cli()['checkouts'][0]['action'])
        self.apply(preview)
        overview = self.cli(code=2)
        self.assertEqual('unchecked', overview['checkouts'][0]['action'])
        self.assertNotIn('snapshot', overview['checkouts'][0])
        self.cli('refresh')
        self.assertEqual('current', self.cli()['checkouts'][0]['action'])

    def test_cached_failure_never_becomes_fresh_preview_authority(self):
        self.add()
        self.preview()
        self.up.rename(self.base / 'offline')
        preview = self.cli('preview', code=2)
        self.assertEqual('unavailable', preview['checkouts'][0]['action'])
        self.assertEqual('current', self.cli(code=2)['checkouts'][0]['action'])
        result = self.apply(preview, code=2)
        self.assertEqual('unavailable', result['checkouts'][0]['action'])

    def test_cached_read_during_apply_lock_and_empty_setup_guidance(self):
        import fcntl
        self.assertIn('No clones registered', self.cli(raw=True))
        self.add()
        self.preview()
        with (self.state / 'registry.lock').open('a') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.assertEqual('current', self.cli()['checkouts'][0]['action'])
            self.cli('preview', code=1)

    def test_relative_overrides_survive_worker_directory_change(self):
        import time
        result = subprocess.run([str(ROOT / 'v3'), 'checkouts', 'add', str(self.clone),
                                 '--registry=relative/config.json', '--state-dir=relative/state', '--json'],
                                cwd=self.base, env=self.env, text=True, capture_output=True)
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)
        result = subprocess.run([str(ROOT / 'v3'), 'checkouts', '--registry=relative/config.json',
                                 '--state-dir=relative/state', '--json'], cwd=self.base,
                                env=self.env, text=True, capture_output=True)
        self.assertEqual(2, result.returncode, result.stdout + result.stderr)
        deadline = time.monotonic() + 10
        status_file = self.base / 'relative' / 'state' / 'refresh.json'
        while time.monotonic() < deadline:
            status = json.loads(status_file.read_text())
            if status['state'] not in ['queued', 'running']:
                break
            time.sleep(0.05)
        self.assertEqual('complete', status['state'], status)

    def test_worker_lost_token_cannot_publish_or_overwrite_new_status(self):
        import fcntl
        import time
        self.add()
        marker = self.slow_fetch(self.clone, delay=0.4)
        first = self.cli(code=2, auto_refresh=True)
        deadline = time.monotonic() + 5
        while not marker.exists() and time.monotonic() < deadline:
            time.sleep(0.02)
        self.assertTrue(marker.exists())
        status_path = self.state / 'refresh.json'
        status = json.loads(status_path.read_text())
        status['token'] = 'f' * 24
        status['state'] = 'failed'
        status['reason'] = 'newer status marker'
        status_path.write_text(json.dumps(status))
        with (self.state / 'refresh.lock').open('a') as lock:
            while time.monotonic() < deadline:
                try:
                    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                    break
                except BlockingIOError:
                    time.sleep(0.02)
            else:
                self.fail('Old worker did not exit')
        self.assertFalse((self.state / ('overview-' + first['cache_key'] + '.json')).exists())
        self.assertEqual('newer status marker', json.loads(status_path.read_text())['reason'])
        self.assertEqual([], list(self.state.glob('history-*.json')))

    def test_overview_help_and_documented_refresh_commands(self):
        self.add()
        help_text = self.cli('--help', raw=True)
        self.assertIn('Default is cached', help_text)
        self.assertIn('30s', help_text)
        self.assertIn('--no-refresh', help_text)
        self.cli('refresh', cwd=self.up)
        result = self.cli('--no-refresh', cwd=self.clone)
        self.assertEqual('current', result['checkouts'][0]['action'])
        self.assertNotIn('preview_id', result)
        self.cli('refresh', '--no-refresh', code=1)


if __name__ == '__main__':
    unittest.main()
