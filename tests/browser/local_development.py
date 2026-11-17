"""Fresh checkout -> ./v3 start -> native import/retry -> ordinary thread pages.
Uses the already installed Python Playwright and Chrome; installs nothing.
"""
import functools
import http.server
import io
import os
from pathlib import Path
import shutil
import signal
import socket
import subprocess
import tarfile
import tempfile
import threading
import time
import urllib.request
from playwright.sync_api import sync_playwright

PROJECT = Path(__file__).resolve().parents[2]
RUNTIME = [
    'src/ForumRewrite/Application.php', 'src/ForumRewrite/Host/FrontController.php',
    'src/ForumRewrite/Support/LocalDevelopmentImport.php',
    'src/ForumRewrite/Http/LocalDevelopmentPageController.php',
    'templates/pages/local_development.php', 'public/assets/local_development.js',
]


def port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


with tempfile.TemporaryDirectory(prefix='local-start-browser-') as tmp:
    root = Path(tmp)
    checkout = root / 'checkout'
    checkout.mkdir()
    archive = root / 'checkout.tar'
    subprocess.run(['git', 'archive', '--output=' + str(archive), 'HEAD'], cwd=PROJECT, check=True)
    with tarfile.open(archive) as contents:
        contents.extractall(checkout)
    # Include this cycle's runtime edits before its completion commit.
    for relative in RUNTIME:
        shutil.copy2(PROJECT / relative, checkout / relative)
    secrets = root / 'secrets.php'
    secrets.write_text('<?php return [];')
    source_dir = root / 'source'
    source_dir.mkdir()
    record = b'Post-ID: browser-import-example\nCreated-At: 2026-11-16T12:00:00Z\nBoard-Tags: general\nSubject: A thread from another instance\n\nPortable public content.\n'
    with tarfile.open(source_dir / 'repository.tar.gz', 'w:gz') as contents:
        info = tarfile.TarInfo('source/records/posts/browser-import-example.txt')
        info.size = len(record)
        contents.addfile(info, io.BytesIO(record))
    state = {'fail': False}

    class Source(http.server.SimpleHTTPRequestHandler):
        def do_GET(self):
            if state['fail']:
                self.send_error(503, 'Test source unavailable')
            elif self.path.startswith('/downloads/repository.tar.gz'):
                self.path = '/repository.tar.gz'
                super().do_GET()
            else:
                self.send_error(404)

        def log_message(self, *_):
            pass

    remote = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Source, directory=source_dir))
    threading.Thread(target=remote.serve_forever, daemon=True).start()
    source = 'http://127.0.0.1:' + str(remote.server_port)
    environment = {k: v for k, v in os.environ.items() if not k.startswith(('FORUM_', 'FAST_', 'LLM_', 'V3_DEV_'))}
    environment.update(FORUM_SECRETS_PATH=str(secrets), FAST_SCORING_ENABLED='false', FORUM_APPROVED_MEMBERS_ONLY='false', FORUM_SITE_ID='zenmemes')
    address = '127.0.0.1:' + str(port())
    url = 'http://' + address
    log = open(root / 'server.log', 'w+')
    server = subprocess.Popen(['./v3', 'start', address, '--quiet'], cwd=checkout, env=environment, stdout=log, stderr=log, start_new_session=True)
    try:
        for attempt in range(150):
            try:
                with urllib.request.urlopen(url, timeout=1) as response:
                    assert b'Import public content' in response.read()
                    break
            except (OSError, AssertionError):
                if server.poll() is not None:
                    log.seek(0)
                    raise RuntimeError(log.read())
                time.sleep(.1)
        else:
            raise RuntimeError('Local server did not become ready')
        assert (checkout / 'state/local_repository/.git').is_dir()
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(executable_path='/usr/bin/google-chrome', headless=True, args=['--no-sandbox'])
            for scripting in [True, False]:
                context = browser.new_context(java_script_enabled=scripting, service_workers='block')
                page = context.new_page()
                page.goto(url)
                if scripting:
                    themes = page.locator('[data-theme-option]').evaluate_all('(nodes) => nodes.map(n => n.dataset.themeOption)')
                else:
                    themes = ['light']
                for width in [390, 1280]:
                    page.set_viewport_size({'width': width, 'height': 900})
                    for theme in themes:
                        if scripting:
                            page.evaluate('(theme) => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme)', theme)
                        page.goto(url)
                        assert page.get_by_role('button', name='Import', exact=True).is_visible()
                        assert page.locator('input[name=source]').input_value() == 'https://zenmemes.com'
                        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, theme)
                        if theme in ['light', 'midnight', 'bubblegum', 'gloss']:
                            page.screenshot(path=f'/tmp/local-start-{width}-{theme}-{scripting}.png', full_page=True)
                    page.get_by_role('link', name='Browse threads', exact=True).click()
                    assert 'view=all' in page.url
                    assert page.get_by_text('Hello world', exact=True).is_visible()
                    page.goto(url)
                    state['fail'] = True
                    page.locator('input[name=source]').fill(source)
                    if scripting:
                        page.evaluate("""() => {
                            const form = document.querySelector('[data-local-import]');
                            form.addEventListener('submit', event => event.preventDefault(), {once: true});
                            form.requestSubmit(form.querySelector('button'));
                        }""")
                        assert page.locator('[data-local-import-pending]').is_visible()
                        page.screenshot(path=f'/tmp/local-start-{width}-pending.png', full_page=True)
                        page.reload()
                        page.locator('input[name=source]').fill(source)
                    page.get_by_role('button', name='Import', exact=True).click()
                    page.wait_for_load_state()
                    assert page.get_by_role('alert').is_visible()
                    assert page.locator('input[name=source]').input_value() == source
                    assert page.get_by_role('button', name='Retry import').is_visible()
                    page.get_by_text('Import details', exact=True).click()
                    assert page.locator('pre').is_visible()
                    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                    page.screenshot(path=f'/tmp/local-start-{width}-failure-{scripting}.png', full_page=True)
                    state['fail'] = False
                    page.get_by_role('button', name='Retry import').click()
                    page.wait_for_load_state()
                    assert page.get_by_role('link', name='Browse imported threads').is_visible()
                    page.get_by_text('Import details', exact=True).click()
                    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                    page.screenshot(path=f'/tmp/local-start-{width}-imported-{scripting}.png', full_page=True)
                    page.get_by_role('link', name='Browse imported threads').click()
                    page.get_by_text('A thread from another instance', exact=True).first.click()
                    assert page.get_by_text('Portable public content.', exact=True).is_visible()
                    page.goto(url)
                context.close()
            browser.close()
        print('PASS fresh checkout + ./v3 start; local bootstrap; all offered themes at 390/1280; JS on/off; native download failure/retry/import/thread; expanded logs and no overflow')
    finally:
        os.killpg(server.pid, signal.SIGTERM)
        server.wait(timeout=15)
        remote.shutdown()
        remote.server_close()
        log.close()
