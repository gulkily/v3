"""Isolated Apache; no system server config or real public .htaccess is changed."""
import json, os, pathlib, pwd, shutil, socket, subprocess, tempfile, time, urllib.request, urllib.error, concurrent.futures, fcntl
project = pathlib.Path(__file__).resolve().parents[1]
root = pathlib.Path(tempfile.mkdtemp(prefix='v3-high-traffic-apache-'))
public = root/'public'; public.mkdir(); repo=root/'repository'; repo.mkdir()
shutil.copytree(project/'tests/fixtures/parity_minimal_v1/records',repo/'records')
for i in range(20):
    (repo/f'records/posts/load-{i:02}.txt').write_text(f'Post-ID: load-{i:02}\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nSubject: Load page {i}\n\nInitial body.\n')
subprocess.run(['git','init','-q',str(repo)],check=True)
subprocess.run(['git','-C',str(repo),'config','user.email','test@localhost'],check=True)
subprocess.run(['git','-C',str(repo),'config','user.name','Fixture'],check=True)
subprocess.run(['git','-C',str(repo),'add','.'],check=True)
subprocess.run(['git','-C',str(repo),'commit','-qm','Fixture'],check=True)
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
env.pop('FORUM_APPROVED_MEMBERS_ONLY',None)
def cli(*args): return subprocess.check_output(['php',str(project/'scripts/high_traffic.php'),*args,'--json'],env=env,text=True)
def request(path, method='GET', headers=None):
    try:
        with urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}'+path,method=method,headers=headers or {}),timeout=40) as r:return r.status,r.headers,r.read()
    except urllib.error.HTTPError as e:return e.code,e.headers,e.read()
def count():return len((root/'php-count').read_text().splitlines()) if (root/'php-count').exists() else 0
server=None
try:
    enabled=json.loads(cli('enable'))
    assert enabled['effective_mode']=='high-traffic' and 'high-traffic run' in enabled['cron_command']
    readable=subprocess.check_output(['php',str(project/'scripts/high_traffic.php'),'status'],env=env,text=True)
    assert 'Effective mode: high-traffic' in readable and 'not installed by this command' in readable
    assert 'high-traffic run' in readable
    schedule=json.loads(cli('cron',str(root/'access log')))
    assert "'"+str(root/'access log')+"'" in schedule['cron_command']
    assert (public/'.htaccess').read_text().startswith('# BEGIN V3 HIGH TRAFFIC')
    help_result=subprocess.run(['php',str(project/'scripts/high_traffic.php'),'--help'],env=env,capture_output=True,text=True)
    assert help_result.returncode==0 and 'cron prints installation instructions' in help_result.stdout
    invalid=subprocess.run(['php',str(project/'scripts/high_traffic.php'),'enable','--bogus'],env=env,capture_output=True,text=True)
    assert invalid.returncode==1 and 'Usage:' in invalid.stderr
    assert '# custom hosting rule retained' in (public/'.htaccess').read_text()
    server=subprocess.Popen(['apache2','-f',str(config),'-DFOREGROUND'],env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,start_new_session=True)
    for _ in range(100):
        if server.poll() is not None:raise RuntimeError(server.communicate()[1].decode())
        try: request('/api/version');break
        except OSError:time.sleep(.05)
    # Cold bursts: coordinate a page and refuse work when both generator slots are held.
    cache_pages=(public/'.high-traffic').resolve()
    slots=[open(cache_pages.parent/f'locks/slot-{i}','a') for i in range(2)]
    for slot in slots:fcntl.flock(slot,fcntl.LOCK_EX)
    status,headers,_=request('/about/')
    assert status==503 and headers.get('Retry-After')=='5'
    for slot in slots:fcntl.flock(slot,fcntl.LOCK_UN);slot.close()
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        burst=list(pool.map(lambda _:request('/about/',headers={'Cookie':'PHPSESSID=burst'}),range(8)))
    assert all(r[0] in (200,503) for r in burst) and any(r[0]==200 for r in burst)
    status,headers,body=request('/about/',headers={'Cookie':'PHPSESSID=client-one'})
    assert status==200,(status,body[:300],(root/'error.log').read_text())
    assert b'v3-generated-at' in body,body[:200]
    cache_pages=(public/'.high-traffic').resolve()
    logfile=root/'access.log'
    logfile.write_text('127.0.0.1 - - [now] "GET /about/ HTTP/1.1" 200 100 "-" "browser"\n')
    (cache_pages/'about/index.html').write_text(body.decode().replace('</head>','<!-- OLD REFRESH COPY --></head>'))
    os.utime(cache_pages/'about/index.html',(1,1))
    result=json.loads(cli('run',str(logfile)))
    assert result['rendered']==1,result
    assert b'OLD REFRESH COPY' not in (cache_pages/'about/index.html').read_bytes()
    status,headers,body=request('/about/')
    before=count()
    for cookie in ['client-one','client-two']:
        status,headers,warm=request('/about/',headers={'Cookie':'PHPSESSID='+cookie})
        assert status==200 and warm==body and headers.get('X-V3-High-Traffic')=='hit',(status,headers,warm[:200])
    assert count()==before,'warm hit invoked PHP'
    assert 'no-cache' in headers.get('Cache-Control','')
    retained=cache_pages.parent/'assets/assets/retained.012345abcdef.css';retained.parent.mkdir(parents=True,exist_ok=True);retained.write_text('body { color: navy; }')
    code,_,css=request('/assets/retained.012345abcdef.css');assert code==200 and css==b'body { color: navy; }'
    before=count()
    status,headers,head=request('/about/','HEAD')
    assert status==200 and head==b'' and count()==before
    for path in ['/.high-traffic/about/index.html','/.high-traffic/about/index.html?x=1']:
        assert request(path)[0]==403
    request('/about/?x=1');request('/about/',headers={'Authorization':'Bearer test'})
    assert count()==before+2
    assert request('/threads/nonexistent/')[0]==404
    # Real 20-page refresh workload, each page below the one-second fixture budget.
    logfile.write_text(''.join(f'127.0.0.1 - - [now] "GET /threads/load-{i:02}/ HTTP/1.1" 200 100 "-" "browser"\n' for i in range(20)))
    started=time.monotonic(); refreshed=json.loads(cli('run',str(logfile)))
    assert refreshed['rendered']==20,refreshed
    refresh_seconds=time.monotonic()-started
    assert refresh_seconds<120,refresh_seconds
    for i in range(20):assert b'Initial body.' in request(f'/threads/load-{i:02}/')[2]
    # Routine source changes remain cached until cron replaces all twenty pages.
    for i in range(20):
        record=repo/f'records/posts/load-{i:02}.txt';record.write_text(record.read_text().replace('Initial body.','Updated body.'))
        os.utime(cache_pages/f'threads/load-{i:02}/index.html',(1,1))
    subprocess.run(['git','-C',str(repo),'add','.'],check=True);subprocess.run(['git','-C',str(repo),'commit','-qm','Routine edits'],check=True)
    assert b'Initial body.' in request('/threads/load-00/')[2]
    started=time.monotonic();updated=json.loads(cli('run',str(logfile)))
    assert updated['rendered']==20,updated
    refresh_seconds=max(refresh_seconds,time.monotonic()-started)
    for i in range(20):assert b'Updated body.' in request(f'/threads/load-{i:02}/')[2]
    assert refresh_seconds<120,refresh_seconds
    # Failed refresh retains the full old page.
    old=(cache_pages/'about/index.html').read_bytes();os.utime(cache_pages/'about/index.html',(1,1))
    logfile.write_text('127.0.0.1 - - [now] "GET /about/ HTTP/1.1" 200 100 "-" "browser"\n')
    broken_env=dict(env,FORUM_PHP_CLI='/bin/false')
    failed=json.loads(subprocess.check_output(['php',str(project/'scripts/high_traffic.php'),'run',str(logfile),'--json'],env=broken_env,text=True))
    assert failed['failed']==1 and (cache_pages/'about/index.html').read_bytes()==old
    # Atomic refresh is observed only as complete old/new HTML by concurrent Apache readers.
    (cache_pages/'about/index.html').write_bytes(old.replace(b'</head>',b'<!-- old-complete --></head>'))
    os.utime(cache_pages/'about/index.html',(1,1))
    refresh=subprocess.Popen(['php',str(project/'scripts/high_traffic.php'),'run',str(logfile),'--json'],env=env,stdout=subprocess.PIPE)
    observed=[]
    while refresh.poll() is None:
        code,_,snapshot=request('/about/');assert code==200 and b'</html>' in snapshot,(code,snapshot[-200:])
        observed.append(snapshot)
    assert refresh.wait()==0
    new=(cache_pages/'about/index.html').read_bytes();old_marked=old.replace(b'</head>',b'<!-- old-complete --></head>')
    assert all(snapshot in (old_marked,new) for snapshot in observed)
    # A mode switch fences an already-running renderer before publication.
    import shlex
    wrapper=root/'slow-php';marker=root/'render-started'
    wrapper.write_text('#!/bin/sh\ntouch '+shlex.quote(str(marker))+'\nsleep 0.5\nexec /usr/bin/php "$@"\n');wrapper.chmod(0o755)
    slow_env=dict(env,FORUM_PHP_CLI=str(wrapper))
    ordinary=subprocess.Popen(['php',str(project/'scripts/high_traffic.php'),'refresh','/about/'],env=slow_env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    for _ in range(100):
        if marker.exists():break
        time.sleep(.01)
    assert marker.exists()
    ordinary_write=r'require $argv[1]."/autoload.php"; $w=new \ForumRewrite\Write\LocalWriteService($argv[2],$argv[3],$argv[4],new \ForumRewrite\Canonical\CanonicalRecordRepository($argv[2])); $w->createThread(["board_tags"=>"general","subject"=>"Routine concurrent update","body"=>"No background refresh starvation."]);'
    subprocess.run(['php','-r',ordinary_write,str(project),str(repo),str(root/'read.sqlite3'),str(public)],env=env,check=True,capture_output=True)
    ordinary.communicate(timeout=40);assert ordinary.returncode==0
    assert (cache_pages.parent/'dirty').exists()
    dirty_refresh=json.loads(cli('run',str(logfile)));assert dirty_refresh['rendered']==1,dirty_refresh
    marker.unlink()
    racing=subprocess.Popen(['php',str(project/'scripts/high_traffic.php'),'refresh','/about/'],env=slow_env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    for _ in range(100):
        if marker.exists():break
        time.sleep(.01)
    assert marker.exists()
    cli('disable');racing.communicate(timeout=40)
    assert racing.returncode==1 and not (public/'.high-traffic').is_symlink()
    before=count();request('/about/');assert count()==before+1
    # Real canonical private transition withdraws routing before the flag is committed.
    cli('enable');request('/about/')
    writer='require $argv[1]."/autoload.php"; $w=new \ForumRewrite\Write\LocalWriteService($argv[2],$argv[3],$argv[4],new \ForumRewrite\Canonical\CanonicalRecordRepository($argv[2])); $w->setFeatureFlag(["key"=>"FORUM_APPROVED_MEMBERS_ONLY","value"=>$argv[5]]);'
    def set_private(value):subprocess.run(['php','-r',writer,str(project),str(repo),str(root/'read.sqlite3'),str(public),value],env=env,check=True,capture_output=True)
    set_private('true')
    assert not (public/'.high-traffic').is_symlink()
    assert request('/about/')[1].get('X-V3-High-Traffic') is None
    set_private('false')
    # Native import retires direct serving before its publication callback.
    cli('enable');request('/about/')
    source=root/'import-source';(source/'records/posts').mkdir(parents=True)
    (source/'records/posts/imported-traffic.txt').write_text('Post-ID: imported-traffic\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nSubject: Imported traffic fixture\n\nPublic import.\n')
    import_check=r'require $argv[1]."/autoload.php"; (new \ForumRewrite\Import\ContentImportRunner($argv[2],$argv[3]))->run($argv[4],function()use($argv){if(is_link($argv[5]."/.high-traffic"))throw new RuntimeException("Import published before cache withdrawal.");});'
    subprocess.run(['php','-r',import_check,str(project),str(repo),str(root/'read.sqlite3'),str(source),str(public)],env=env,check=True,capture_output=True)
    assert not (public/'.high-traffic').is_symlink()


    private_env=dict(env,FORUM_APPROVED_MEMBERS_ONLY='true')
    denied=subprocess.run(['php',str(project/'scripts/high_traffic.php'),'enable'],env=private_env,capture_output=True)
    assert denied.returncode==1 and not (public/'.high-traffic').is_symlink()
    saved=(public/'.htaccess').read_bytes();public.chmod(0o555)
    try:
        refusal=subprocess.run(['php',str(project/'scripts/high_traffic.php'),'enable'],env=env,capture_output=True)
        assert refusal.returncode==1 and (public/'.htaccess').read_bytes()==saved
    finally:public.chmod(0o755)
    cli('enable');(public/'.htaccess').write_text('InvalidDirective\n');cli('restore')
    assert request('/api/version')[0]==200
    # Production replay: stale SQLite rows plus both retained, absent reply targets.
    ids=['reply-20260526032118-654b2b08','reply-20260526032816-a1447d49']
    for post_id in ids:
        (repo/f'records/posts/{post_id}.txt').write_text(f'Post-ID: {post_id}\nCreated-At: 2026-04-10T12:20:00Z\nBoard-Tags: general\nThread-ID: load-00\nParent-ID: load-00\n\nABSENT-TARGET-SECRET\n')
    request('/?view=all')  # Build a deliberately stale model before deleting the sources.
    # Explicit rebuild also covers the no-metadata-change case on hosts using separate repositories.
    rebuild=r'require $argv[1]."/autoload.php"; (new \ForumRewrite\ReadModel\ReadModelBuilder($argv[2],$argv[3],new \ForumRewrite\Canonical\CanonicalRecordRepository($argv[2])))->rebuild();'
    subprocess.run(['php','-r',rebuild,str(project),str(repo),str(root/'read.sqlite3')],env=env,check=True)
    import hashlib
    live_hash=hashlib.sha256((root/'read.sqlite3').read_bytes()).hexdigest()
    (repo/'records/post-retractions').mkdir(exist_ok=True)
    for post_id in ids:
        (repo/f'records/posts/{post_id}.txt').unlink()
        (repo/f'records/post-retractions/post-retraction-{post_id}.txt').write_text(f'Record-ID: post-retraction-{post_id}\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: {post_id}\nOperation: retract\nAuthor-Identity-ID: openpgp:'+('a'*40)+'\n\n')
    cli('enable')
    status,_,home=request('/')
    assert status==200 and b'v3-generated-at' in home and b'ABSENT-TARGET-SECRET' not in home
    status,_,thread=request('/threads/load-00/')
    assert status==200 and b'Updated body.' in thread and b'ABSENT-TARGET-SECRET' not in thread
    before=count()
    for route in ['/', '/threads/load-00/']:
        code,headers,body=request(route)
        assert code==200 and headers.get('X-V3-High-Traffic')=='hit' and b'ABSENT-TARGET-SECRET' not in body
    assert count()==before,'absent-target warm hits invoked PHP'
    old_home=home
    time.sleep(1.05)
    assert json.loads(cli('refresh','/'))['refreshed']=='/'
    code,headers,home=request('/')
    assert code==200 and headers.get('X-V3-High-Traffic')=='hit' and home!=old_home
    assert b'ABSENT-TARGET-SECRET' not in home
    assert live_hash==hashlib.sha256((root/'read.sqlite3').read_bytes()).hexdigest()
    assert not list(cache_pages.parent.glob('.render-*'))
    cli('disable')
    assert request('/')[0]==200
    print(json.dumps({'status':'passed','checks':['Apache warm hits bypass PHP with sessions','HEAD','direct-storage denial','query/auth bypass','custom rule preservation','disable','CLI routing restore','cold capacity and burst','20-page source refresh','failed refresh retention','old/new atomic readers','obsolete renderer rejection','routine writes do not cancel refresh','canonical private transition','import withdrawal before publication','both production absent-target IDs with stale SQLite rows','excluded-model cold/warm/refresh without live-model mutation'],'fixture':str(root),'twenty_page_refresh_seconds':round(refresh_seconds,3)}))
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
