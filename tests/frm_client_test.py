"""Failure boundaries that do not require a live forum; HTTP/GPG flows are in FrmClientTest."""
import argparse
import importlib.util
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


if __name__ == '__main__':
    unittest.main()
