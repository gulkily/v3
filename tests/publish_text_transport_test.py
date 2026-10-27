"""HTTP contract and failure checks for all standalone text publishers."""
import http.server
import json
import os
from pathlib import Path
import subprocess
import tempfile
import threading
import unittest
from urllib.parse import parse_qs

ROOT = Path(__file__).resolve().parents[1]
CLIENTS = [('python3', 'py'), ('php', 'php'), ('perl', 'pl')]


class PublishTextTransportTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.directory = Path(cls.temp.name)
        cls.home = cls.directory / 'gnupg'
        cls.home.mkdir(mode=0o700)
        cls.env = dict(os.environ, GNUPGHOME=str(cls.home))
        subprocess.run(['gpg', '--batch', '--pinentry-mode', 'loopback', '--passphrase', '',
                        '--quick-generate-key', 'transport-agent', 'ed25519', 'sign', '0'],
                       env=cls.env, check=True, capture_output=True)
        keys = subprocess.check_output(['gpg', '--with-colons', '--list-secret-keys'], env=cls.env).decode()
        cls.fingerprint = next(line.split(':')[9] for line in keys.splitlines() if line.startswith('fpr:'))
        cls.file = cls.directory / 'body.txt'
        cls.body = 'Café & + =\r\n\nFinal line\n'
        cls.file.write_bytes(cls.body.encode())

    @classmethod
    def tearDownClass(cls):
        subprocess.run(['gpgconf', '--kill', 'gpg-agent'], env=cls.env, capture_output=True)
        cls.temp.cleanup()

    def setUp(self):
        self.requests = []
        self.respond = lambda path, fields: (200, b'status=ok\npost_id=test-post\nthread_id=root\n')
        owner = self

        class Handler(http.server.BaseHTTPRequestHandler):
            def do_POST(self):
                fields = parse_qs(self.rfile.read(int(self.headers['Content-Length'])).decode(), keep_blank_values=True)
                owner.requests.append((self.path, fields))
                status, body = owner.respond(self.path, fields)
                self.send_response(status)
                if status == 302:
                    self.send_header('Location', '/redirected')
                self.end_headers()
                self.wfile.write(body)

            def log_message(self, *args):
                pass

        self.server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f'http://127.0.0.1:{self.server.server_port}/forum'

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()

    def run_client(self, client, *options):
        interpreter, extension = client
        return subprocess.run([interpreter, str(ROOT / f'scripts/publish_text.{extension}'),
                               self.base, str(self.file), *options], env=self.env,
                              capture_output=True, text=True, timeout=30)

    def test_anonymous_thread_and_reply(self):
        for client in CLIENTS:
            with self.subTest(client=client):
                result = self.run_client(client, '--subject', 'A & B', '--board-tags', 'general,test')
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn('/forum/posts/test-post', result.stdout)
                self.assertEqual(self.requests[-1], ('/forum/api/create_thread', {
                    'body': [self.body], 'subject': ['A & B'], 'board_tags': ['general,test']}))
                result = self.run_client(client, '--thread-id', 'root')
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(self.requests[-1][0], '/forum/api/create_reply')
                self.assertEqual(self.requests[-1][1]['parent_id'], ['root'])

    def test_failure_responses_do_not_retry(self):
        for status, body in [(400, b'error=Rejected body\n'), (400, b'{"status":"error","error":"Rejected key"}'),
                             (200, b'<html>Members only</html>'), (302, b'')]:
            self.respond = lambda path, fields: (status, body)
            for client in CLIENTS:
                with self.subTest(client=client, status=status, body=body):
                    before = len(self.requests)
                    result = self.run_client(client)
                    self.assertEqual(result.returncode, 1, result.stderr)
                    self.assertEqual(len(self.requests), before + 1)
                    self.assertIn('before retrying', result.stderr)

    def test_canonical_bytes_and_recovery_id(self):
        canonical = 'Post-ID: test-post\r\nAuthor: café\r\n\r\nBody & + =\n\n'
        prepared = dict(status='ok', prepare_token='token', canonical_record=canonical,
                        record_path='records/test-post.txt', post_id='test-post')

        def respond(path, fields):
            if path.endswith('/prepare_thread'):
                return 200, json.dumps(prepared).encode()
            return 500, b'{"status":"error","error":"Lost final response"}'

        self.respond = respond
        for client in CLIENTS:
            with self.subTest(client=client):
                before = len(self.requests)
                result = self.run_client(client, '--signing-key', self.fingerprint)
                self.assertEqual(result.returncode, 1, result.stderr)
                self.assertEqual(len(self.requests), before + 2)
                self.assertIn('Post-ID: test-post', result.stderr)
                path, fields = self.requests[-1]
                self.assertEqual(path, '/forum/api/create_prepared_post')
                self.assertEqual(fields['canonical_record'], [canonical])
                self.assertEqual(fields['author_identity_id'], ['openpgp:' + self.fingerprint.lower()])
                record = self.directory / 'record.txt'
                signature = self.directory / 'record.asc'
                record.write_bytes(canonical.encode())
                signature.write_bytes(fields['detached_signature'][0].encode())
                verified = subprocess.run(['gpg', '--verify', str(signature), str(record)],
                                          env=self.env, capture_output=True)
                self.assertEqual(verified.returncode, 0, verified.stderr)

    def test_invalid_options_do_not_send_requests(self):
        for client in CLIENTS:
            for options in [('--parent-id', 'x'), ('--signing-key', 'bad'),
                            ('--generate-key', ' '), ('--generate-key', 'test', '--signing-key', self.fingerprint),
                            ('--thread-id', 'root', '--subject', 'bad')]:
                with self.subTest(client=client, options=options):
                    self.assertEqual(self.run_client(client, *options).returncode, 2)
        self.assertEqual(self.requests, [])

    def test_signing_failure_never_publishes_anonymously(self):
        prepared = dict(status='ok', prepare_token='token', canonical_record='Canonical record\n',
                        record_path='records/test-post.txt', post_id='test-post')
        self.respond = lambda path, fields: (200, json.dumps(prepared).encode())
        for client in CLIENTS:
            with self.subTest(client=client):
                before = len(self.requests)
                result = self.run_client(client, '--signing-key', 'A' * 40)
                self.assertEqual(result.returncode, 1, result.stderr)
                self.assertEqual(len(self.requests), before + 1)
                self.assertEqual(self.requests[-1][0], '/forum/api/prepare_thread')
                self.assertIn('Post-ID: test-post', result.stderr)

    def test_invalid_file_does_not_generate_keys_or_send_requests(self):
        try:
            for body in (b'\xff', b' \r\n\t'):
                self.file.write_bytes(body)
                for client in CLIENTS:
                    with self.subTest(client=client, body=body):
                        result = self.run_client(client, '--generate-key', 'must-not-be-created')
                        self.assertEqual(result.returncode, 2, result.stderr)
                        self.assertNotIn('Signing-key:', result.stdout)
        finally:
            self.file.write_bytes(self.body.encode())
        self.assertEqual(self.requests, [])


if __name__ == '__main__':
    unittest.main()
