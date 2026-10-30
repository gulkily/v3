#!/usr/bin/env python3
"""Portable forum client. Python 3 standard library, curl and GPG; no packages."""
FRM_VERSION = "2026-10-30"

import argparse
import datetime
import hashlib
import secrets
import shlex
import unicodedata
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
from urllib.parse import urlencode, urlsplit


class ClientError(Exception):
    pass


def site_url(value):
    url = urlsplit(value)
    if (url.scheme not in ('http', 'https') or not url.hostname or url.username or url.password
            or url.query or url.fragment or any(c.isspace() for c in value)):
        raise ClientError('Use --site with an http(s) instance URL without credentials, query or fragment.')
    return value.rstrip('/')


def request(site, route, fields=None, timeout=60):
    """One request. Never forward authored data via redirects. No curl config/cookies."""
    if not shutil.which('curl'):
        raise ClientError('curl is required. Install it using your operating system tools.')
    with tempfile.TemporaryDirectory(prefix='frm-http-') as directory:
        body = Path(directory) / 'response'
        headers = Path(directory) / 'headers'
        command = ['curl', '--disable', '--silent', '--show-error', '--max-time', str(timeout),
                   '--proto', '=http,https', '--output', str(body), '--dump-header', str(headers),
                   '--write-out', '%{http_code}']
        data = None
        if fields is not None:
            command += ['--data-binary', '@-', '--header', 'Content-Type: application/x-www-form-urlencoded']
            data = urlencode(fields).encode('utf-8')
        result = subprocess.run(command + [site + route], input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if result.returncode:
            raise ClientError('Network outcome unknown: ' + result.stderr.decode('utf-8', 'replace').strip())
        try:
            status = int(result.stdout)
            text = body.read_bytes().decode('utf-8')
        except (ValueError, UnicodeError):
            raise ClientError('Malformed response; keep any saved operation and check the instance URL.')
        values = {}
        for line in headers.read_text(encoding='iso-8859-1').splitlines():
            if ':' in line:
                key, value = line.split(':', 1)
                values[key.lower()] = value.strip()
        return status, text, values


def response_error(status, text):
    try:
        payload = json.loads(text)
        message = payload.get('error') if isinstance(payload, dict) else None
    except ValueError:
        message = None
    return ClientError('HTTP %s: %s' % (status, message or 'Unexpected response. Check the instance URL, access policy and /api/.'))


def read_post(site, post_id, timeout=60):
    status, text, headers = request(site, '/api/get_post?' + urlencode({'post_id': post_id}), timeout=timeout)
    if status == 404 and text.strip() == 'post not found':
        return None
    if status != 200:
        raise response_error(status, text)
    if '\n\n' not in text or ('Post-ID: ' + post_id) not in text.split('\n\n', 1)[0].splitlines():
        raise ClientError('Unrecognized post response; publication status is unknown.')
    return text


def discover(site, timeout=60):
    status, text, _ = request(site, '/api/', timeout=timeout)
    if status != 200:
        raise response_error(status, text)
    if re.search(r'^POST /api/create_signed_post \[signed, json\]', text, re.M):
        return 'signed'
    if all(re.search(r'^POST /api/' + route + r' \[signed, json\]', text, re.M)
           for route in ('prepare_identity', 'create_identity', 'prepare_thread', 'prepare_reply', 'create_prepared_post')):
        return 'prepared'
    raise ClientError('Cannot identify a supported posting API. Inspect SITE/api/ before publishing; no write was sent.')


def header(value):
    if any(ord(c) < 32 or ord(c) == 127 for c in value):
        raise ClientError('Header values cannot contain control characters.')
    return value


def save_meta(path, values):
    data = ''.join(header(str(k)) + ': ' + header(str(v)) + '\n' for k, v in values.items())
    temporary = path.with_suffix('.tmp')
    with temporary.open('w', encoding='utf-8', newline='\n') as stream:
        stream.write(data)
        stream.flush()
        os.fsync(stream.fileno())
    temporary.replace(path)


def load_meta(path):
    return dict(line.split(': ', 1) for line in path.read_text(encoding='utf-8').splitlines())


def gpg_run(home, arguments):
    if not shutil.which('gpg'):
        raise ClientError('GPG is required for writing. Install it using your operating system tools.')
    command = ['gpg'] + (['--homedir', home] if home else [])
    result = subprocess.run(command + arguments, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode:
        raise ClientError('GPG failed. Check the key and unlock existing keys with GPG/pinentry: ' + result.stderr.decode('utf-8', 'replace').strip())
    return result.stdout


def identity(args, directory):
    file = directory / 'identity.txt'
    if file.exists() and not args.signing_key:
        return load_meta(file)
    home = str(Path(args.gpg_home).expanduser().resolve()) if args.gpg_home else os.environ.get('GNUPGHOME', str(Path.home() / '.gnupg'))
    if args.signing_key:
        selector = header(args.signing_key)
        generated = 'no'
    else:
        home = str(directory / 'keyring')
        Path(home).mkdir(mode=0o700, exist_ok=True)
        # A crash after generation must not cause a second key to be generated.
        existing = gpg_run(home, ['--batch', '--with-colons', '--list-secret-keys'])
        if b'sec:' not in existing:
            gpg_run(home, ['--batch', '--pinentry-mode', 'loopback', '--passphrase', '',
                           '--quick-generate-key', header(args.name), 'ed25519', 'sign', '0'])
        selector = ''
        generated = 'yes'
    listing = gpg_run(home, ['--batch', '--with-colons', '--list-secret-keys', '--'] + ([selector] if selector else [])).decode()
    fingerprints = []; primary = False
    for line in listing.splitlines():
        row = line.split(':')
        if row[0] in ('sec', 'ssb'):
            primary = row[0] == 'sec'
        elif row[0] == 'fpr' and primary:
            fingerprints.append(row[9]); primary = False
    if len(fingerprints) != 1:
        raise ClientError('Select exactly one primary secret key with --signing-key FINGERPRINT.')
    result = {'Fingerprint': fingerprints[0], 'Home': home, 'Generated': generated}
    save_meta(file, result)
    return result


def sign(operation, key, source, target):
    arguments = ['--batch', '--yes']
    if key['Generated'] == 'yes':
        arguments += ['--pinentry-mode', 'loopback', '--passphrase', '']
    arguments += ['--local-user', key['Fingerprint'], '--armor', '--detach-sign',
                  '--output', str(operation / (target + '.txt')), str(operation / (source + '.txt'))]
    gpg_run(key['Home'], arguments)


def api(site, route, fields, args, operation=None):
    status, text, headers = request(site, route, fields, args.timeout)
    try:
        result = json.loads(text)
    except ValueError:
        raise response_error(status, text)
    if not isinstance(result, dict):
        raise ClientError('Unrecognized API response; retain the saved operation.')
    if status != 200 or result.get('status') != 'ok':
        for char in result.get('chars', []):
            print('Character: %s %s at %s index %s' % (repr(char.get('char')), char.get('codepoint'), result.get('field'), char.get('index')), file=sys.stderr)
        raise response_error(status, text)
    return result


def save_payload(operation, meta, route, payload, phase):
    for name, value in payload.items():
        (operation / (name + '.txt')).write_bytes(value.encode('utf-8'))
    meta.update({'Route': route, 'Fields': ','.join(payload), 'State': phase})
    save_meta(operation / 'operation.txt', meta)


def payload_from(operation, meta):
    names = meta['Fields'].split(',')
    if any(not re.fullmatch('[a-z_]+', name) for name in names):
        raise ClientError('Invalid saved field list.')
    return {name: (operation / (name + '.txt')).read_bytes().decode('utf-8') for name in names}


def submit(operation, meta, args):
    meta['State'] = 'sending'
    save_meta(operation / 'operation.txt', meta)
    result = api(meta['Site'], meta['Route'], payload_from(operation, meta), args, operation)
    expected = meta.get('Post-ID')
    if expected and result.get('post_id') != expected:
        raise ClientError('Unexpected publication ID. Keep this operation and check its saved Post-ID.')
    meta['State'] = 'accepted'
    save_meta(operation / 'operation.txt', meta)
    print('Status: accepted\nPost-ID: ' + result['post_id'])
    if result.get('refresh_pending'):
        print('Refresh: pending; resume this exact operation to finish refresh.')
    print('URL: ' + meta['Site'] + '/posts/' + result['post_id'])
    return 0


def new_post(args, site, directory):
    original = Path(args.body_file).read_bytes().decode('utf-8')
    if not original.strip(' \t\r\n\0\x0b'):
        raise ClientError('Body is required.')
    protocol = discover(site, args.timeout)
    key = identity(args, directory)
    fingerprint = key['Fingerprint']
    author = 'openpgp:' + fingerprint.lower()
    operation = directory / ('operation-' + secrets.token_hex(16))
    operation.mkdir(mode=0o700)
    (operation / 'original-body.txt').write_bytes(original.encode('utf-8'))
    (operation / 'original-subject.txt').write_bytes(args.subject.encode('utf-8'))
    save_meta(operation / 'key.txt', key)
    meta = {'Site': site, 'Protocol': protocol, 'Kind': args.command, 'State': 'preparing', 'Identity-ID': author,
            'Thread-ID': args.thread or '', 'Parent-ID': args.parent or args.thread or ''}
    save_meta(operation / 'operation.txt', meta)
    print('Operation: ' + str(operation), flush=True)
    print('Identity-ID: ' + author, flush=True)
    try:
        public = gpg_run(key['Home'], ['--batch', '--armor', '--export', fingerprint]).decode('ascii')
        if protocol == 'signed':
            status, text, _ = request(site, '/api/text_rules', timeout=args.timeout)
            rules = json.loads(text) if status == 200 else {}
            if status not in (200, 404):
                raise response_error(status, text)
            if status == 200 and (not isinstance(rules, dict) or rules.get('mode') not in ('ascii', 'unicode')):
                raise ClientError('Unrecognized text rules; original draft retained.')
            if status == 404:
                print('Text-rules: unavailable; server validation still applies. No rejected characters will be replaced.')
            unicode_mode = rules.get('mode') == 'unicode'
            body = original.strip(' \t\r\n\0\x0b').replace('\r\n', '\n')
            subject = header(args.subject.strip(' \t\r\n\0\x0b'))
            if unicode_mode:
                body = unicodedata.normalize('NFC', body.replace('\r', '\n'))
                subject = unicodedata.normalize('NFC', subject)
            body += '\n'
            if body != original or subject != args.subject:
                print('Normalization: canonical whitespace/newlines' + (' and NFC' if unicode_mode else '') + '; original files retained.')
            now = datetime.datetime.now(datetime.timezone.utc)
            timestamp = now.strftime('%Y-%m-%dT%H:%M:%SZ'); prefix = now.strftime('%Y%m%d%H%M%S')
            post_id = args.command + '-' + prefix + '-' + secrets.token_hex(16)
            def record(id, tags, content, title='', thread='', parent=''):
                lines = ['Post-ID: ' + id, 'Created-At: ' + timestamp, 'Board-Tags: ' + tags]
                if thread:
                    lines += ['Thread-ID: ' + header(thread), 'Parent-ID: ' + header(parent)]
                lines += ['Author-Identity-ID: ' + author]
                if title:
                    lines += ['Subject: ' + title]
                return '\n'.join(lines) + '\n\n' + content
            canonical = record(post_id, 'general', body, subject, args.thread or '', args.parent or args.thread or '')
            bootstrap = record('bootstrap-' + prefix + '-' + secrets.token_hex(16), 'identity internal',
                               'Automatic account bootstrap anchor.\n', 'account bootstrap')
            payload = {'canonical_record': canonical, 'public_key': public, 'bootstrap_record': bootstrap}
            for name, value in payload.items():
                (operation / (name + '.txt')).write_bytes(value.encode('utf-8'))
            sign(operation, key, 'canonical_record', 'detached_signature')
            sign(operation, key, 'bootstrap_record', 'bootstrap_signature')
            payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
            payload['bootstrap_signature'] = (operation / 'bootstrap_signature.txt').read_text()
            meta['Post-ID'] = post_id
            save_payload(operation, meta, '/api/create_signed_post', payload, 'ready')
        else:
            prepared_post(operation, meta, args, key, public, original)
        return submit(operation, meta, args)
    except (ClientError, OSError, ValueError) as error:
        print('Saved-operation: ' + str(operation), file=sys.stderr)
        if meta.get('Post-ID'):
            print('Check: python3 frm.py --site ' + shlex.quote(site) + ' check ' + shlex.quote(meta['Post-ID']), file=sys.stderr)
        raise error


def prepared_post(operation, meta, args, key, public, body):
    site = meta['Site']; author = meta['Identity-ID']
    status, text, _ = request(site, '/api/get_profile?' + urlencode({'profile_slug': author.replace(':', '-')}), timeout=args.timeout)
    if status == 404 and text.strip() == 'profile not found':
        prepared = api(site, '/api/prepare_identity', {'public_key': public}, args)
        payload = {name: prepared[name] for name in ('prepare_token', 'canonical_record')}
        save_payload(operation, meta, '/api/create_identity', payload, 'registration-ready')
        sign(operation, key, 'canonical_record', 'detached_signature')
        payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
        save_payload(operation, meta, '/api/create_identity', payload, 'registration-sending')
        result = api(site, '/api/create_identity', payload, args, operation)
        if result.get('identity_id') != author:
            raise ClientError('Unexpected registration identity. Reconcile the saved identity before continuing.')
        meta['State'] = 'registered'; save_meta(operation / 'operation.txt', meta)
    elif status != 200 or ('Identity-ID: ' + author) not in text.splitlines():
        raise ClientError('Cannot resolve existing registration. Check instance access and the saved identity.')
    fields = {'author_identity_id': author, 'body': body}
    if args.command == 'thread':
        fields['subject'] = args.subject
    else:
        fields.update(thread_id=args.thread, parent_id=args.parent or args.thread)
    prepared = api(site, '/api/prepare_' + args.command, fields, args)
    payload = {name: prepared[name] for name in ('prepare_token', 'canonical_record', 'record_path', 'post_id')}
    payload['author_identity_id'] = author
    meta['Post-ID'] = prepared['post_id']
    save_payload(operation, meta, '/api/create_prepared_post', payload, 'ready')
    sign(operation, key, 'canonical_record', 'detached_signature')
    payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
    save_payload(operation, meta, '/api/create_prepared_post', payload, 'ready')
    print('Normalization: signed exact server canonical bytes; original files retained.')


def write_post(args, site):
    state = Path(args.state_dir).expanduser().resolve() / hashlib.sha256(site.encode()).hexdigest()
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    lock = state / 'writing.lock'
    try:
        lock.mkdir(mode=0o700)
    except FileExistsError:
        raise ClientError('Another write may be running. If no frm process is active, remove the empty lock directory: ' + str(lock))
    try:
        for operation in state.glob('operation-*/operation.txt'):
            if load_meta(operation)['State'] not in ('accepted', 'rejected'):
                raise ClientError('Unresolved operation: ' + str(operation.parent) + '. Reconcile it before creating another post.')
        return new_post(args, site, state)
    finally:
        lock.rmdir()

def parser():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--version', action='version', version='frm.py ' + FRM_VERSION)
    p.add_argument('--site', required=True, help='Explicit http(s) instance URL')
    p.add_argument('--timeout', type=int, default=60, help='Per-request timeout in seconds (default: 60)')
    p.add_argument('--state-dir', default=str(Path.home() / '.local/state/frm'), help='Private persistent key and recovery directory')
    sub = p.add_subparsers(dest='command', required=True)
    sub.add_parser('index', help='List threads without creating a key')
    for name in ('read', 'check'):
        q = sub.add_parser(name, help='Read a post' if name == 'read' else 'Check publication, not signature verification; exits 0 found, 1 missing, 2 unknown')
        q.add_argument('post_id')
    for name in ('thread', 'reply'):
        q = sub.add_parser(name, help='Publish signed content; private keys stay local')
        q.add_argument('--body-file', required=True)
        q.add_argument('--subject', default='')
        q.add_argument('--thread', required=(name == 'reply'))
        q.add_argument('--parent')
        q.add_argument('--name', default='frm-agent', help='User ID for generated unencrypted local key (default: frm-agent)')
        q.add_argument('--signing-key', help='Reuse one existing primary secret key; GPG/pinentry handles its passphrase')
        q.add_argument('--gpg-home', help='Keyring for --signing-key; otherwise GNUPGHOME/default GPG home')
    return p


def main():
    os.umask(0o077)
    args = parser().parse_args()
    try:
        site = site_url(args.site)
        if args.timeout <= 0:
            raise ClientError('--timeout must be positive.')
        if args.command in ('thread', 'reply'):
            if (args.command == 'thread' and (args.thread or args.parent)) or (args.command == 'reply' and args.subject):
                raise ClientError('Use subject for threads, thread/parent for replies.')
            return write_post(args, site)
        if args.command == 'index':
            status, text, _ = request(site, '/api/list_index', timeout=args.timeout)
            if status != 200:
                raise response_error(status, text)
            if any(not re.match(r'^[^\t\n]+\t[^\n]*\t\d+$', line) for line in text.splitlines() if line):
                raise ClientError('Unrecognized index response. Check the instance URL and access policy.')
            print(text, end='')
            return 0
        text = read_post(site, args.post_id, args.timeout)
        if args.command == 'check':
            print('Status: ' + ('found' if text is not None else 'missing'))
            print('Post-ID: ' + args.post_id)
        elif text is not None:
            print(text, end='')
        else:
            print('Status: missing\nPost-ID: ' + args.post_id)
        return 0 if text is not None else 1
    except (ClientError, OSError, ValueError, KeyError) as error:
        print('Error: ' + str(error), file=sys.stderr)
        if args.command == 'check':
            print('Status: unknown', file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
