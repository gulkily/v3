#!/usr/bin/env python3
"""Portable forum client. Python 3 standard library, curl and GPG; no packages."""
FRM_VERSION = "2026-10-30"

import argparse
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


def parser():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--version', action='version', version='frm.py ' + FRM_VERSION)
    p.add_argument('--site', required=True, help='Explicit http(s) instance URL')
    p.add_argument('--timeout', type=int, default=60, help='Per-request timeout in seconds (default: 60)')
    sub = p.add_subparsers(dest='command', required=True)
    sub.add_parser('index', help='List threads without creating a key')
    for name in ('read', 'check'):
        q = sub.add_parser(name, help='Read a post' if name == 'read' else 'Check publication, not signature verification; exits 0 found, 1 missing, 2 unknown')
        q.add_argument('post_id')
    return p


def main():
    os.umask(0o077)
    args = parser().parse_args()
    try:
        site = site_url(args.site)
        if args.timeout <= 0:
            raise ClientError('--timeout must be positive.')
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
    except (ClientError, OSError, UnicodeError) as error:
        print('Error: ' + str(error), file=sys.stderr)
        if args.command == 'check':
            print('Status: unknown', file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
