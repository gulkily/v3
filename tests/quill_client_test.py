"""Failure boundaries that do not require a live forum; HTTP/GPG flows are in QuillClientTest."""
import argparse
import importlib.util
import io
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('quill', Path(__file__).resolve().parents[1] / 'public/quill.py')
quill = importlib.util.module_from_spec(spec)
spec.loader.exec_module(quill)


def png(width, height):
    import struct, zlib
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data))
    rows = b''.join(b'\x00' + b'\xff\xff\xff' * width for _ in range(height))
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(rows)) + chunk(b'IEND', b''))


def jpeg_header(width, height):
    """Header-only baseline JPEG: SOI, APP0, SOF0 with the size, a start of scan and EOI."""
    app0 = b'\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00'
    sof0 = b'\xff\xc0\x00\x11\x08' + height.to_bytes(2, 'big') + width.to_bytes(2, 'big') + b'\x03' + b'\x01\x11\x00\x02\x11\x01\x03\x11\x01'
    sos = b'\xff\xda\x00\x0c\x03\x01\x00\x02\x11\x03\x11\x00\x3f\x00'
    return b'\xff\xd8' + app0 + sof0 + sos + b'\x00' + b'\xff\xd9'


def png_chunk(kind, data):
    import struct, zlib
    return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data))


def with_text_chunk(data):
    """Insert a tEXt metadata chunk after IHDR."""
    return data[:33] + png_chunk(b'tEXt', b'Comment\x00secret place') + data[33:]


def exif_app1(orientation):
    """APP1 segment with a little-endian EXIF IFD holding one Orientation entry."""
    tiff = b'II*\x00\x08\x00\x00\x00' + b'\x01\x00' + b'\x12\x01\x03\x00\x01\x00\x00\x00' + orientation.to_bytes(2, 'little') + b'\x00\x00' + b'\x00\x00\x00\x00'
    body = b'Exif\x00\x00' + tiff
    return b'\xff\xe1' + (len(body) + 2).to_bytes(2, 'big') + body


def with_app1(jpeg, orientation):
    return jpeg[:2] + exif_app1(orientation) + jpeg[2:]


class ImageAttachmentTest(unittest.TestCase):
    """Without Pillow: already-prepared PNG/JPEG only, metadata removed. With Pillow: like image_prepare.js."""

    def prepare(self, data, use_pillow=False):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'image'
            path.write_bytes(data)
            if use_pillow:
                return quill.prepare_image(path)
            with patch.object(quill, 'pillow', return_value=None):
                return quill.prepare_image(path)

    def test_reference_line_matches_the_browser_format(self):
        import hashlib
        data = png(3, 2)
        line = quill.image_reference(data, 'png', (3, 2), "Tools card (live) it's fine!*")
        self.assertEqual('Image-v1: %s png %d 3 2 Tools%%20card%%20%%28live%%29%%20it%%27s%%20fine%%21%%2A' % (hashlib.sha256(data).hexdigest(), len(data)), line)
        for description in ('x' * 501, 'tab\there'):
            with self.assertRaisesRegex(quill.ClientError, '500 bytes'):
                quill.image_reference(data, 'png', (3, 2), description)

    def test_without_pillow_png_metadata_is_removed_and_pixels_kept(self):
        data = with_text_chunk(png(3, 2))
        prepared, kind, size, note = self.prepare(data)
        self.assertEqual(('png', (3, 2)), (kind, size))
        self.assertNotIn(b'tEXt', prepared)
        self.assertNotIn(b'secret place', prepared)
        self.assertEqual(png(3, 2), prepared)
        self.assertIn('Pillow not installed', note)

    def test_without_pillow_jpeg_exif_is_removed_and_rotated_photos_refused(self):
        jpeg = jpeg_header(640, 480)
        prepared, kind, size, _ = self.prepare(with_app1(jpeg, 1))
        self.assertEqual(('jpg', (640, 480), jpeg), (kind, size, prepared))
        with self.assertRaisesRegex(quill.ClientError, 'stored rotated'):
            self.prepare(with_app1(jpeg, 6))

    def test_without_pillow_limits_name_pillow_as_the_fix(self):
        for data, message in [(b'GIF89a' + b'\x00' * 20, 'PNG or JPEG only'), (png(2049, 1), '2048 pixels per side \\(this one is 2049 x 1\\)'),
                              (b'\xff\xd8\xff\xe0\x00\x02', 'readable JPEG'), (b'', '32 MiB')]:
            with self.assertRaisesRegex(quill.ClientError, message):
                self.prepare(data)
        with patch.object(quill, 'IMAGE_MAX_BYTES', 10), self.assertRaisesRegex(quill.ClientError, '5 MiB'):
            self.prepare(png(2, 2))


@unittest.skipUnless(quill.pillow(), 'Pillow not installed')
class PillowImagePrepareTest(unittest.TestCase):
    def prepare(self, data):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'image'
            path.write_bytes(data)
            return quill.prepare_image(path)

    def encode(self, image, fmt, **options):
        import io
        out = io.BytesIO()
        image.save(out, fmt, **options)
        return out.getvalue()

    def test_large_png_is_resized_to_2048_and_stays_png(self):
        from PIL import Image
        prepared, kind, size, note = self.prepare(self.encode(Image.new('RGB', (3000, 1000), 'white'), 'PNG'))
        self.assertEqual(('png', (2048, 682)), (kind, size))
        self.assertEqual((2048, 682), Image.open(__import__('io').BytesIO(prepared)).size)
        self.assertIn('Pillow', note)

    def test_rotated_jpeg_is_turned_upright_and_loses_exif(self):
        from PIL import Image
        exif = Image.Exif()
        exif[0x0112] = 6
        prepared, kind, size, _ = self.prepare(self.encode(Image.new('RGB', (400, 300), 'red'), 'JPEG', exif=exif.tobytes()))
        self.assertEqual(('jpg', (300, 400)), (kind, size))
        self.assertNotIn(b'Exif', prepared)

    def test_other_formats_become_png_and_alpha_survives(self):
        from PIL import Image
        prepared, kind, size, _ = self.prepare(self.encode(Image.new('RGBA', (20, 10), (0, 0, 0, 0)), 'WEBP', lossless=True))
        self.assertEqual(('png', (20, 10)), (kind, size))
        self.assertEqual('RGBA', Image.open(__import__('io').BytesIO(prepared)).mode)

    def test_shrinks_by_steps_until_under_the_byte_limit(self):
        from PIL import Image
        import random
        noise = Image.frombytes('RGB', (300, 300), random.Random(1).randbytes(300 * 300 * 3))
        with patch.object(quill, 'IMAGE_MAX_BYTES', 120000):
            prepared, kind, size, _ = self.prepare(self.encode(noise, 'PNG'))
        self.assertLessEqual(len(prepared), 120000)
        self.assertLess(size[0], 300)
        self.assertIn(size[0], [int(300 * 0.8 ** step) for step in range(1, 8)])


class ImageRequestTest(unittest.TestCase):
    def test_multipart_sends_fields_from_files_and_the_image(self):
        seen = {}
        def fake_run(command, input=None, stdout=None, stderr=None):
            seen['command'] = command
            seen['parts'] = {}
            for index, item in enumerate(command):
                if item == '--form':
                    name, value = command[index + 1].split('=', 1)
                    seen['parts'][name] = Path(value[1:]).read_text() if value.startswith('<') else value
            Path(command[command.index('--output') + 1]).write_text('{"status":"ok","post_id":"thread-1"}')
            Path(command[command.index('--dump-header') + 1]).write_text('')
            return argparse.Namespace(returncode=0, stdout=b'200', stderr=b'')
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'):
            quill.request('https://forum.example', '/api/create_prepared_post', {'canonical_record': '@not-a-file\n<nor this'}, 5, {'image': ('/tmp/x.png', 'image/png')})
        self.assertNotIn('--data-binary', seen['command'])
        self.assertEqual('@not-a-file\n<nor this', seen['parts']['canonical_record'])
        self.assertEqual('@/tmp/x.png;type=image/png', seen['parts']['image'])


class ClientFailureTest(unittest.TestCase):
    def test_unrecognized_discovery_never_selects_legacy(self):
        for response in [(200, '<html>lobby</html>', {}), (503, '{"status":"busy"}', {}),
                         (404, 'not found', {}), (200, 'POST /api/create_signed_post', {})]:
            with patch.object(quill, 'request', return_value=response):
                with self.assertRaises(quill.ClientError):
                    quill.discover('https://forum.example')

    def test_unknown_read_is_not_missing(self):
        for response in [(404, '<html>proxy</html>', {}), (200, 'post not found', {}),
                         (200, 'Post-ID: wrong\n\nhello\n', {})]:
            with patch.object(quill, 'request', return_value=response):
                with self.assertRaises(quill.ClientError):
                    quill.read_post('https://forum.example', 'post-id')

    def test_long_or_invalid_delay_does_not_retry_early(self):
        args = argparse.Namespace(timeout=1, retries=2)
        for delay in ('120', 'nan', 'not-a-date'):
            with patch.object(quill, 'request', return_value=(503, '{"status":"busy","error":"busy"}', {'retry-after': delay})) as send, patch.object(quill.time, 'sleep') as sleep:
                with self.assertRaises(quill.ClientError):
                    quill.api('https://forum.example', '/api/create_signed_post', {}, args)
                self.assertEqual(1, send.call_count)
                sleep.assert_not_called()

    def test_nonbusy_failures_are_never_retried(self):
        args = argparse.Namespace(timeout=1, retries=5)
        for status, text in [(400, '{"status":"error","error":"invalid"}'), (403, '{}'), (404, '{}'),
                             (500, '{}'), (503, '{"status":"error"}'), (200, 'malformed'), (200, '[]')]:
            with patch.object(quill, 'request', return_value=(status, text, {})) as send:
                with self.assertRaises(quill.ClientError):
                    quill.api('https://forum.example', '/api/create_signed_post', {}, args)
                self.assertEqual(1, send.call_count)

    def test_source_coordinates_after_composition_and_newlines(self):
        self.assertEqual(10, quill.source_index('  Cafe\u0301\r\nx→', 'Café\nx→\n', 6))
        self.assertEqual(2, quill.source_index('e\u0301\u200dx', 'é\u200dx\n', 1))

    def test_legacy_unknown_read_preserves_operation(self):
        with tempfile.TemporaryDirectory() as directory:
            operation = Path(directory)
            quill.save_meta(operation / 'key.txt', {'Fingerprint': 'test', 'Home': directory, 'Generated': 'yes'})
            meta = {'Protocol': 'prepared', 'State': 'sending', 'Site': 'https://forum.example', 'Post-ID': 'saved'}
            with patch.object(quill, 'read_post', side_effect=quill.ClientError('unknown')), patch.object(quill, 'api') as send:
                with self.assertRaises(quill.ClientError):
                    quill.resume_post(operation, meta, argparse.Namespace(timeout=1))
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
        report = quill.trouble_report('reply', '/api/create_signed_post', error, 'failed')
        for secret in self.SECRETS:
            for fragment in secret.split('\n'):
                self.assertNotIn(fragment, report)
        self.assertIn('Error: GPG failed: [key block]', report)
        self.assertIn('[path]', report)

    def test_report_has_fixed_fields_and_bounded_error(self):
        report = quill.trouble_report('thread', '', 'HTTP 503: ' + 'x' * 900, 'failed', 2)
        names = [line.split(': ', 1)[0] for line in report.split('\n\n', 1)[1].splitlines()]
        self.assertEqual(['Client', 'Command', 'Route', 'Outcome', 'Busy-Retries', 'Error'], names)
        self.assertIn('Route: none', report)
        self.assertIn('Busy-Retries: 2', report)
        error = next(line for line in report.splitlines() if line.startswith('Error: '))
        self.assertLessEqual(len(error) - len('Error: '), quill.REPORT_ERROR_LIMIT)

    def test_api_routes_and_urls_survive_scrubbing(self):
        text = quill.scrub('HTTP 400: Inspect https://forum.example/api/ and /api/create_signed_post')
        self.assertIn('https://forum.example/api/', text)
        self.assertIn('/api/create_signed_post', text)


class TroubleReportPostingTest(unittest.TestCase):
    SITE = 'https://forum.example'
    OK = (200, 'status=ok\npost_id=reply-1\nthread_id=t1\n', {})

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        quill.TRACE.update(route='/api/create_signed_post', busy=0, operation=None, identity='')

    def args(self, **values):
        base = dict(command='reply', resume=None, state_dir=self.directory.name, report_thread='t1',
                    no_report=False, timeout=1)
        base.update(values)
        return argparse.Namespace(**base)

    def operation(self, state):
        operation = Path(self.directory.name) / 'operation-x'
        operation.mkdir()
        quill.save_meta(operation / 'operation.txt', {'Site': self.SITE, 'Kind': 'reply', 'State': state})
        quill.TRACE['operation'] = operation
        return operation

    def test_failure_before_identity_posts_one_unsigned_report_after_the_error(self):
        argv = ['quill.py', '--site', self.SITE, '--state-dir', self.directory.name, 'reply',
                '--thread', 'x', '--body-file', 'b', '--report-thread', 't1']
        with patch.object(sys, 'argv', argv), \
                patch.object(quill, 'write_post', side_effect=quill.ClientError('GPG failed: no agent')), \
                patch.object(quill, 'request', return_value=self.OK) as send, \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            self.assertEqual(2, quill.main())
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
        quill.TRACE['identity'] = 'openpgp:abc'
        operation = self.operation('rejected')
        with patch.object(quill, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            quill.report_trouble(self.args(), self.SITE, quill.ClientError('HTTP 400: invalid'))
            quill.report_trouble(self.args(), self.SITE, quill.ClientError('HTTP 400: invalid'))
        self.assertEqual(1, send.call_count)
        self.assertEqual('openpgp:abc', send.call_args[0][2]['author_identity_id'])
        self.assertIn('Outcome: rejected', send.call_args[0][2]['body'])
        self.assertEqual('yes', quill.load_meta(operation / 'operation.txt')['Trouble-Reported'])

    def test_screenshot_case_prepare_identity_error_is_readable_and_reported(self):
        """The reported zm.py crash: prepare_identity answered without prepare_token (KeyError)."""
        refused = (400, '{"status":"error","error":"Unable to inspect OpenPGP public key. gpg said: no valid OpenPGP data found."}', {})
        with patch.object(quill, 'request', return_value=refused):
            with self.assertRaises(quill.ClientError) as caught:
                quill.api(self.SITE, '/api/prepare_identity', {'public_key': ''}, argparse.Namespace(timeout=1, retries=0))
        self.assertIn('Unable to inspect OpenPGP public key', str(caught.exception))
        quill.TRACE['route'] = '/api/prepare_identity'
        with patch.object(quill, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            quill.report_trouble(self.args(), self.SITE, caught.exception)
        body = send.call_args[0][2]['body']
        self.assertIn('Route: /api/prepare_identity', body)
        self.assertIn('Error: HTTP 400: Unable to inspect OpenPGP public key', body)

    def test_unregistered_identity_falls_back_to_anonymous(self):
        refused = (400, 'error=author_identity_id does not resolve to a known identity.\n', {})
        with patch.object(quill, 'request', side_effect=[refused, self.OK]) as send:
            self.assertEqual('reply-1', quill.post_trouble_report(self.SITE, 't1', 'body', 'openpgp:abc', 1))
        self.assertEqual(2, send.call_count)
        self.assertNotIn('author_identity_id', send.call_args[0][2])

    def test_unsent_report_is_queued_and_sent_by_the_next_write(self):
        self.operation('sending')
        with patch.object(quill, 'request', side_effect=quill.ClientError('Network outcome unknown')), \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            quill.report_trouble(self.args(), self.SITE, quill.ClientError('HTTP 503: busy'))
        self.assertIn('saved for the next write', err.getvalue())
        queued = list(Path(self.directory.name).rglob('trouble-pending-*'))
        self.assertEqual(1, len(queued))
        self.assertIn('Outcome: unresolved', queued[0].read_text())
        quill.TRACE.update(operation=None, busy=0)
        with patch.object(quill, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            quill.report_trouble(self.args(), self.SITE)
        self.assertEqual(1, send.call_count)
        self.assertFalse(queued[0].exists())

    def test_success_after_busy_retries_is_reported(self):
        quill.TRACE['busy'] = 2
        with patch.object(quill, 'request', return_value=self.OK) as send, patch('sys.stderr', new_callable=io.StringIO):
            quill.report_trouble(self.args(), self.SITE)
        self.assertIn('Outcome: accepted after busy retries', send.call_args[0][2]['body'])

    def test_no_report_and_missing_thread_send_nothing(self):
        with patch.object(quill, 'request') as send, patch('sys.stderr', new_callable=io.StringIO) as err:
            quill.report_trouble(self.args(no_report=True), self.SITE, quill.ClientError('x'))
            quill.report_trouble(self.args(report_thread=''), self.SITE, quill.ClientError('x'))
        send.assert_not_called()
        self.assertIn('no --report-thread', err.getvalue())

    def test_reporting_never_raises(self):
        with patch.object(quill, '_report_trouble', side_effect=RuntimeError('boom')), \
                patch('sys.stderr', new_callable=io.StringIO) as err:
            quill.report_trouble(self.args(), self.SITE, quill.ClientError('x'))
        self.assertIn('Trouble-report: not sent (RuntimeError)', err.getvalue())


class VersionAndCompatibilityTest(unittest.TestCase):
    """Quill reports its version, prints the server's update notice once, and keeps frm.py's settings."""

    def fake_curl(self, headers):
        seen = []

        def fake_run(command, input=None, stdout=None, stderr=None):
            seen.append(command)
            Path(command[command.index('--output') + 1]).write_text('ok\n')
            Path(command[command.index('--dump-header') + 1]).write_text('HTTP/1.1 200 OK\r\n' + headers)
            return argparse.Namespace(returncode=0, stdout=b'200', stderr=b'')
        return seen, fake_run

    def test_every_request_names_its_version(self):
        seen, fake_run = self.fake_curl('')
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'):
            quill.request('https://forum.example', '/api/')
            quill.request('https://forum.example', '/api/create_reply', {'body': 'x'})
        for command in seen:
            self.assertEqual('quill/' + quill.QUILL_VERSION, command[command.index('--user-agent') + 1])

    def test_json_body_and_extra_headers(self):
        seen, fake_run = self.fake_curl('')
        sent = []

        def capture(command, input=None, stdout=None, stderr=None):
            sent.append(input)
            return fake_run(command, input, stdout, stderr)
        with patch.object(quill.subprocess, 'run', side_effect=capture), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'):
            quill.request('https://forum.example', '/api/inbox/catch_up', json_body={'section': 'replies', 'post_ids': ['a']},
                          extra_headers=('X-Requested-With: ForumInbox',), cookie='v3_session=abc')
        command = seen[0]
        self.assertIn('Content-Type: application/json', command)
        self.assertIn('X-Requested-With: ForumInbox', command)
        self.assertNotIn('Content-Type: application/x-www-form-urlencoded', command)
        self.assertEqual({'section': 'replies', 'post_ids': ['a']}, quill.json.loads(sent[0]))
        with self.assertRaises(quill.ClientError):
            quill.request('https://forum.example', '/api/', extra_headers=('Bad: a\nb',))

    def test_checkin_and_caught_up_parse(self):
        args = quill.parser().parse_args(['--site', 'https://forum.example', 'checkin', '--json'])
        self.assertEqual(('checkin', True), (args.command, args.json))
        args = quill.parser().parse_args(['--site', 'https://forum.example', 'caught-up', 'p1', 'p2'])
        self.assertEqual(('caught-up', 'replies', ['p1', 'p2']), (args.command, args.section, args.post_ids))
        with self.assertRaises(SystemExit), patch('sys.stderr', new_callable=io.StringIO):
            quill.parser().parse_args(['--site', 'https://forum.example', 'caught-up'])

    def test_update_notice_is_printed_once(self):
        notice = 'You are running Quill 2000-01-01; this site serves 2026-11-16. Fetch it from /quill.py.'
        seen, fake_run = self.fake_curl('X-Quill-Update: ' + notice + '\r\n')
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'), \
                patch.object(quill, 'NOTICES', set()), patch('sys.stderr', new_callable=io.StringIO) as err:
            quill.request('https://forum.example', '/api/')
            quill.request('https://forum.example', '/api/list_index')
        self.assertEqual('Update: ' + notice + '\n', err.getvalue())

    def test_no_notice_without_the_header(self):
        seen, fake_run = self.fake_curl('Content-Type: text/plain\r\n')
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'), \
                patch.object(quill, 'NOTICES', set()), patch('sys.stderr', new_callable=io.StringIO) as err:
            quill.request('https://forum.example', '/api/')
        self.assertEqual('', err.getvalue())

    def test_old_state_directory_is_kept(self):
        with tempfile.TemporaryDirectory() as home:
            with patch.object(quill.Path, 'home', return_value=Path(home)), patch('sys.stderr', new_callable=io.StringIO) as err:
                self.assertEqual(str(Path(home) / '.local/state/quill'), quill.default_state_dir())
                (Path(home) / '.local/state/frm').mkdir(parents=True)
                self.assertEqual(str(Path(home) / '.local/state/frm'), quill.default_state_dir())
                self.assertIn('kept from frm.py', err.getvalue())
                (Path(home) / '.local/state/quill').mkdir()
                self.assertEqual(str(Path(home) / '.local/state/quill'), quill.default_state_dir())

    def test_explicit_state_directory_wins(self):
        args = argparse.Namespace(state_dir='/tmp/quill-explicit', resume=None)
        self.assertEqual(Path('/tmp/quill-explicit').resolve(), quill.state_directory(args, 'https://forum.example').parent)

    def test_settings_prefer_quill_then_frm(self):
        with patch.dict(quill.os.environ, {'FRM_REPORT_THREAD': 'old'}, clear=True):
            self.assertEqual('old', quill.setting('REPORT_THREAD'))
        with patch.dict(quill.os.environ, {'FRM_REPORT_THREAD': 'old', 'QUILL_REPORT_THREAD': 'new'}, clear=True):
            self.assertEqual('new', quill.setting('REPORT_THREAD'))
        with patch.dict(quill.os.environ, {}, clear=True):
            self.assertEqual('', quill.setting('REPORT_THREAD'))

    def test_printed_commands_use_the_saved_name(self):
        meta = {'Site': 'https://forum.example', 'Kind': 'reply'}
        with patch.object(quill.sys, 'argv', ['/tmp/frm.py']):
            self.assertTrue(quill.recovery_command(Path('/tmp/op'), meta).startswith('python3 frm.py --site '))
        with patch.object(quill.sys, 'argv', ['/tmp/quill.py']):
            self.assertTrue(quill.recovery_command(Path('/tmp/op'), meta).startswith('python3 quill.py --site '))
        with patch.object(quill.sys, 'argv', ['-c']):
            self.assertTrue(quill.recovery_command(Path('/tmp/op'), meta).startswith('python3 quill.py --site '))


class SignInTest(unittest.TestCase):
    """sign_in keeps the session cookie in memory and sends it only when asked; nothing on disk."""

    def fake_server(self, responses):
        seen = []

        def fake_run(command, input=None, stdout=None, stderr=None):
            url = command[-1]
            route = url.split('://', 1)[1].split('/', 1)[1].split('?', 1)[0]
            status, body, headers = responses['/' + route]
            seen.append(command)
            Path(command[command.index('--output') + 1]).write_text(body)
            Path(command[command.index('--dump-header') + 1]).write_text('HTTP/1.1 %d X\r\n%s' % (status, headers))
            return argparse.Namespace(returncode=0, stdout=str(status).encode(), stderr=b'')
        return seen, fake_run

    def sign_in(self, responses):
        seen, fake_run = self.fake_server(responses)

        def fake_sign(folder, key, source, target):
            (folder / (target + '.txt')).write_text('SIGNATURE')
        key = {'Fingerprint': 'ABCDEF', 'Home': '/nowhere', 'Generated': 'no'}
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'), \
                patch.object(quill, 'sign', side_effect=fake_sign):
            return seen, quill.sign_in('https://forum.example', key)

    def test_session_cookie_is_picked_from_several(self):
        seen, session = self.sign_in({
            '/api/auth_challenge': (200, 'challenge=abc123\n', ''),
            '/api/authenticate_identity': (200, 'status=ok\nidentity_id=openpgp:abcdef\napproved=1\n',
                                           'Set-Cookie: theme-hint=gloss; Path=/\r\nSet-Cookie: __Host-v3_session=s3cret; Path=/; Secure; HttpOnly\r\n'),
        })
        self.assertEqual('__Host-v3_session=s3cret', session['cookie'])
        self.assertEqual('openpgp:abcdef', session['identity_id'])
        self.assertTrue(session['approved'])
        self.assertNotIn('--cookie-jar', seen[1])

    def test_cookie_is_sent_only_when_given(self):
        seen, fake_run = self.fake_server({'/api/auth_status': (200, 'status=authenticated\n', '')})
        with patch.object(quill.subprocess, 'run', side_effect=fake_run), patch.object(quill.shutil, 'which', return_value='/usr/bin/curl'):
            quill.request('https://forum.example', '/api/auth_status', cookie='v3_session=abc')
            quill.request('https://forum.example', '/api/auth_status')
        self.assertIn('Cookie: v3_session=abc', seen[0])
        self.assertFalse(any(part.startswith('Cookie:') for part in seen[1]))

    def test_rejected_signature_names_the_server_error(self):
        with self.assertRaisesRegex(quill.ClientError, 'Identity signature verification failed'):
            self.sign_in({
                '/api/auth_challenge': (200, 'challenge=abc123\n', ''),
                '/api/authenticate_identity': (403, 'error=Identity signature verification failed.\n', ''),
            })

    def test_no_session_cookie_is_an_error(self):
        with self.assertRaisesRegex(quill.ClientError, 'no session cookie'):
            self.sign_in({
                '/api/auth_challenge': (200, 'challenge=abc123\n', ''),
                '/api/authenticate_identity': (200, 'status=ok\napproved=1\n', 'Set-Cookie: theme-hint=gloss\r\n'),
            })

    def test_saved_key_never_generates_one(self):
        with tempfile.TemporaryDirectory() as directory:
            args = argparse.Namespace(state_dir=directory, resume=None, signing_key=None, gpg_home=None)
            with self.assertRaisesRegex(quill.ClientError, 'No key for this site yet'):
                quill.saved_key(args, 'https://forum.example')


if __name__ == '__main__':
    unittest.main()
