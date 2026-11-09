"""Failure boundaries that do not require a live forum; HTTP/GPG flows are in FrmClientTest."""
import argparse
import importlib.util
import io
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('frm', Path(__file__).resolve().parents[1] / 'public/frm.py')
frm = importlib.util.module_from_spec(spec)
spec.loader.exec_module(frm)


class ClientFailureTest(unittest.TestCase):
    def test_unrecognized_discovery_never_selects_legacy(self):
        for response in [(200, '<html>lobby</html>', {}), (503, '{"status":"busy"}', {}),
                         (404, 'not found', {}), (200, 'POST /api/create_signed_post', {})]:
            with patch.object(frm, 'request', return_value=response):
                with self.assertRaises(frm.ClientError):
                    frm.discover('https://forum.example')

    def test_unknown_read_is_not_missing(self):
        for response in [(404, '<html>proxy</html>', {}), (200, 'post not found', {}),
                         (200, 'Post-ID: wrong\n\nhello\n', {})]:
            with patch.object(frm, 'request', return_value=response):
                with self.assertRaises(frm.ClientError):
                    frm.read_post('https://forum.example', 'post-id')

    def test_long_or_invalid_delay_does_not_retry_early(self):
        args = argparse.Namespace(timeout=1, retries=2)
        for delay in ('120', 'nan', 'not-a-date'):
            with patch.object(frm, 'request', return_value=(503, '{"status":"busy","error":"busy"}', {'retry-after': delay})) as send, patch.object(frm.time, 'sleep') as sleep:
                with self.assertRaises(frm.ClientError):
                    frm.api('https://forum.example', '/api/create_signed_post', {}, args)
                self.assertEqual(1, send.call_count)
                sleep.assert_not_called()

    def test_nonbusy_failures_are_never_retried(self):
        args = argparse.Namespace(timeout=1, retries=5)
        for status, text in [(400, '{"status":"error","error":"invalid"}'), (403, '{}'), (404, '{}'),
                             (500, '{}'), (503, '{"status":"error"}'), (200, 'malformed'), (200, '[]')]:
            with patch.object(frm, 'request', return_value=(status, text, {})) as send:
                with self.assertRaises(frm.ClientError):
                    frm.api('https://forum.example', '/api/create_signed_post', {}, args)
                self.assertEqual(1, send.call_count)

    def test_source_coordinates_after_composition_and_newlines(self):
        self.assertEqual(10, frm.source_index('  Cafe\u0301\r\nx→', 'Café\nx→\n', 6))
        self.assertEqual(2, frm.source_index('e\u0301\u200dx', 'é\u200dx\n', 1))

    def test_legacy_unknown_read_preserves_operation(self):
        with tempfile.TemporaryDirectory() as directory:
            operation = Path(directory)
            frm.save_meta(operation / 'key.txt', {'Fingerprint': 'test', 'Home': directory, 'Generated': 'yes'})
            meta = {'Protocol': 'prepared', 'State': 'sending', 'Site': 'https://forum.example', 'Post-ID': 'saved'}
            with patch.object(frm, 'read_post', side_effect=frm.ClientError('unknown')), patch.object(frm, 'api') as send:
                with self.assertRaises(frm.ClientError):
                    frm.resume_post(operation, meta, argparse.Namespace(timeout=1))
                send.assert_not_called()
                self.assertEqual('sending', meta['State'])


class TroubleReportTest(unittest.TestCase):
    SECRETS = ['-----BEGIN PGP PRIVATE KEY BLOCK-----\nlQVYBGabc\n-----END PGP PRIVATE KEY BLOCK-----',
               '/home/agent/.local/state/frm/0e52ffa5bc2361fae2149a02f223cacd/operation-1',
               '~/.gnupg/private-keys-v1.d', '0e52ffa5bc2361fae2149a02f223cacd',
               'BA132E21C90EB7E49A74CB86790ED3E31CC14C8E',
               '-----BEGIN PGP SIGNATURE-----\niHUEABYKAB0']  # unterminated: hides the rest

    def test_report_never_carries_keys_paths_or_tokens(self):
        error = 'GPG failed: ' + ' and '.join(self.SECRETS)
        report = frm.trouble_report('reply', '/api/create_signed_post', error, 'failed')
        for secret in self.SECRETS:
            for fragment in secret.split('\n'):
                self.assertNotIn(fragment, report)
        self.assertIn('Error: GPG failed: [key block]', report)
        self.assertIn('[path]', report)

    def test_report_has_fixed_fields_and_bounded_error(self):
        report = frm.trouble_report('thread', '', 'HTTP 503: ' + 'x' * 900, 'failed', 2)
        names = [line.split(': ', 1)[0] for line in report.split('\n\n', 1)[1].splitlines()]
        self.assertEqual(['Client', 'Command', 'Route', 'Outcome', 'Busy-Retries', 'Error'], names)
        self.assertIn('Route: none', report)
        self.assertIn('Busy-Retries: 2', report)
        error = next(line for line in report.splitlines() if line.startswith('Error: '))
        self.assertLessEqual(len(error) - len('Error: '), frm.REPORT_ERROR_LIMIT)

    def test_api_routes_and_urls_survive_scrubbing(self):
        text = frm.scrub('HTTP 400: Inspect https://forum.example/api/ and /api/create_signed_post')
        self.assertIn('https://forum.example/api/', text)
        self.assertIn('/api/create_signed_post', text)


class TroubleReportPostingTest(unittest.TestCase):
    SITE = 'https://forum.example'
    OK = (200, 'status=ok\npost_id=reply-1\nthread_id=t1\n', {})

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        frm.TRACE.update(route='/api/create_signed_post', busy=0, operation=None, identity='')

    def args(self, **values):
        base = dict(command='reply', resume=None, state_dir=self.directory.name, report_thread='t1',
                    no_report=False, timeout=1)
        base.update(values)
        return argparse.Namespace(**base)

    def operation(self, state):
        operation = Path(self.directory.name) / 'operation-x'
        operation.mkdir()
        frm.save_meta(operation / 'operation.txt', {'Site': self.SITE, 'Kind': 'reply', 'State': state})
        frm.TRACE['operation'] = operation
        return operation

    def test_failure_before_identity_posts_one_unsigned_report_after_the_error(self):
        argv = ['frm.py', '--site', self.SITE, '--state-dir', self.directory.name, 'reply',
                '--thread', 'x', '--body-file', 'b', '--report-thread', 't1']
        with patch.object(sys, 'argv', argv), \
                patch.object(frm, 'write_post', side_effect=frm.ClientError('GPG failed: no agent')), \
                patch.object(frm, 'request', return_value=self.OK) as send, \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            self.assertEqual(2, frm.main())
        self.assertEqual(1, send.call_count)
        fields = send.call_args[0][2]
        self.assertEqual(('t1', 't1', 'plain'), (fields['thread_id'], fields['parent_id'], fields['body_format']))
        self.assertNotIn('author_identity_id', fields)
        self.assertIn('Error: GPG failed: no agent', fields['body'])
        output = err.getvalue()
        self.assertLess(output.index('Error: GPG failed'), output.index('Trouble-report:'))
        self.assertIn('Trouble-report: sent https://forum.example/threads/t1', output)
        self.assertEqual([], list(Path(self.directory.name).rglob('trouble-pending-*')))

    def test_known_identity_is_named_and_rejection_reported_once(self):
        frm.TRACE['identity'] = 'openpgp:abc'
        operation = self.operation('rejected')
        with patch.object(frm, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            frm.report_trouble(self.args(), self.SITE, frm.ClientError('HTTP 400: invalid'))
            frm.report_trouble(self.args(), self.SITE, frm.ClientError('HTTP 400: invalid'))
        self.assertEqual(1, send.call_count)
        self.assertEqual('openpgp:abc', send.call_args[0][2]['author_identity_id'])
        self.assertIn('Outcome: rejected', send.call_args[0][2]['body'])
        self.assertEqual('yes', frm.load_meta(operation / 'operation.txt')['Trouble-Reported'])

    def test_unregistered_identity_falls_back_to_anonymous(self):
        refused = (400, 'error=author_identity_id does not resolve to a known identity.\n', {})
        with patch.object(frm, 'request', side_effect=[refused, self.OK]) as send:
            self.assertEqual('reply-1', frm.post_trouble_report(self.SITE, 't1', 'body', 'openpgp:abc', 1))
        self.assertEqual(2, send.call_count)
        self.assertNotIn('author_identity_id', send.call_args[0][2])

    def test_unsent_report_is_queued_and_sent_by_the_next_write(self):
        self.operation('sending')
        with patch.object(frm, 'request', side_effect=frm.ClientError('Network outcome unknown')), \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            frm.report_trouble(self.args(), self.SITE, frm.ClientError('HTTP 503: busy'))
        self.assertIn('saved for the next write', err.getvalue())
        queued = list(Path(self.directory.name).rglob('trouble-pending-*'))
        self.assertEqual(1, len(queued))
        self.assertIn('Outcome: unresolved', queued[0].read_text())
        frm.TRACE.update(operation=None, busy=0)
        with patch.object(frm, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            frm.report_trouble(self.args(), self.SITE)
        self.assertEqual(1, send.call_count)
        self.assertFalse(queued[0].exists())

    def test_success_after_busy_retries_is_reported(self):
        frm.TRACE['busy'] = 2
        with patch.object(frm, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            frm.report_trouble(self.args(), self.SITE)
        self.assertIn('Outcome: accepted after busy retries', send.call_args[0][2]['body'])

    def test_no_report_and_missing_thread_send_nothing(self):
        with patch.object(frm, 'request') as send, patch('sys.stderr', new_callable=io.StringIO) as err:
            frm.report_trouble(self.args(no_report=True), self.SITE, frm.ClientError('x'))
            frm.report_trouble(self.args(report_thread=''), self.SITE, frm.ClientError('x'))
        send.assert_not_called()
        self.assertIn('no --report-thread', err.getvalue())

    def test_reporting_never_raises(self):
        with patch.object(frm, '_report_trouble', side_effect=RuntimeError('boom')), \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            frm.report_trouble(self.args(), self.SITE, frm.ClientError('x'))
        self.assertIn('Trouble-report: not sent (RuntimeError)', err.getvalue())


if __name__ == '__main__':
    unittest.main()
