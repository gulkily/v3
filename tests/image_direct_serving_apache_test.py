"""Direct image serving through a private Apache: the shipped public/.htaccess rules and the
headers PublishedImages writes. Never touches system Apache. Run: python3 tests/image_direct_serving_apache_test.py
Skips (exit 0, prints why) when Apache or its modules are not installed."""
import os, pathlib, pwd, shutil, socket, subprocess, sys, tempfile, time, urllib.request, urllib.error

project = pathlib.Path(__file__).resolve().parents[1]
modules = pathlib.Path('/usr/lib/apache2/modules')
apache = shutil.which('apache2')
needed = ['mod_mpm_prefork.so', 'mod_authz_core.so', 'mod_dir.so', 'mod_mime.so', 'mod_rewrite.so', 'mod_headers.so']
if apache is None or any(not (modules / m).exists() for m in needed):
    print('skipped: apache2 with rewrite and headers modules is not installed')
    sys.exit(0)

work = pathlib.Path(tempfile.mkdtemp(prefix='v3-direct-images-'))
try:
    root = work / 'public'
    root.mkdir()
    shutil.copy(project / 'public/.htaccess', root / '.htaccess')
    # Stands in for the application: whatever reaches it answers with this marker.
    (root / 'index.php').write_text('APPLICATION\n')
    sha, other = 'a' * 64, 'b' * 64
    source = work / 'source.bin'
    source.write_bytes(b'\x89PNG published bytes')
    published = root / '.published-images'
    names = [f'{sha}.png', f'thumbs/{sha}-wide.png.webp']
    code = ('require "autoload.php"; $p = new ForumRewrite\\Images\\PublishedImages($argv[1]);'
            'foreach (array_slice($argv, 3) as $n) { $p->publish($n, $argv[2]); } echo implode(",", $p->names());')
    out = subprocess.run(['php', '-r', code, str(published), str(source), *names], cwd=project, capture_output=True, text=True, check=True).stdout
    assert out == ','.join(sorted(names)), out

    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    # Apache will not serve as root; as root, run its workers as www-data and let them read the fixture.
    user = 'www-data' if os.getuid() == 0 else pwd.getpwuid(os.getuid()).pw_name
    if os.getuid() == 0:
        subprocess.run(['chmod', '-R', 'a+rX', str(work)], check=True)
    config = work / 'httpd.conf'
    config.write_text(f'''ServerRoot "/etc/apache2"
ServerName 127.0.0.1
Listen 127.0.0.1:{port}
PidFile "{work}/httpd.pid"
DefaultRuntimeDir "{work}"
ErrorLog "{work}/error.log"
LogLevel warn
''' + ''.join(f'LoadModule {m[4:-3]}_module {modules}/{m}\n' for m in needed) + f'''User {user}
TypesConfig /etc/mime.types
DocumentRoot "{root}"
<Directory "{root}">
  AllowOverride All
  Require all granted
</Directory>
''')
    server = subprocess.Popen([apache, '-f', str(config), '-DFOREGROUND'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True)

    def request(path, method='GET'):
        try:
            with urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}{path}', method=method), timeout=5) as r:
                return r.status, r.headers, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read()

    try:
        for _ in range(100):
            if server.poll() is not None:
                log = work / 'error.log'
                raise RuntimeError(server.communicate()[1].decode() + (log.read_text() if log.exists() else ''))
            try: request('/'); break
            except OSError: time.sleep(.05)

        # A published original is answered by Apache: the file's bytes, never the application.
        status, headers, body = request(f'/images/{sha}.png')
        assert (status, body) == (200, source.read_bytes()), (status, body[:60], (work / 'error.log').read_text() if (work / 'error.log').exists() else '')
        assert headers['Cache-Control'] == 'private, max-age=31536000, immutable', headers['Cache-Control']
        assert headers['X-Content-Type-Options'] == 'nosniff', dict(headers)
        assert headers['Content-Type'] == 'image/png', headers['Content-Type']
        assert headers['Vary'] is None, headers['Vary']

        # A published copy is answered as WebP.
        status, headers, body = request(f'/images/thumbs/{sha}-wide.png.webp')
        assert (status, body) == (200, source.read_bytes()), (status, body[:60])
        assert headers['Content-Type'] == 'image/webp', headers['Content-Type']
        assert headers['Cache-Control'] == 'private, max-age=31536000, immutable'

        status, headers, body = request(f'/images/{sha}.png', 'HEAD')
        assert status == 200 and body == b'' and 'max-age=31536000' in headers['Cache-Control']

        # Everything else still reaches the application: an image that is not published,
        # a published one asked for with a query or by POST, and names outside the pattern.
        for path, method in [(f'/images/{other}.png', 'GET'), (f'/images/thumbs/{other}-tile.png.webp', 'GET'),
                             (f'/images/{sha}.png?x=1', 'GET'), (f'/images/{sha}.png', 'POST'),
                             ('/images/.htaccess', 'GET'), (f'/images/thumbs/../{sha}.png.php', 'GET'), ('/threads/', 'GET')]:
            status, headers, body = request(path, method)
            assert b'APPLICATION' in body, (path, method, status, body[:80])
            assert 'max-age=31536000' not in (headers['Cache-Control'] or ''), (path, headers['Cache-Control'])

        # The folder is not an address, and it cannot be listed.
        for path in [f'/.published-images/{sha}.png', '/.published-images/', '/.published-images/.htaccess', f'/.PUBLISHED-images/{sha}.png']:
            status, _, body = request(path)
            assert status == 403, (path, status)
            assert body != source.read_bytes() and b'Header set' not in body, path

        # Removing the image makes its addresses fall back to the application at once.
        subprocess.run(['php', '-r', 'require "autoload.php"; (new ForumRewrite\\Images\\PublishedImages($argv[1]))->removeImage($argv[2]);', str(published), sha], cwd=project, check=True)
        for name in names:
            status, _, body = request('/images/' + name)
            assert b'APPLICATION' in body, (name, status)

        # With the folder emptied the rules do nothing.
        subprocess.run(['php', '-r', 'require "autoload.php"; (new ForumRewrite\\Images\\PublishedImages($argv[1]))->clear();', str(published)], cwd=project, check=True)
        assert not published.exists()
        status, _, body = request(f'/images/{sha}.png')
        assert b'APPLICATION' in body
        print('image direct serving through Apache: ok')
    finally:
        server.terminate()
        server.wait(timeout=10)
finally:
    shutil.rmtree(work, ignore_errors=True)
