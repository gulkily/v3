#!/usr/bin/env python3
"""Real CLI/HTTP/PTY regressions. Run with Python 3 and a PHP CLI on PATH.

Only temporary projects/configurations are changed. Shared PDO/mbstring modules
permit missing-extension cases; statically compiled modules are reported as skips.
"""
import hashlib
import json
import os
from pathlib import Path
import pty
import select
import shlex
import shutil
import signal
import socket
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


def run(args, env):
    return subprocess.run(args, env=env, input='yes\n', capture_output=True, text=True, timeout=20)


def snapshot(root):
    return {str(p.relative_to(root)): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in root.rglob('*') if p.is_file() and not p.is_symlink()}


def listening(port):
    with socket.socket() as connection:
        connection.settimeout(.1)
        return connection.connect_ex(('127.0.0.1', port)) == 0


def request(port, path, method='GET'):
    req = urllib.request.Request(f'http://127.0.0.1:{port}{path}', method=method,
                                 data=b'' if method == 'POST' else None)
    with urllib.request.urlopen(req, timeout=20) as response:
        assert response.status == 200
        return response.read().decode()


def terminal_report(command, env):
    master, slave = pty.openpty()
    process = subprocess.Popen(command, env=env, stdin=subprocess.DEVNULL,
                               stdout=slave, stderr=slave)
    os.close(slave)
    chunks = []
    deadline = time.monotonic() + 20
    try:
        while time.monotonic() < deadline:
            if select.select([master], [], [], .1)[0]:
                try:
                    chunk = os.read(master, 65536)
                except OSError:
                    break
                if not chunk:
                    break
                chunks.append(chunk)
            elif process.poll() is not None:
                break
        process.wait(timeout=1)
        return process.returncode, b''.join(chunks)
    finally:
        if process.poll() is None:
            process.kill()
            process.wait()
        os.close(master)


def main():
    php = shutil.which('php')
    if not php:
        raise SystemExit('Test setup requires PHP CLI on PATH; no runtime is installed automatically.')
    identity = json.loads(subprocess.check_output([php, '-r',
        'echo json_encode([PHP_BINARY, ini_get("extension_dir"), getenv("LD_LIBRARY_PATH")]);']))
    binary, extension_dir, library_path = identity
    env = dict(os.environ)
    if library_path:
        env['LD_LIBRARY_PATH'] = library_path
    static_modules = json.loads(subprocess.check_output([binary, '-n', '-r',
        'echo json_encode(get_loaded_extensions());'], env=env))

    with tempfile.TemporaryDirectory(prefix='v3-dependency-integration-') as work:
        fixture = Path(work)
        project = fixture / 'project'
        project.mkdir()
        shutil.copy2(ROOT / 'v3', project / 'v3')
        shutil.copy2(ROOT / 'autoload.php', project / 'autoload.php')
        shutil.copytree(ROOT / 'scripts', project / 'scripts')
        for directory in ('src', 'templates', 'tests'):
            (project / directory).symlink_to(ROOT / directory, target_is_directory=True)
        (project / 'public').mkdir()
        for path in (ROOT / 'public').iterdir():
            target = project / 'public' / path.name
            if path.suffix == '.php':
                shutil.copy2(path, target)
            else:
                target.symlink_to(path, target_is_directory=path.is_dir())
        (fixture / 'bin').mkdir()
        config = fixture / 'php.ini'
        wrapper = fixture / 'bin' / 'php'
        wrapper.write_text('#!/bin/sh\nexport PHPRC=' + shlex.quote(str(config))
            + '\nexport PHP_INI_SCAN_DIR=\nexec ' + shlex.quote(binary) + ' "$@"\n')
        wrapper.chmod(0o755)
        for tool in ('bash', 'dirname', 'cat', 'git'):
            (fixture / 'bin' / tool).symlink_to(shutil.which(tool))
        (fixture / 'bin' / 'uname').symlink_to(shutil.which('uname'))
        install_marker = fixture / 'unexpected-installation'
        for tool in ('apt-get', 'sudo'):
            installer = fixture / 'bin' / tool
            installer.write_text('#!/usr/bin/env bash\nprintf attempted >'
                                 + shlex.quote(str(install_marker)) + '\nexit 99\n')
            installer.chmod(0o755)
        env.update(PATH=str(fixture / 'bin') + ':' + env['PATH'],
                   FORUM_SITE_ID='zenmemes', FORUM_SECRETS_PATH=str(fixture / 'no-secrets.php'),
                   DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED='false', FAST_SCORING_ENABLED='false')
        # Use default paths beneath the isolated project, never the caller's state.
        for key in ('FORUM_REPOSITORY_ROOT', 'FORUM_DATABASE_PATH', 'FORUM_STATIC_HTML_ROOT',
                    'FORUM_TASK_QUEUE_DATABASE_PATH', 'VISITOR_STATISTICS_DATABASE_PATH',
                    'LLM_EXCHANGE_DATABASE_PATH', 'PRIVATE_MESSAGE_DATABASE_PATH'):
            env.pop(key, None)
        extensions = ['pdo', 'pdo_sqlite', 'mbstring', 'ctype', 'fileinfo', 'iconv']

        def configure(omit=(), disabled='', intl=False):
            selected = extensions + (['intl'] if intl else [])
            lines = ['extension_dir=' + extension_dir]
            for extension in selected:
                if extension not in omit and extension.lower() not in [x.lower() for x in static_modules]:
                    module = Path(extension_dir) / (extension + '.so')
                    if extension == 'intl' and not module.is_file():
                        continue  # Optional intl is not a test setup requirement.
                    if not module.is_file():
                        raise AssertionError(f'Test setup needs shared extension: {module}')
                    lines.append('extension=' + extension + '.so')
            lines.append('disable_functions=' + disabled)
            config.write_text('\n'.join(lines) + '\n')

        cases = [('PDO extension', ('pdo', 'pdo_sqlite'), ''),
                 ('PDO SQLite driver', ('pdo_sqlite',), ''),
                 ('mbstring extension', ('mbstring',), ''),
                 ('Server process functions unavailable: proc_open', (), 'proc_open'),
                 ('Server process functions unavailable: proc_terminate', (), 'proc_terminate'),
                 ('Local Git shell functions', (), 'exec'),
                 ('Local Git shell functions', (), 'escapeshellarg'),
                 ('mbstring functions unavailable: mb_ord', (), 'mb_ord')]
        before = snapshot(project)
        for message, omitted, disabled in cases:
            if any(x.lower() in [m.lower() for m in static_modules] for x in omitted):
                print('SKIP: statically compiled extension prevents removal:', omitted)
                continue
            configure(omit=omitted, disabled=disabled)
            for command in ('check-dependencies', 'dependency-check', 'start'):
                args = [str(project / 'v3'), command]
                if command == 'start':
                    args += ['8123', '--quiet', '--log-file=' + str(project / 'not-created.log')]
                result = run(args, env)
                text = result.stdout + result.stderr
                assert result.returncode == 1 and message in text, (command, message, text)
                assert str(config) in text and 'Selected PHP CLI:' in text, text
                assert 'Fatal error' not in text, text
                if command != 'start':
                    assert '✗ FAIL:' in result.stdout and not result.stderr, text
                    assert '\x1b' not in text, 'Redirected report should contain no color escapes'
                    assert text.index('✗ FAIL:') < text.index('Result: NOT READY') < text.index('Recovery guidance'), text
                assert snapshot(project) == before, 'Failed preflight changed project state'
                assert not install_marker.exists(), 'Unattended preflight invoked an installer'
            print('PASS: both entry points reject', message, 'without state changes')

        configure(omit=('pdo_sqlite', 'mbstring', 'intl'))
        concise = run([str(wrapper), str(project / 'scripts/check_local_server_dependencies.php'),
                       'startup', '1', 'ubuntu-24.04'], env)
        assert concise.returncode == 1 and not concise.stdout, concise
        assert '✗ FAIL: PDO SQLite driver\n' in concise.stderr, concise.stderr
        assert '✗ FAIL: mbstring extension\n' in concise.stderr, concise.stderr
        assert 'combining marks are rejected' in concise.stderr, concise.stderr
        if '8.3.' in concise.stderr:
            assert 'sudo apt-get install php8.3-sqlite3 php8.3-mbstring\n' in concise.stderr, concise.stderr
            assert 'Optional Unicode normalization:\n  sudo apt-get install php8.3-intl' in concise.stderr, concise.stderr
            assert concise.stderr.count('administrator privileges') == 1, concise.stderr
        assert 'same options' in concise.stderr and './v3 check-dependencies' in concise.stderr
        startup_args = [str(wrapper), str(project / 'scripts/check_local_server_dependencies.php'),
                        'startup', '1', 'ubuntu-24.04']
        startup_env = dict(env)
        startup_env.pop('NO_COLOR', None)
        code, startup_colored = terminal_report(startup_args, startup_env)
        assert code == 1 and b'\x1b[31m' in startup_colored, startup_colored
        if '8.3.' in concise.stderr:
            assert b'\x1b[1;36msudo apt-get install php8.3-sqlite3 php8.3-mbstring\x1b[0m' in startup_colored
        code, startup_plain = terminal_report(startup_args, {**startup_env, 'NO_COLOR': ''})
        assert code == 1 and b'\x1b' not in startup_plain, startup_plain
        print('PASS: concise startup groups required installs separately from optional recovery')

        # Both sample failure cases remain scannable; commands stand out on a terminal.
        configure(omit=('pdo_sqlite', 'mbstring'), disabled='exec')
        color_env = dict(env)
        color_env.pop('NO_COLOR', None)
        code, colored = terminal_report([str(project / 'v3'), 'check-dependencies'], color_env)
        assert code == 1 and b'\x1b[31m' in colored, colored
        assert b'\x1b[1;36mphp --ini\x1b[0m' in colored, colored
        if 'Ubuntu 24.04/APT' in colored.decode() and '✗ FAIL: PDO SQLite driver' in colored.decode():
            assert b'\x1b[1;36msudo apt-get install php8.3-sqlite3\x1b[0m' in colored, colored
        code, plain = terminal_report([str(project / 'v3'), 'check-dependencies'], {**color_env, 'NO_COLOR': ''})
        assert code == 1 and b'\x1b' not in plain, plain
        assert '✗ FAIL:' in plain.decode() and 'Recovery guidance' in plain.decode(), plain
        parked_php = fixture / 'parked-php'
        wrapper.rename(parked_php)
        try:
            code, missing = terminal_report([str(project / 'v3'), 'check-dependencies'],
                                             {**color_env, 'PATH': str(fixture / 'bin')})
            missing_text = missing.decode()
            assert code == 1 and '✗ FAIL: PHP CLI' in missing_text, missing_text
            assert '? SKIP: PDO SQLite driver' in missing_text, missing_text
            assert missing_text.index('Result: NOT READY') < missing_text.index('Recovery guidance'), missing_text
            if 'Ubuntu 24.04 with APT' in missing_text:
                assert b'\x1b[1;36msudo apt-get update\x1b[0m' in missing, missing
        finally:
            parked_php.rename(wrapper)
        assert snapshot(project) == before
        print('PASS: terminal status colors, highlighted commands, and NO_COLOR fallback')

        configure()
        report = run([str(project / 'v3'), 'check-dependencies'], env)
        assert report.returncode == 0, report.stdout + report.stderr
        assert 'OK: In-memory SQLite opening' in report.stdout
        assert 'OK: Git execution from PHP' in report.stdout
        assert snapshot(project) == before, 'Healthy report changed state'
        assert not install_marker.exists(), 'Healthy report invoked an installer'
        # Readiness must not mistake application/database errors for missing tools.
        (project / 'state').mkdir()
        corrupt = project / 'state' / 'corrupt.sqlite'
        corrupt.write_bytes(b'not an SQLite database')
        corrupt_env = {**env, 'FORUM_DATABASE_PATH': str(corrupt),
                       'FORUM_REPOSITORY_ROOT': str(fixture / 'invalid-checkout')}
        before = snapshot(project)
        assert run([str(project / 'v3'), 'check-dependencies'], corrupt_env).returncode == 0
        assert snapshot(project) == before
        print('PASS: healthy/restored report is read-only and independent of checkout/database errors')

        lifecycles = [('normal', '', True, False),
                      ('no-pcntl', 'pcntl_async_signals,pcntl_signal', False, False),
                      ('no-shell', 'shell_exec', False, False),
                      ('no-pcntl-shell', 'pcntl_async_signals,pcntl_signal,shell_exec', False, False),
                      ('no-stty', '', False, True)]
        for name, disabled, intl, no_stty in lifecycles:
            configure(disabled=disabled, intl=intl)
            case_env = {**env, 'PATH': str(fixture / 'bin') if no_stty else env['PATH']}
            report = run([str(project / 'v3'), 'check-dependencies'], case_env)
            assert report.returncode == 0, report.stdout + report.stderr
            if no_stty:
                assert 'WARN: Optional stty' in report.stdout
            if disabled:
                assert 'WARN: Optional' in report.stdout
            if not intl and 'intl' not in static_modules:
                assert 'combining marks are rejected' in report.stdout
            for terminal in (False, True):
                for stop_signal in (signal.SIGINT, signal.SIGTERM):
                    with socket.socket() as reserved:
                        reserved.bind(('127.0.0.1', 0))
                        port = reserved.getsockname()[1]
                    log = fixture / f'{name}-{terminal}-{stop_signal} log with spaces.txt'
                    args = [str(project / 'v3'), 'start', str(port), '--verbose', '--log-file=' + str(log)]
                    if terminal:
                        master, slave = pty.openpty()
                        process = subprocess.Popen(args, env=case_env, stdin=slave, stdout=slave,
                                                   stderr=slave, start_new_session=True)
                        os.close(slave)
                    else:
                        process = subprocess.Popen(args, env=case_env, stdout=subprocess.PIPE,
                                                   stderr=subprocess.PIPE, start_new_session=True)
                    output = b''
                    try:
                        for _ in range(150):
                            if listening(port):
                                break
                            assert process.poll() is None, 'Launcher exited before serving'
                            time.sleep(.02)
                        assert listening(port), 'Server failed to listen'
                        # Every capability combination completes ordinary local reads and writes.
                        assert 'Hello world' in request(port, '/threads/root-001')
                        body = urllib.parse.urlencode({'board_tags': 'general', 'subject': 'Dependency recovery',
                                                       'body': 'Local dependency check write'})
                        created = request(port, '/api/create_thread?' + body, 'POST')
                        fields = dict(line.split('=', 1) for line in created.splitlines() if '=' in line)
                        assert fields.get('status') == 'ok', created
                        assert len(fields['commit_sha']) == 40
                        thread_id = fields['thread_id']
                        assert 'Dependency recovery' in request(port, '/threads/' + thread_id)
                        reply = request(port, '/api/create_reply?' + urllib.parse.urlencode({
                            'thread_id': thread_id, 'parent_id': thread_id, 'body': 'Local recovery reply'}), 'POST')
                        assert 'status=ok' in reply, reply
                        assert 'Local recovery reply' in request(port, '/threads/' + thread_id)
                        # Redirected runs signal the launcher; PTY runs signal its process group.
                        (os.killpg if terminal else os.kill)(process.pid, stop_signal)
                        process.wait(timeout=7)
                        if terminal:
                            while select.select([master], [], [], .1)[0]:
                                try:
                                    chunk = os.read(master, 65536)
                                except OSError:
                                    break
                                if not chunk:
                                    break
                                output += chunk
                        else:
                            out, err = process.communicate()
                            output = out + err
                        assert process.returncode == 0, output.decode(errors='replace')
                        assert not listening(port), 'Shutdown left a listening child'
                        assert b'stopped' in output, output
                        plain = log.read_bytes()
                        assert b'\x1b' not in plain and b'/api/create_thread' in plain, plain
                        if terminal and (disabled or no_stty):
                            assert b'\x1b[2J' not in output, output
                        print('PASS:', name, 'terminal' if terminal else 'redirected',
                              signal.Signals(stop_signal).name, 'HTTP reads/writes, plain log, clean shutdown')
                    finally:
                        if process.poll() is None:
                            process.terminate()
                            try:
                                process.wait(timeout=5)
                            except subprocess.TimeoutExpired:
                                process.kill()
                                process.wait()
                        if terminal:
                            os.close(master)


if __name__ == '__main__':
    main()
