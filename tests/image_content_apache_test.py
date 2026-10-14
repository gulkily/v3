"""Run against a fixture retained by image_content_browser.mjs; never touches system Apache."""
import json, os, pathlib, pwd, socket, subprocess, sys, time, urllib.request, urllib.error

project = pathlib.Path(__file__).resolve().parents[1]
root = pathlib.Path(sys.argv[1]).resolve()
work = root / 'apache-check'
work.mkdir(exist_ok=True)
modules = pathlib.Path('/usr/lib/apache2/modules')
php_module = next(modules.glob('libphp*.so'))
user = pwd.getpwuid(os.getuid()).pw_name
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
config = work / 'httpd.conf'
config.write_text(f'''ServerRoot "/etc/apache2"
ServerName 127.0.0.1
Listen 127.0.0.1:{port}
PidFile "{work}/httpd.pid"
DefaultRuntimeDir "{work}"
ErrorLog "{work}/error.log"
LogLevel warn
LoadModule mpm_prefork_module {modules}/mod_mpm_prefork.so
LoadModule authz_core_module {modules}/mod_authz_core.so
LoadModule authz_host_module {modules}/mod_authz_host.so
LoadModule dir_module {modules}/mod_dir.so
LoadModule mime_module {modules}/mod_mime.so
LoadModule env_module {modules}/mod_env.so
LoadModule rewrite_module {modules}/mod_rewrite.so
LoadModule php_module {php_module}
User {user}
Group {user}
TypesConfig /etc/mime.types
DocumentRoot "{project}/public"
<Directory "{project}/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
php_admin_value upload_max_filesize 6M
php_admin_value post_max_size 8M
php_admin_value session.save_path "{root}/sessions"
''')
repo = root / 'restore/repository'
image = next((root / 'restore/repository-images').glob('*.png'))
static = work / 'static/current'
static.mkdir(parents=True, exist_ok=True)
(static / 'index.html').write_text('<body data-image-content-state="1">APACHE_IMAGE_SNAPSHOT</body>')
(root / 'unused.php').write_text('<?php return [];')
base_env = dict(os.environ, FORUM_REPOSITORY_ROOT=str(repo), FORUM_DATABASE_PATH=str(root / 'restore/read.sqlite3'),
                FORUM_STATIC_HTML_ROOT=str(work / 'static'), FORUM_PUBLIC_ARTIFACT_ROOT=str(work / 'artifacts'),
                FORUM_SECRETS_PATH=str(root / 'unused.php'), FORUM_SITE_ID='qdb',
                FAST_SCORING_ENABLED='false', PHP_INI_SCAN_DIR='/etc/php/8.1/apache2/conf.d')
base_env.pop('FORUM_IMAGE_ROOT', None)
def request(path, data=None, headers={}):
    try:
        with urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}'+path, data=data, headers=headers), timeout=10) as r:
            return r.status, r.headers, r.read()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read()

for enabled, members in [(True,False),(False,False),(True,True)]:
    env=dict(base_env, FORUM_IMAGE_CONTENT_ENABLED=str(enabled).lower(), FORUM_APPROVED_MEMBERS_ONLY=str(members).lower())
    server=subprocess.Popen(['apache2','-f',str(config),'-DFOREGROUND'], env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True)
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError(server.communicate()[1].decode() + (work/'error.log').read_text())
            try: request('/api/version'); break
            except OSError: time.sleep(.05)
        status, headers, body = request('/images/'+image.name)
        if enabled and not members:
            assert status == 200, (status,body)
            assert body == image.read_bytes()
            assert headers['X-Content-Type-Options'] == 'nosniff'
            assert 'no-store' in headers['Cache-Control']
        else:
            assert status != 200, status
        status, _, body = request('/')
        assert (b'APACHE_IMAGE_SNAPSHOT' in body) == (enabled and not members), (enabled,members,status,body[:100])
        status, _, body = request('/downloads/images.tar')
        assert (status == 200) == (not members), (status,body[:100])
        status, _, _ = request('/.static/current/index.html')
        assert status != 200
        if enabled and not members:
            slug = next((repo / 'records/approval-seeds').glob('openpgp-*.txt')).stem
            status, _, profile = request('/api/get_profile?profile_slug=' + slug)
            assert status == 200 and b'Image-Upload-Allowed: yes' in profile, (status, profile)
            # PHP rejects oversized multipart requests before any canonical publication.
            before=subprocess.check_output(['git','-C',str(repo),'rev-parse','HEAD'])
            payload=b'--image-boundary\r\nContent-Disposition: form-data; name="image"; filename="huge.png"\r\nContent-Type: image/png\r\n\r\n'+b'x'*(9*1024*1024)+b'\r\n--image-boundary--\r\n'
            status, _, body=request('/api/create_prepared_post',payload,{'Content-Type':'multipart/form-data; boundary=image-boundary'})
            assert status == 413, (status,body[:100])
            assert b'server upload limit' in body
            assert subprocess.check_output(['git','-C',str(repo),'rev-parse','HEAD']) == before
    finally:
        server.terminate()
        try: server.communicate(timeout=10)
        except subprocess.TimeoutExpired: server.kill(); server.communicate()
print(json.dumps({'status':'passed','server':'Apache/mod_php','checks':['image bytes/headers','flag/membership gate','static flag mismatch','flag-off backup','oversized upload','blocked direct static paths']}))
