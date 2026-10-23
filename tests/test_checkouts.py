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

    def git(self, directory, *args, check=True):
        return subprocess.run(['git', '-C', str(directory), *args], env=self.env,
                              text=True, capture_output=True, check=check).stdout.strip()

    def commit(self, directory, name, text):
        (directory / name).write_text(text)
        self.git(directory, 'add', '--', name)
        self.git(directory, 'commit', '-qm', text)
        return self.git(directory, 'rev-parse', 'HEAD')

    def cli(self, *args, code=0, raw=False, cwd=None):
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
        self.assertIn('Already current', output)
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


if __name__ == '__main__':
    unittest.main()
