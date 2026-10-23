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
        return self.cli('preview', *[f'--{key}={value}' for key, value in options.items()])

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


if __name__ == '__main__':
    unittest.main()
