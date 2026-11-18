"""Fresh checkout -> ./v3 start -> queued import with live steps -> retry -> ordinary thread pages.
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
    'src/ForumRewrite/Http/LocalDevelopmentPageController.php', 'src/ForumRewrite/Http/ImportController.php',
    'src/ForumRewrite/Import/InstanceImportRequests.php', 'src/ForumRewrite/Import/LastImportSummary.php',
    'src/ForumRewrite/View/TemplateRenderer.php',
    'templates/pages/local_development.php', 'templates/pages/tools_import.php',
    'templates/partials/import_run.php', 'public/assets/import_control.js',
    'public/assets/local_message_pull.js',
    'src/ForumRewrite/Support/LocalDevelopmentRemote.php', 'src/ForumRewrite/Http/LocalDevelopmentRemoteController.php',
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
    state = {'fail': False, 'delay': 0}

    class Source(http.server.SimpleHTTPRequestHandler):
        def do_GET(self):
            if state['fail']:
                self.send_error(503, 'Test source unavailable')
            elif self.path.startswith('/downloads/repository.tar.gz'):
                time.sleep(state['delay'])  # keeps the Downloading step on screen long enough to check
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
            imported_once = False
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
                        # The card keeps the last import's result, so after one the button reads Import again.
                        assert page.locator('[data-import-form] button[type=submit]').is_visible()
                        if not imported_once:
                            assert page.get_by_role('button', name='Import', exact=True).is_visible()
                            assert page.locator('input[name=source]').input_value() == 'https://zenmemes.com'
                        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, theme)
                        if theme in ['light', 'midnight', 'bubblegum', 'gloss']:
                            page.screenshot(path=f'/tmp/local-start-{width}-{theme}-{scripting}.png', full_page=True)
                    page.get_by_role('link', name='Browse threads', exact=True).click()
                    assert 'view=all' in page.url
                    # Wait for the board: is_visible() right after a click races the navigation.
                    page.get_by_text('Hello world', exact=True).first.wait_for(timeout=10000)
                    page.goto(url)
                    state['fail'] = True
                    if imported_once:
                        # A copy that has imported: one action, a stated cost, and the source folded away.
                        assert page.get_by_role('button', name='Check for new content').is_visible()
                        assert 'Downloads everything again' in page.locator('[data-import-form]').inner_text()
                        assert page.locator('[data-import-last]').is_visible()
                        change = page.locator('details', has=page.get_by_text('Change source', exact=True))
                        assert not change.locator('input[name=source]').is_visible()
                        page.get_by_text('Change source', exact=True).click()
                        change.locator('input[name=source]').fill(source)
                        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, 'change source')
                        page.screenshot(path=f'/tmp/local-start-{width}-status-{scripting}.png', full_page=True)
                        change.get_by_role('button', name='Import', exact=True).click()
                    else:
                        page.locator('input[name=source]').fill(source)
                        page.locator('[data-import-form] button[type=submit]').click()
                    had_status = imported_once
                    imported_once = True
                    # The failed download ends in the reason, the kept source and Retry, with or without scripts.
                    page.get_by_role('alert').wait_for(timeout=40000)
                    assert 'Retry or resume below.' in page.get_by_role('alert').inner_text()
                    assert page.locator('[data-import-form] input[name=source]').input_value() == source
                    assert page.get_by_role('button', name='Retry import').is_visible()
                    # The last good import is still reported under the failure.
                    assert page.locator('[data-import-last]').count() == (1 if had_status else 0)
                    page.get_by_text('Import details', exact=True).first.click()
                    assert page.locator('pre').is_visible()
                    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                    page.screenshot(path=f'/tmp/local-start-{width}-failure-{scripting}.png', full_page=True)
                    state['fail'] = False
                    state['delay'] = 10 if scripting else 12
                    started = time.monotonic()
                    page.get_by_role('button', name='Retry import').click()
                    current = page.locator('.import-steps li.is-current')
                    current.first.wait_for(timeout=2000)
                    assert time.monotonic() - started < 2, 'steps should show within 2 seconds of the click'
                    assert not page.locator('[data-import-form]').is_visible()
                    # The earlier failure and its details give way to the steps.
                    assert not page.get_by_role('alert').is_visible()
                    assert not page.get_by_text('Import details', exact=True).first.is_visible()
                    assert not page.locator('[data-import-last]').is_visible()
                    assert page.get_by_text('Keep this page open').count() == 0
                    if scripting:
                        # Live: the step list reaches Downloading and the timer ticks between polls.
                        page.locator('.import-steps li.is-current', has_text='Downloading').wait_for(timeout=8000)
                        timing = page.locator('[data-import-timing]')
                        before = timing.inner_text()
                        page.wait_for_timeout(2500)  # ticks are per second and not aligned with the 2-second poll
                        assert timing.inner_text() != before, 'timer did not tick'
                        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, 'running')
                        page.screenshot(path=f'/tmp/local-start-{width}-running.png', full_page=True)
                        # Other pages answer mid-import, and a reload shows the same step.
                        began = time.monotonic()
                        with urllib.request.urlopen(url + '/threads/?view=all', timeout=5) as response:
                            assert response.status == 200
                        assert time.monotonic() - began < 2, 'a page should load while the import runs'
                        page.reload()
                        assert 'Downloading' in page.locator('.import-steps li.is-current').inner_text()
                    else:
                        # Without scripts nothing polls or ticks: the page must reload itself to move on.
                        before = page.locator('[data-import-timing]').inner_text()
                        page.locator('.import-steps li.is-current', has_text='Downloading').wait_for(timeout=12000)
                        deadline = time.monotonic() + 12
                        while page.locator('[data-import-timing]').inner_text() == before:
                            assert time.monotonic() < deadline, 'the page did not reload itself'
                            page.wait_for_timeout(500)
                    page.locator('[data-import-root][data-import-busy="0"] [data-import-last]').wait_for(timeout=60000)
                    state['delay'] = 0
                    assert page.get_by_role('alert').count() == 0
                    # The first pass imports the record; later passes find nothing new.
                    status = page.locator('[data-import-last]').inner_text()
                    assert status.startswith('Imported from 127.0.0.1:') and 'just now' in status, status
                    assert status.endswith('1 imported') or status.endswith('nothing new'), status
                    assert page.get_by_role('link', name='Browse imported threads').is_visible()
                    assert page.get_by_role('button', name='Check for new content').is_visible()
                    page.get_by_text('Import details', exact=True).first.click()
                    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                    page.screenshot(path=f'/tmp/local-start-{width}-imported-{scripting}.png', full_page=True)
                    page.get_by_role('link', name='Browse imported threads').click()
                    page.get_by_text('A thread from another instance', exact=True).first.click()
                    assert page.get_by_text('Portable public content.', exact=True).is_visible()
                    page.goto(url)
                context.close()
            browser.close()
        print('PASS fresh checkout + ./v3 start; local bootstrap; all offered themes at 390/1280; JS on/off; download failure, retry with live steps, ticking timer, usable site mid-import, reload, self-reload without scripts, last-import status, one-click re-import, Change source, imported thread; no overflow')
    finally:
        os.killpg(server.pid, signal.SIGTERM)
        server.wait(timeout=15)
        remote.shutdown()
        remote.server_close()
        log.close()
