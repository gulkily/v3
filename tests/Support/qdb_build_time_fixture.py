#!/usr/bin/env python3
"""Prepare fixed Git workloads outside timing; stdlib only, refuses existing roots."""
import pathlib, subprocess, sys, shutil
root = pathlib.Path(sys.argv[1]); root.mkdir()
source = pathlib.Path(__file__).resolve().parents[1] / 'fixtures/parity_minimal_v1'
for kind in ('ordinary', 'large', 'edge'):
    repo = root / kind
    shutil.copytree(source, repo)
    def git(*args):
        return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()
    git('init', '-b', 'main'); git('config', 'user.name', 'Build fixture'); git('config', 'user.email', 'fixture@example.invalid')
    git('config', 'gc.auto', '0'); git('add', '.'); git('commit', '-qm', 'initial')
    if kind == 'ordinary':
        # Fast-import avoids including 1000 shell startup costs in fixture preparation.
        with subprocess.Popen(['git', '-C', str(repo), 'fast-import', '--quiet'], stdin=subprocess.PIPE) as p:
            for i in range(1000):
                body = ''.join(f'{i % 2}:{n}: substantial changing text for diff computation\n' for n in range(4000)).encode()
                message = f'ordinary {i}'.encode()
                header = f'commit refs/heads/main\ncommitter Build fixture <fixture@example.invalid> {1700000000+i} +0000\ndata {len(message)}\n'.encode() + message + b'\n'
                if i == 0: header += f'from {git("rev-parse", "HEAD")}\n'.encode()
                p.stdin.write(header + b'M 100644 inline history.txt\n' + f'data {len(body)}\n'.encode() + body + b'\n')
            p.stdin.close()
            if p.wait(): raise RuntimeError('fast-import failed')
        git('reset', '--hard', 'HEAD')
    elif kind == 'large':
        directory = repo / 'records/posts'; directory.mkdir(exist_ok=True)
        for i in range(20000): (directory / f'bulk-{i:05d}.txt').write_text(f'Fixture: {i}\n')
        git('add', '.'); git('commit', '-qm', 'large import')
    else:
        for name in ('plain', 'space name', 'tab\tname', 'new\nline', 'ümlaut'):
            (repo/name).write_text('one\ntwo\n')
        (repo/'binary').write_bytes(b'\x00\x01')
        git('add', '.'); git('commit', '-qm', 'files')
        git('mv', 'plain', 'renamed'); (repo/'binary').write_bytes(b'\x00\x02')
        git('add', '.'); git('commit', '-qm', 'rename binary'); git('rm', 'space name'); git('commit', '-qm', 'delete')
        git('commit', '--allow-empty', '-qm', 'empty')
        git('checkout', '-b', 'side'); (repo/'side').write_text('side'); git('add', '.'); git('commit', '-qm', 'side')
        git('checkout', 'main'); (repo/'main').write_text('main'); git('add', '.'); git('commit', '-qm', 'main')
        git('merge', '--no-ff', 'side', '-m', 'merge')
for kind in ('empty', 'missing'):
    repo = root / kind; repo.mkdir()
    if kind == 'empty': subprocess.run(['git', '-C', str(repo), 'init', '-q'], check=True)
print(root)
