#!/usr/bin/env python3
"""Portable forum client. Python 3 standard library, curl and GPG; no packages."""
FRM_VERSION = "2026-11-11"

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
import time
from email.utils import parsedate_to_datetime
from urllib.parse import quote, urlencode, urlsplit


class ClientError(Exception):
    pass


# Trouble-report context for this run: last API route, busy retries, operation and identity.
TRACE = {'route': '', 'busy': 0, 'operation': None, 'identity': ''}
REPORT_ERROR_LIMIT = 300


def scrub(text):
    """Remove what a public report must never carry: key blocks, local paths, tokens."""
    text = re.sub(r'-----BEGIN PGP[\s\S]*?(?:-----END PGP[^\n]*?-----|$)', '[key block]', text)
    text = re.sub(r'(?<![\w.:/])(?:~|/(?!api/))[^\s\'",;)]*', '[path]', text)
    text = re.sub(r'\b[0-9A-Fa-f]{24,}\b', '[hex]', text)
    text = ' '.join(text.split())
    return text if len(text) <= REPORT_ERROR_LIMIT else text[:REPORT_ERROR_LIMIT - 3] + '...'


def trouble_report(command, route, error, outcome, busy_retries=0):
    """Plain-text report from a fixed field list; never includes drafts, keys or local state."""
    lines = ['Automatic trouble report from frm.py. Turn off with --no-report.', '',
             'Client: frm.py ' + FRM_VERSION, 'Command: ' + command,
             'Route: ' + (route or 'none'), 'Outcome: ' + outcome,
             'Busy-Retries: ' + str(busy_retries), 'Error: ' + (scrub(error) or 'none')]
    return '\n'.join(lines) + '\n'


def site_url(value):
    url = urlsplit(value)
    if (url.scheme not in ('http', 'https') or not url.hostname or url.username or url.password
            or url.query or url.fragment or any(c.isspace() for c in value)):
        raise ClientError('Use --site with an http(s) instance URL without credentials, query or fragment.')
    return value.rstrip('/')


def request(site, route, fields=None, timeout=60, files=None):
    """One request. Never forward authored data via redirects. No curl config/cookies.

    files maps a form field to (path, content type) and sends the request as multipart."""
    if not shutil.which('curl'):
        raise ClientError('curl is required. Install it using your operating system tools.')
    TRACE['route'] = route.split('?', 1)[0]
    with tempfile.TemporaryDirectory(prefix='frm-http-') as directory:
        body = Path(directory) / 'response'
        headers = Path(directory) / 'headers'
        command = ['curl', '--disable', '--silent', '--show-error', '--max-time', str(timeout),
                   '--proto', '=http,https', '--output', str(body), '--dump-header', str(headers),
                   '--write-out', '%{http_code}']
        data = None
        if files:
            # Field values go through files so curl never reads @ or < at the start of authored text.
            for index, (name, value) in enumerate((fields or {}).items()):
                part = Path(directory) / ('field-%d' % index)
                part.write_bytes(value.encode('utf-8'))
                command += ['--form', '%s=<%s' % (name, part)]
            for name, (path, kind) in files.items():
                command += ['--form', '%s=@%s;type=%s' % (name, path, kind)]
        elif fields is not None:
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


def api(site, route, fields, args, operation=None, files=None):
    for attempt in range(args.retries + 1):
        status, text, headers = request(site, route, fields, args.timeout, files)
        try:
            result = json.loads(text)
        except ValueError:
            raise response_error(status, text)
        if not isinstance(result, dict):
            raise ClientError('Unrecognized API response; retain the saved operation.')
        if status == 200 and result.get('status') == 'ok':
            return result
        if status == 202 and result.get('status') == 'staged' and route == '/api/create_signed_post':
            print_characters(result, route, fields, operation)
            return result
        if status == 503 and result.get('status') == 'busy' and attempt < args.retries:
            delay = headers.get('retry-after', result.get('retry_after', 10))
            try:
                delay = float(delay)
            except (ValueError, TypeError):
                try:
                    delay = parsedate_to_datetime(delay).timestamp() - time.time()
                except (ValueError, TypeError, OverflowError):
                    raise ClientError('Invalid Retry-After. Keep the operation and retry later.')
            if not 0 <= delay <= 60:
                raise ClientError('Server requested a longer retry delay. Keep the operation and retry after: ' + str(headers.get('retry-after', delay)))
            TRACE['busy'] += 1
            print('Retry: busy; waiting %s seconds (%s/%s)' % (delay, attempt + 1, args.retries), flush=True)
            time.sleep(delay)
            continue
        if operation and status in (400, 403) and ((route == '/api/create_signed_post' and result.get('code') in ('invalid_post', 'access_denied')) or route.startswith('/api/prepare_')):
            meta = load_meta(operation / 'operation.txt')
            meta['State'] = 'rejected'; save_meta(operation / 'operation.txt', meta)
        print_characters(result, route, fields, operation)
        raise response_error(status, text)


def print_characters(result, route, fields, operation):
    """Report each refused character at its position in the original draft file."""
    for char in (result.get('chars') if isinstance(result.get('chars'), list) else []):
        if not isinstance(char, dict): continue
        index = char.get('index'); field = result.get('field')
        if operation and route == '/api/create_signed_post' and field in ('body', 'subject') and isinstance(index, int):
            original = (operation / ('original-' + field + '.txt')).read_bytes().decode('utf-8')
            canonical = fields['canonical_record'].split('\n\n', 1)[1] if field == 'body' else next((line[9:] for line in fields['canonical_record'].splitlines() if line.startswith('Subject: ')), '')
            index = source_index(original, canonical, index)
        shown = repr(char['char']) + ' ' if isinstance(char.get('char'), str) else ''
        print('Character: %s%s at %s index %s' % (shown, char.get('codepoint'), field, index), file=sys.stderr)


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
    files = None
    if meta.get('Image') and meta['Route'] == '/api/create_prepared_post':
        files = {'image': (operation / meta['Image'], IMAGE_TYPES[meta['Image'].rsplit('.', 1)[1]])}
    result = api(meta['Site'], meta['Route'], payload_from(operation, meta), args, operation, files)
    expected = meta.get('Post-ID')
    if expected and result.get('post_id') != expected:
        raise ClientError('Unexpected publication ID. Keep this operation and check its saved Post-ID.')
    if result.get('status') == 'staged':
        meta['State'] = 'staged'
        save_meta(operation / 'operation.txt', meta)
        print('Staged: the server kept this post but has not published it.')
        print('Reason: ' + str(result.get('reason') or 'unknown'))
        print('Post-ID: ' + result['post_id'])
        print('View: ' + meta['Site'] + '/staged/')
        print('Fix the draft, then publish it under the same post ID:')
        print('Replace: ' + replace_command(meta))
        return STAGED_EXIT
    if meta.get('Replaces'):
        staged_operation = Path(meta['Replaces'])
        staged_meta = load_meta(staged_operation / 'operation.txt')
        staged_meta['State'] = 'replaced'
        save_meta(staged_operation / 'operation.txt', staged_meta)
    meta['State'] = 'accepted'
    save_meta(operation / 'operation.txt', meta)
    print('Status: accepted\nPost-ID: ' + result['post_id'])
    if result.get('refresh_pending'):
        print('Refresh: pending; resume this exact operation to finish refresh.')
    print('URL: ' + meta['Site'] + '/posts/' + result['post_id'])
    return 0


def new_post(args, site, directory, operation=None, saved_meta=None, saved_key=None):
    original = Path(args.body_file).read_bytes().decode('utf-8')
    if not original.strip(' \t\r\n\0\x0b'):
        raise ClientError('Body is required.')
    protocol = saved_meta['Protocol'] if saved_meta else discover(site, args.timeout)
    image = None
    if not saved_meta and getattr(args, 'image', None):
        if re.search(r'(?m)^Image-v1:', original):
            raise ClientError('Remove the Image-v1 line from the body; --image adds it.')
        if len((args.image_description or '').encode('utf-8')) > 500 or re.search('[\x00-\x1f\x7f]', args.image_description or ''):
            raise ClientError('Image descriptions must be at most 500 bytes and contain no control characters.')
        prepared, kind, size, note = prepare_image(args.image)
        image = (kind, image_reference(prepared, kind, size, args.image_description or ''), prepared)
        print('Image: %d x %d %s, %d KiB, %s' % (size[0], size[1], kind.upper(), -(-len(prepared) // 1024), note))
        # Only the prepared publish accepts the image file.
        protocol = 'prepared'
    key = saved_key or (load_meta(args.replaces / 'key.txt') if getattr(args, 'replaces', None) else identity(args, directory))
    fingerprint = key['Fingerprint']
    author = 'openpgp:' + fingerprint.lower()
    if operation is None:
        operation = directory / ('operation-' + secrets.token_hex(16))
        operation.mkdir(mode=0o700)
        (operation / 'original-body.txt').write_bytes(original.encode('utf-8'))
        (operation / 'original-subject.txt').write_bytes(args.subject.encode('utf-8'))
        save_meta(operation / 'key.txt', key)
        meta = {'Site': site, 'Protocol': protocol, 'Kind': args.command, 'State': 'preparing', 'Identity-ID': author,
                'Thread-ID': args.thread or '', 'Parent-ID': args.parent or args.thread or ''}
        if getattr(args, 'replaces', None):
            if protocol != 'signed':
                raise ClientError('This site does not take one-shot signed posts, so --replace is unavailable.')
            meta.update({'Replaces': str(args.replaces), 'Replace-Post-ID': args.replace})
        if image:
            # The operation keeps its own copy, so a resume sends the bytes that were signed.
            (operation / ('image.' + image[0])).write_bytes(image[2])
            meta.update({'Image': 'image.' + image[0], 'Image-Line': image[1]})
        save_meta(operation / 'operation.txt', meta)
    else:
        meta = saved_meta
    TRACE['operation'] = operation; TRACE['identity'] = author
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
            post_timestamp = timestamp
            if meta.get('Replaces'):
                # Same post ID and Created-At as the staged post; the server records when it arrived.
                post_id = meta['Replace-Post-ID']
                stamp = post_id.split('-')[1]
                post_timestamp = '%s-%s-%sT%s:%s:%sZ' % (stamp[0:4], stamp[4:6], stamp[6:8], stamp[8:10], stamp[10:12], stamp[12:14])
            def record(id, tags, content, title='', thread='', parent='', created=timestamp):
                lines = ['Post-ID: ' + id, 'Created-At: ' + created, 'Board-Tags: ' + tags]
                if thread:
                    lines += ['Thread-ID: ' + header(thread), 'Parent-ID: ' + header(parent)]
                lines += ['Author-Identity-ID: ' + author]
                if title:
                    lines += ['Subject: ' + title]
                return '\n'.join(lines) + '\n\n' + content
            canonical = record(post_id, args.tags, body, subject, args.thread or '', args.parent or args.thread or '', post_timestamp)
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
        if rejected(operation):
            print(REJECTED_GUIDANCE, file=sys.stderr)
            raise error
        print('Resume: ' + recovery_command(operation, meta), file=sys.stderr)
        if meta.get('Post-ID'):
            print('Check: python3 frm.py --site ' + shlex.quote(site) + ' check ' + shlex.quote(meta['Post-ID']), file=sys.stderr)
        raise error


IMAGE_TYPES = {'png': 'image/png', 'jpg': 'image/jpeg'}
# Same limits as the browser composer (public/assets/image_prepare.js) and the server's upload check.
IMAGE_SOURCE_BYTES = 32 * 1024 * 1024
IMAGE_SOURCE_SIDE = 16384
IMAGE_SOURCE_PIXELS = 40 * 1000 * 1000
IMAGE_MAX_BYTES = 5 * 1024 * 1024
IMAGE_MAX_SIDE = 2048
# PNG chunks that only carry metadata (text, EXIF, timestamps); everything else is image data or colour.
PNG_METADATA_CHUNKS = {b'tEXt', b'zTXt', b'iTXt', b'eXIf', b'tIME'}


def image_size(data, kind):
    """Pixel width and height from a PNG IHDR or a JPEG start-of-frame segment."""
    if kind == 'png':
        if len(data) < 24 or data[12:16] != b'IHDR':
            raise ClientError('Image is not a readable PNG.')
        return int.from_bytes(data[16:20], 'big'), int.from_bytes(data[20:24], 'big')
    for marker, segment in jpeg_segments(data):
        if marker in (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF) and len(segment) >= 9:
            return int.from_bytes(segment[7:9], 'big'), int.from_bytes(segment[5:7], 'big')
    raise ClientError('Image is not a readable JPEG.')


def jpeg_segments(data):
    """(marker, bytes) for each header segment up to the start of scan; the last item is the rest of the file."""
    if not data.startswith(b'\xff\xd8'):
        raise ClientError('Image is not a readable JPEG.')
    position = 2
    while position + 4 <= len(data):
        if data[position] != 0xFF:
            raise ClientError('Image is not a readable JPEG.')
        marker = data[position + 1]
        if marker == 0xFF:
            position += 1
            continue
        if marker == 0xDA:
            yield marker, data[position:]
            return
        length = int.from_bytes(data[position + 2:position + 4], 'big')
        if length < 2 or position + 2 + length > len(data):
            raise ClientError('Image is not a readable JPEG.')
        yield marker, data[position:position + 2 + length]
        position += 2 + length
    raise ClientError('Image is not a readable JPEG.')


def jpeg_orientation(segment):
    """EXIF orientation from an APP1 segment, or 1 when it has none."""
    body = segment[4:]
    if not body.startswith(b'Exif\x00\x00') or len(body) < 14:
        return 1
    tiff = body[6:]
    order = {b'II': 'little', b'MM': 'big'}.get(tiff[:2])
    if not order:
        return 1
    offset = int.from_bytes(tiff[4:8], order)
    if offset + 2 > len(tiff):
        return 1
    for index in range(int.from_bytes(tiff[offset:offset + 2], order)):
        entry = tiff[offset + 2 + index * 12:offset + 14 + index * 12]
        if len(entry) == 12 and int.from_bytes(entry[:2], order) == 0x0112:
            return int.from_bytes(entry[8:10], order)
    return 1


def strip_image_metadata(data, kind):
    """Drop metadata the way the browser's re-encode does, without changing any pixels.

    PNG: remove text, EXIF and timestamp chunks. JPEG: remove comments and APP segments other than
    JFIF (APP0) and Adobe (APP14), which describe the colour data. A rotated photo can't be fixed
    without re-encoding, so it is refused."""
    if kind == 'png':
        if b'acTL' in data[:data.find(b'IDAT')]:
            raise ClientError('Animated PNGs need Pillow to keep one frame (python3 -m pip install Pillow), or export a still image.')
        out, position = [data[:8]], 8
        while position + 12 <= len(data):
            length = int.from_bytes(data[position:position + 4], 'big')
            chunk = data[position:position + 12 + length]
            if chunk[4:8] not in PNG_METADATA_CHUNKS:
                out.append(chunk)
            position += 12 + length
        return b''.join(out)
    out = [b'\xff\xd8']
    for marker, segment in jpeg_segments(data):
        if marker == 0xE1 and jpeg_orientation(segment) != 1:
            raise ClientError('This photo is stored rotated. Install Pillow (python3 -m pip install Pillow) so frm.py can turn it upright, or export an upright copy.')
        if marker == 0xFE or (0xE1 <= marker <= 0xEF and marker != 0xEE):
            continue
        out.append(segment)
    return b''.join(out)


def pillow():
    """Pillow's Image and ImageOps modules when installed; frm.py itself needs only the standard library."""
    try:
        from PIL import Image, ImageOps
    except ImportError:
        return None
    return Image, ImageOps


def prepare_image_with_pillow(data, modules):
    """Same steps as image_prepare.js: upright, one frame, at most 2048 px per side, re-encoded
    (JPEG stays JPEG at quality 85, anything else becomes PNG), shrunk by 0.8 until it fits 5 MiB."""
    import io
    Image, ImageOps = modules
    try:
        source = Image.open(io.BytesIO(data))
        source.size
    except Exception:
        raise ClientError('This image cannot be prepared. Choose JPEG, PNG, WebP, GIF or BMP, or export your image to one of those formats.')
    width, height = source.size
    if max(width, height) > IMAGE_SOURCE_SIDE or width * height > IMAGE_SOURCE_PIXELS:
        raise ClientError('This image is too large to prepare safely. Export a copy below 40 megapixels and 16384 pixels per side.')
    kind = 'jpg' if source.format == 'JPEG' else 'png'
    source.seek(0)
    image = ImageOps.exif_transpose(source)
    if kind == 'jpg':
        image = image.convert('RGB')
    else:
        image = image.convert('RGBA' if image.mode in ('RGBA', 'LA', 'PA') or 'transparency' in image.info else 'RGB')
    width, height = image.size
    ratio = min(1.0, IMAGE_MAX_SIDE / max(width, height))
    for attempt in range(8):
        size = (max(1, int(width * ratio)), max(1, int(height * ratio)))
        frame = image if size == image.size else image.resize(size, Image.LANCZOS)
        out = io.BytesIO()
        if kind == 'jpg':
            frame.save(out, 'JPEG', quality=85, optimize=True)
        else:
            frame.save(out, 'PNG', optimize=True)
        if out.tell() <= IMAGE_MAX_BYTES:
            return out.getvalue(), kind, size
        ratio *= 0.8
    raise ClientError('Unable to prepare this image within 5 MiB. Try a smaller copy.')


def prepare_image(path):
    """Return (bytes to upload, 'png'|'jpg', (width, height), note) for --image.

    With Pillow: prepared like the browser composer (any common format, resized and re-encoded).
    Without it: an already-prepared PNG or JPEG is sent with its metadata removed."""
    data = Path(path).read_bytes()
    if not data or len(data) > IMAGE_SOURCE_BYTES:
        raise ClientError('Attach an image of at most 32 MiB.')
    modules = pillow()
    if modules:
        prepared, kind, size = prepare_image_with_pillow(data, modules)
        return prepared, kind, size, 'prepared with Pillow'
    kind = 'png' if data.startswith(b'\x89PNG\r\n\x1a\n') else 'jpg' if data.startswith(b'\xff\xd8\xff') else None
    if kind is None:
        raise ClientError('Without Pillow, frm.py sends PNG or JPEG only. Install Pillow (python3 -m pip install Pillow) to convert other formats.')
    width, height = image_size(data, kind)
    if not (0 < width <= IMAGE_MAX_SIDE and 0 < height <= IMAGE_MAX_SIDE):
        raise ClientError('Without Pillow, images must already be at most %d pixels per side (this one is %d x %d). Install Pillow (python3 -m pip install Pillow) to resize automatically.' % (IMAGE_MAX_SIDE, width, height))
    prepared = strip_image_metadata(data, kind)
    if len(prepared) > IMAGE_MAX_BYTES:
        raise ClientError('Without Pillow, images must already be at most 5 MiB. Install Pillow (python3 -m pip install Pillow) to shrink automatically.')
    return prepared, kind, (width, height), 'sent as is, metadata removed (Pillow not installed)'


def image_reference(data, kind, size, description):
    """The signed Image-v1 line for prepared bytes, with the description encoded like the browser's."""
    if len(description.encode('utf-8')) > 500 or re.search('[\x00-\x1f\x7f]', description):
        raise ClientError('Image descriptions must be at most 500 bytes and contain no control characters.')
    line = 'Image-v1: %s %s %d %d %d' % (hashlib.sha256(data).hexdigest(), kind, len(data), size[0], size[1])
    if description:
        line += ' ' + quote(description, safe='')
    return line


def board_tags(value):
    value = ' '.join(value.split())
    if not re.fullmatch(r'[a-z0-9-]+(?: [a-z0-9-]+)*', value) or 'identity' in value.split():
        raise argparse.ArgumentTypeError('tags must be lowercase words (a-z, 0-9, -) separated by spaces, not identity')
    return value


def prepared_post(operation, meta, args, key, public, body):
    site = meta['Site']; author = meta['Identity-ID']
    status, text, _ = request(site, '/api/get_profile?' + urlencode({'profile_slug': author.replace(':', '-')}), timeout=args.timeout)
    if status == 404 and text.strip() == 'profile not found':
        prepared = api(site, '/api/prepare_identity', {'public_key': public}, args, operation)
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
    if meta.get('Image-Line'):
        body = body.rstrip() + '\n\n' + meta['Image-Line']
    fields = {'author_identity_id': author, 'body': body}
    if args.command == 'thread':
        fields['subject'] = args.subject
        fields['board_tags'] = args.tags
    else:
        fields.update(thread_id=args.thread, parent_id=args.parent or args.thread)
    prepared = api(site, '/api/prepare_' + args.command, fields, args, operation)
    payload = {name: prepared[name] for name in ('prepare_token', 'canonical_record', 'record_path', 'post_id')}
    payload['author_identity_id'] = author
    meta['Post-ID'] = prepared['post_id']
    save_payload(operation, meta, '/api/create_prepared_post', payload, 'ready')
    sign(operation, key, 'canonical_record', 'detached_signature')
    payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
    save_payload(operation, meta, '/api/create_prepared_post', payload, 'ready')
    print('Normalization: signed exact server canonical bytes; original files retained.')



REJECTED_GUIDANCE = 'Rejected: the server refused this write, so nothing was published. Correct the original draft and start a new write; do not resume.'
# Exit status for a post the server kept but did not publish (0 published, 2 error).
STAGED_EXIT = 3


def replace_command(meta):
    command = 'python3 frm.py --site ' + shlex.quote(meta['Site']) + ' ' + meta['Kind'] + ' --replace ' + shlex.quote(meta['Post-ID']) + ' --body-file FIXED_DRAFT'
    return command + (' --subject SUBJECT' if meta['Kind'] == 'thread' else '')


def staged_operation(state, site, kind, post_id):
    """The saved operation that staged post_id on this site."""
    for file in state.glob('operation-*/operation.txt'):
        meta = load_meta(file)
        if meta.get('Post-ID') == post_id and meta.get('Site') == site and meta.get('State') == 'staged':
            if meta.get('Kind') != kind:
                raise ClientError('Post %s was staged as a %s; use that command to replace it.' % (post_id, meta.get('Kind')))
            return file.parent, meta
    raise ClientError('No staged operation for %s in this state directory. Replace it from the client that sent it.' % post_id)


def rejected(operation):
    try:
        return load_meta(operation / 'operation.txt').get('State') == 'rejected'
    except (OSError, ValueError):
        return False


def recovery_command(operation, meta):
    return 'python3 frm.py --site ' + shlex.quote(meta['Site']) + ' ' + meta['Kind'] + ' --resume ' + shlex.quote(str(operation))


def source_index(original, canonical, index):
    """NFD lengths align NFC output to original code points without rewriting input."""
    trimmed = original.strip(' \t\r\n\0\x0b')
    offset = len(original) - len(original.lstrip(' \t\r\n\0\x0b'))
    target = len(unicodedata.normalize('NFD', canonical[:index]))
    at = 0
    for i, char in enumerate(trimmed):
        if char == '\r' and trimmed[i:i + 2] == '\r\n':
            continue
        if at >= target:
            return offset + i
        at += len(unicodedata.normalize('NFD', char))
    return index


def resume_post(operation, meta, args):
    if meta['Protocol'] not in ('signed', 'prepared'):
        raise ClientError('Unknown saved protocol; no request sent.')
    if meta.get('Route') not in (None, '/api/create_signed_post', '/api/create_prepared_post', '/api/create_identity'):
        raise ClientError('Unknown saved route; no request sent.')
    key = load_meta(operation / 'key.txt')
    state = meta['State']
    if state in ('staged', 'replaced'):
        raise ClientError('This post was staged, not lost; resending the same draft stages it again. ' + ('Publish a fix with: ' + replace_command(meta) if state == 'staged' else 'It was replaced by a fixed version.'))
    if meta['Protocol'] == 'signed' and state in ('ready', 'sending', 'accepted'):
        return submit(operation, meta, args)
    if state == 'rejected':
        raise ClientError('This operation was rejected. Correct the original draft and start a new write; do not edit signed files.')
    if meta['Protocol'] == 'prepared' and state == 'sending':
        text = read_post(meta['Site'], meta['Post-ID'], args.timeout)
        if text is not None:
            canonical = (operation / 'canonical_record.txt').read_bytes().decode('utf-8')
            if text.split('\n\n', 1)[1] != canonical.split('\n\n', 1)[1]:
                raise ClientError('Published body differs from the saved operation; ask the operator to reconcile it.')
            meta['State'] = 'accepted'; save_meta(operation / 'operation.txt', meta)
            print('Status: found\nPost-ID: ' + meta['Post-ID'])
            return 0
        raise ClientError('Prepared publication outcome is unresolved. Keep this operation; ask the operator to confirm its Post-ID and token before making another post. A missing read is not proof it failed.')
    if meta['Protocol'] == 'prepared' and state == 'accepted':
        print('Status: accepted\nPost-ID: ' + meta['Post-ID'])
        return 0
    if meta['Protocol'] == 'prepared' and state == 'ready':
        payload = payload_from(operation, meta)
        if 'detached_signature' not in payload:
            sign(operation, key, 'canonical_record', 'detached_signature')
            payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
            save_payload(operation, meta, '/api/create_prepared_post', payload, 'ready')
        return submit(operation, meta, args)
    if meta['Protocol'] == 'prepared' and state in ('registration-ready', 'registration-sending'):
        author = meta['Identity-ID']
        status, text, _ = request(meta['Site'], '/api/get_profile?' + urlencode({'profile_slug': author.replace(':', '-')}), timeout=args.timeout)
        if status == 200 and ('Identity-ID: ' + author) in text.splitlines():
            meta['State'] = 'registered'; save_meta(operation / 'operation.txt', meta)
        elif state == 'registration-ready' and status == 404 and text.strip() == 'profile not found':
            payload = payload_from(operation, meta)
            sign(operation, key, 'canonical_record', 'detached_signature')
            payload['detached_signature'] = (operation / 'detached_signature.txt').read_text()
            save_payload(operation, meta, '/api/create_identity', payload, 'registration-sending')
            result = api(meta['Site'], '/api/create_identity', payload, args, operation)
            if result.get('identity_id') != author:
                raise ClientError('Registration identity mismatch; reconcile with the operator.')
            meta['State'] = 'registered'; save_meta(operation / 'operation.txt', meta)
        else:
            raise ClientError('Registration outcome unresolved. Keep this key and operation; ask the operator to reconcile the saved identity/token. No new registration sent.')
    # These states have not submitted an ordinary post. Restore original inputs.
    args.body_file = str(operation / 'original-body.txt')
    args.subject = (operation / 'original-subject.txt').read_bytes().decode('utf-8')
    args.thread = meta.get('Thread-ID') or None
    args.parent = meta.get('Parent-ID') or None
    if meta['Protocol'] == 'signed' and (operation / 'canonical_record.txt').exists() and (operation / 'bootstrap_record.txt').exists():
        payload = {name: (operation / (name + '.txt')).read_bytes().decode('utf-8') for name in ('canonical_record', 'bootstrap_record', 'public_key')}
        for source, target in [('canonical_record', 'detached_signature'), ('bootstrap_record', 'bootstrap_signature')]:
            if not (operation / (target + '.txt')).exists():
                sign(operation, key, source, target)
            payload[target] = (operation / (target + '.txt')).read_text()
        meta['Post-ID'] = payload['canonical_record'].splitlines()[0].split(': ', 1)[1]
        save_payload(operation, meta, '/api/create_signed_post', payload, 'ready')
        return submit(operation, meta, args)
    if meta['State'] not in ('preparing', 'registered'):
        raise ClientError('Unrecognized operation state; no request sent.')
    return new_post(args, meta['Site'], operation.parent, operation, meta, key)


def state_directory(args, site):
    if getattr(args, 'resume', None):
        return Path(args.resume).expanduser().resolve().parent
    return Path(args.state_dir).expanduser().resolve() / hashlib.sha256(site.encode()).hexdigest()


def post_trouble_report(site, thread_id, body, identity_id, timeout):
    """Unsigned reply, so a broken key or registration can still be reported."""
    fields = {'thread_id': thread_id, 'parent_id': thread_id, 'body': body, 'body_format': 'plain'}
    if identity_id:
        fields['author_identity_id'] = identity_id
    while True:
        status, text, _ = request(site, '/api/create_reply', fields, timeout)
        values = dict(line.split('=', 1) for line in text.splitlines() if '=' in line)
        if status == 200 and values.get('status') == 'ok' and values.get('post_id'):
            return values['post_id']
        if status == 400 and 'author_identity_id' in fields:
            del fields['author_identity_id']  # unregistered identity: report anonymously instead
            continue
        raise ClientError('HTTP %s: %s' % (status, values.get('error') or 'unexpected reply response'))


def report_trouble(args, site, error=None):
    """After a write: post queued reports, then this run's report. Never raises."""
    try:
        _report_trouble(args, site, error)
    except Exception as problem:  # a report must never hide or replace the write's own outcome
        print('Trouble-report: not sent (' + type(problem).__name__ + ').', file=sys.stderr)


def _report_trouble(args, site, error):
    if getattr(args, 'no_report', False):
        return
    operation = TRACE['operation']
    try:
        meta = load_meta(operation / 'operation.txt') if operation else {}
    except (OSError, ValueError):
        meta = {}
    if error is None and not TRACE['busy']:
        outcome = None
    elif error is None:
        outcome = 'accepted after busy retries'
    elif meta.get('State') == 'rejected':
        outcome = 'rejected'
    elif meta and meta.get('State') != 'accepted':
        outcome = 'unresolved'
    else:
        outcome = 'failed'
    if outcome and meta.get('Trouble-Reported') == 'yes':
        outcome = None
    thread_id = args.report_thread
    if not thread_id:
        if outcome:
            print('Trouble-report: not sent; no --report-thread or FRM_REPORT_THREAD set.', file=sys.stderr)
        return
    try:
        state = state_directory(args, site)
        state.mkdir(mode=0o700, parents=True, exist_ok=True)
    except OSError as problem:
        print('Trouble-report: not sent; state directory unavailable: ' + str(problem), file=sys.stderr)
        return
    identity_id = TRACE['identity']
    if not identity_id and (state / 'identity.txt').exists():
        try:
            identity_id = 'openpgp:' + load_meta(state / 'identity.txt')['Fingerprint'].lower()
        except (OSError, ValueError, KeyError):
            identity_id = ''
    queued = sorted(state.glob('trouble-pending-*.txt'))
    if outcome:
        report = trouble_report(args.command, TRACE['route'], str(error or ''), outcome, TRACE['busy'])
        print('Trouble-report:\n' + ''.join('  ' + line + '\n' for line in report.splitlines()), end='', file=sys.stderr)
        if operation and meta:
            meta['Trouble-Reported'] = 'yes'
            try:
                save_meta(operation / 'operation.txt', meta)
            except OSError:
                pass
        pending = state / ('trouble-pending-' + secrets.token_hex(8) + '.txt')
        pending.write_bytes(report.encode('utf-8'))
        queued.append(pending)
    for file in queued:
        try:
            post_id = post_trouble_report(site, thread_id, file.read_text(encoding='utf-8'), identity_id, args.timeout)
        except (ClientError, OSError, ValueError) as problem:
            print('Trouble-report: not sent (' + str(problem) + '); saved for the next write: ' + str(file), file=sys.stderr)
            return
        file.unlink()
        print('Trouble-report: sent ' + site + '/threads/' + thread_id + ' (post ' + post_id + ')', file=sys.stderr)


def write_post(args, site):
    operation = Path(args.resume).expanduser().resolve() if args.resume else None
    state = state_directory(args, site)
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    lock = state / 'writing.lock'
    try:
        lock.mkdir(mode=0o700)
    except FileExistsError:
        raise ClientError('Another write may be running. If no frm process is active, remove the empty lock directory: ' + str(lock))
    try:
        if operation:
            TRACE['operation'] = operation
            meta = load_meta(operation / 'operation.txt')
            TRACE['identity'] = meta.get('Identity-ID', '')
            if meta['Site'] != site or meta['Kind'] != args.command:
                raise ClientError('Resume must use the saved site and command. ' + recovery_command(operation, meta))
            try:
                return resume_post(operation, meta, args)
            except (ClientError, OSError, ValueError, KeyError):
                if not rejected(operation):
                    print('Resume: ' + recovery_command(operation, meta), file=sys.stderr)
                raise
        for file in state.glob('operation-*/operation.txt'):
            meta = load_meta(file)
            if meta['State'] not in ('accepted', 'rejected', 'staged', 'replaced'):
                raise ClientError('Unresolved operation. Resume it before creating another post: ' + recovery_command(file.parent, meta))
        if args.replace:
            args.replaces, staged = staged_operation(state, site, args.command, args.replace)
            args.thread = staged.get('Thread-ID') or None
            args.parent = staged.get('Parent-ID') or None
        return new_post(args, site, state)
    finally:
        lock.rmdir()


def parser():
    p = argparse.ArgumentParser(description=__doc__, epilog='Generated keys are unencrypted in a private state directory; keep it for identity reuse and recovery. Existing keys use GPG/pinentry. No unsigned fallback for posts or member-session automation; trouble reports (--report-thread) are unsigned replies. check verifies publication only. Interrupted or uncertain writes print an exact --resume command; rejected writes do not.')
    p.add_argument('--version', action='version', version='frm.py ' + FRM_VERSION)
    p.add_argument('--site', required=True, help='Explicit http(s) instance URL')
    p.add_argument('--timeout', type=int, default=60, help='Per-request timeout in seconds (default: 60)')
    p.add_argument('--retries', type=int, default=2, help='Busy write retries after first attempt, 0–5 (default: 2); never retries other failures automatically')
    p.add_argument('--state-dir', default=str(Path.home() / '.local/state/frm'), help='Private persistent key and recovery directory')
    sub = p.add_subparsers(dest='command', required=True)
    sub.add_parser('index', help='List threads without creating a key')
    for name in ('read', 'check'):
        q = sub.add_parser(name, help='Read a post' if name == 'read' else 'Check publication, not signature verification; exits 0 found, 1 missing, 2 unknown')
        q.add_argument('post_id')
    for name in ('thread', 'reply'):
        q = sub.add_parser(name, help='Publish signed content; private keys stay local')
        q.add_argument('--body-file')
        q.add_argument('--resume', metavar='OPERATION_DIR', help='Resume saved exact operation without creating a new post')
        q.add_argument('--replace', metavar='POST_ID', help='Publish a fixed version of a staged post under its post ID (keeps its thread; give the new --body-file and, for threads, --subject)')
        q.add_argument('--subject', default='')
        q.add_argument('--thread')
        q.add_argument('--parent')
        q.add_argument('--tags', default='general', type=board_tags, help='Space-separated lowercase board tags (default: general)')
        q.add_argument('--image', help='Attach one image. With Pillow installed it is prepared like the browser composer (upright, at most 2048 pixels per side, re-encoded under 5 MiB); without Pillow it must already be a PNG or JPEG within those limits. Needs an approved key on an instance with images enabled')
        q.add_argument('--image-description', default='', help='Short description of the image (alt text, at most 500 bytes)')
        q.add_argument('--name', default='frm-agent', help='User ID for generated unencrypted local key (default: frm-agent)')
        q.add_argument('--signing-key', help='Reuse one existing primary secret key; GPG/pinentry handles its passphrase')
        q.add_argument('--gpg-home', help='Keyring for --signing-key; otherwise GNUPGHOME/default GPG home')
        q.add_argument('--report-thread', default=os.environ.get('FRM_REPORT_THREAD', ''), help='Thread for automatic trouble reports (default: FRM_REPORT_THREAD); reports are printed and carry no draft, key or path')
        q.add_argument('--no-report', action='store_true', help='Never post trouble reports')
    return p


def main():
    os.umask(0o077)
    args = parser().parse_args()
    site = None
    try:
        site = site_url(args.site)
        if not 0 <= args.retries <= 5:
            raise ClientError('--retries must be between 0 and 5.')
        if args.timeout <= 0:
            raise ClientError('--timeout must be positive.')
        if args.command in ('thread', 'reply'):
            if args.resume and any((args.body_file, args.subject, args.thread, args.parent, args.signing_key, args.gpg_home, args.image, args.image_description, args.replace)):
                raise ClientError('--resume uses saved inputs; do not combine it with new content or key options.')
            if args.replace and (args.thread or args.parent or args.image or args.signing_key or args.gpg_home):
                raise ClientError('--replace keeps the staged post\'s thread and key; give only the fixed --body-file (and --subject for threads).')
            if not args.resume and (not args.body_file or (args.command == 'reply' and not args.thread and not args.replace)):
                raise ClientError('New writes require --body-file; replies also require --thread.')
            if (args.command == 'thread' and (args.thread or args.parent)) or (args.command == 'reply' and args.subject):
                raise ClientError('Use subject for threads, thread/parent for replies.')
            if args.image_description and not args.image:
                raise ClientError('--image-description needs --image.')
            result = write_post(args, site)
            report_trouble(args, site)
            return result
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
        if args.command in ('thread', 'reply') and site:
            report_trouble(args, site, error)
        return 2


if __name__ == '__main__':
    sys.exit(main())
