"""Isolated Apache; no system server config or real public .htaccess is changed."""
import json, os, pathlib, pwd, shutil, socket, subprocess, tempfile, time, urllib.request, urllib.error
project = pathlib.Path(__file__).resolve().parents[1]
root = pathlib.Path(tempfile.mkdtemp(prefix='v3-high-traffic-apache-'))
public = root/'public'; public.mkdir(); repo=root/'repository'; repo.mkdir()
(repo/'records').symlink_to(project/'tests/fixtures/parity_minimal_v1/records', target_is_directory=True)
(public/'.htaccess').write_text((project/'public/.htaccess').read_text()+'\n# custom hosting rule retained\n')
(public/'index.php').write_text('<?php file_put_contents('+json.dumps(str(root/'php-count'))+', "1\\n", FILE_APPEND); require '+json.dumps(str(project/'public/index.php'))+';')
(public/'assets').symlink_to(project/'public/assets',target_is_directory=True)
(root/'secrets.php').write_text('<?php return [];')
(root/'sessions').mkdir()
modules=pathlib.Path('/usr/lib/apache2/modules'); user=pwd.getpwuid(os.getuid()).pw_name
with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
config=root/'httpd.conf'
config.write_text(f'''ServerRoot "/etc/apache2"
ServerName 127.0.0.1
Listen 127.0.0.1:{port}
PidFile "{root}/httpd.pid"
DefaultRuntimeDir "{root}"
ErrorLog "{root}/error.log"
LogLevel warn
LoadModule mpm_prefork_module {modules}/mod_mpm_prefork.so
LoadModule authz_core_module {modules}/mod_authz_core.so
LoadModule dir_module {modules}/mod_dir.so
LoadModule mime_module {modules}/mod_mime.so
LoadModule env_module {modules}/mod_env.so
LoadModule rewrite_module {modules}/mod_rewrite.so
LoadModule headers_module {modules}/mod_headers.so
LoadModule php_module {next(modules.glob('libphp*.so'))}
User {user}
Group {user}
TypesConfig /etc/mime.types
DocumentRoot "{public}"
<Directory "{public}">
Options FollowSymLinks
AllowOverride All
Require all granted
</Directory>
<FilesMatch "\\.php$">
SetHandler application/x-httpd-php
</FilesMatch>
php_admin_value session.save_path "{root}/sessions"
''')
env=dict(os.environ,FORUM_REPOSITORY_ROOT=str(repo),FORUM_DATABASE_PATH=str(root/'read.sqlite3'),FORUM_PUBLIC_ARTIFACT_ROOT=str(public),FORUM_STATIC_HTML_ROOT=str(root/'static'),FORUM_SECRETS_PATH=str(root/'secrets.php'),FORUM_SITE_ID='forum',FORUM_APPROVED_MEMBERS_ONLY='false',FAST_SCORING_ENABLED='false')
def cli(*args): return subprocess.check_output(['php',str(project/'scripts/high_traffic.php'),*args],env=env,text=True)
def request(path, method='GET', headers=None):
    try:
        with urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}'+path,method=method,headers=headers or {}),timeout=40) as r:return r.status,r.headers,r.read()
    except urllib.error.HTTPError as e:return e.code,e.headers,e.read()
def count():return len((root/'php-count').read_text().splitlines()) if (root/'php-count').exists() else 0
server=None
try:
    cli('enable')
    assert '# custom hosting rule retained' in (public/'.htaccess').read_text()
    server=subprocess.Popen(['apache2','-f',str(config),'-DFOREGROUND'],env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,start_new_session=True)
    for _ in range(100):
        if server.poll() is not None:raise RuntimeError(server.communicate()[1].decode())
        try: request('/api/version');break
        except OSError:time.sleep(.05)
    status,headers,body=request('/about/',headers={'Cookie':'PHPSESSID=client-one'})
    assert status==200,(status,body[:300],(root/'error.log').read_text())
    assert b'v3-generated-at' in body,body[:200]
    before=count()
    for cookie in ['client-one','client-two']:
        status,headers,warm=request('/about/',headers={'Cookie':'PHPSESSID='+cookie})
        assert status==200 and warm==body and headers.get('X-V3-High-Traffic')=='hit',(status,headers,warm[:200])
    assert count()==before,'warm hit invoked PHP'
    status,headers,head=request('/about/','HEAD')
    assert status==200 and head==b'' and count()==before
    for path in ['/.high-traffic/about/index.html','/.high-traffic/about/index.html?x=1']:
        assert request(path)[0]==403
    request('/about/?x=1');request('/about/',headers={'Authorization':'Bearer test'})
    assert count()==before+2
    cli('disable');request('/about/');assert count()==before+3
    private_env=dict(env,FORUM_APPROVED_MEMBERS_ONLY='true')
    denied=subprocess.run(['php',str(project/'scripts/high_traffic.php'),'enable'],env=private_env,capture_output=True)
    assert denied.returncode==1 and not (public/'.high-traffic').is_symlink()
    cli('enable');(public/'.htaccess').write_text('InvalidDirective\n');cli('restore')
    assert request('/api/version')[0]==200
    print(json.dumps({'status':'passed','checks':['Apache warm hits bypass PHP with sessions','HEAD','direct-storage denial','query/auth bypass','custom rule preservation','disable','CLI routing restore'],'fixture':str(root)}))
except Exception:
    print('Fixture:',root)
    if (root/'error.log').exists():print((root/'error.log').read_text()[-5000:])
    raise
finally:
    if server:
        server.terminate()
        try:server.communicate(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.communicate()
    # Unique local cache belongs solely to this fixture.
    import hashlib
    cache=project/'state/high_traffic'/hashlib.sha256(str(repo.resolve()).encode()).hexdigest()[:16]
    shutil.rmtree(cache,ignore_errors=True)
