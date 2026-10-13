#!/usr/bin/env python3
"""Follow the actual manual recovery text in an existing disposable test chroot.

Run as root with --work-dir created by startup recovery integration tests.
Only that rootfs is modified; preserves output as manual acceptance evidence.
"""
import argparse
import json
import os
from pathlib import Path
import re
import shutil

from local_server_dependency_install_integration_test import SOURCE
from startup_dependency_recovery_integration_test import StartupTarget


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--work-dir', required=True, type=Path)
    args = parser.parse_args()
    assert os.geteuid() == 0
    work = args.work_dir.resolve()
    assert (work / 'evidence.json').is_file() and (work / 'rootfs/work/v3').is_file()
    t = StartupTarget.__new__(StartupTarget)
    t.work = work; t.root = work / 'rootfs'; t.logs = work / 'manual-logs'; t.results = []
    t.logs.mkdir(exist_ok=False)
    shutil.copy2(SOURCE / 'v3', t.root / 'work/v3')
    shutil.copytree(SOURCE / 'scripts', t.root / 'work/scripts', dirs_exist_ok=True)
    series = '8.1' if 'VERSION_ID="22.04"' in (t.root / 'etc/os-release').read_text() else '8.3'
    t.shell('remove-runtime', 'apt-get remove -y php8.3-cli php8.3-sqlite3 php8.3-mbstring')
    for alias in ('start', 'check-dependencies', 'dependency-check'):
        text = t.run('missing-php-' + alias, ['/work/v3', alias], 1)
        command = re.search(r'sudo apt-get install php[0-9.]+-cli php[0-9.]+-sqlite3 php[0-9.]+-mbstring', text)
        assert command, text
        assert 'OS/release: ubuntu' in text
    # Run the emitted command; omit sudo because this is an existing root shell.
    t.shell('follow-missing-php-recipe', 'apt-get update -o APT::Update::Error-Mode=any; DEBIAN_FRONTEND=noninteractive ' + command[0].removeprefix('sudo ') + ' -y')
    t.run('manual-runtime-ready', ['/work/v3', 'check-dependencies'])
    t.start('manual-runtime-usable', '', sudo=True, recovery=False)
    t.shell('remove-extensions', 'apt-get remove -y php8.3-sqlite3 php8.3-mbstring')
    text = t.run('missing-extensions-start', ['/work/v3', 'start'], 1)
    command = re.search(r'sudo apt-get install php[0-9.]+-sqlite3 php[0-9.]+-mbstring', text)
    assert command and 'Optional Unicode normalization:' in text, text
    for alias in ('check-dependencies', 'dependency-check'):
        text = t.run('missing-extensions-' + alias, ['/work/v3', alias], 1)
        assert './v3 check-dependencies --install' in text
    t.shell('follow-missing-extension-recipe', 'DEBIAN_FRONTEND=noninteractive ' + command[0].removeprefix('sudo ') + ' -y')
    t.run('manual-extensions-ready', ['/work/v3', 'check-dependencies'])
    t.start('manual-extensions-usable', '', sudo=True, recovery=False)
    t.shell('disable-installed-module', 'phpdismod -v ' + series + ' -s cli mbstring')
    text = t.run('disabled-installed-module', ['/work/v3', 'start'], 1)
    command = 'sudo phpenmod -v ' + series + ' -s cli mbstring'
    assert command in text and 'reinstalling is not the next action' in text, text
    t.shell('follow-enable-recipe', command.removeprefix('sudo '))
    t.run('enabled-ready', ['/work/v3', 'check-dependencies'])
    t.start('manual-enabled-usable', '', sudo=True, recovery=False)
    t.shell('modify-owned-cli', "cp /usr/bin/php8.3 /tmp/original-php; printf x >> /usr/bin/php8.3")
    try:
        text = t.run('modified-owned-cli-refused', ['/work/v3', 'check-dependencies', '--install'], 1)
        assert 'not a verified' in text
    finally:
        t.shell('restore-owned-cli', 'cp /tmp/original-php /usr/bin/php8.3')
    (work / 'manual-evidence.json').write_text(json.dumps(t.results, indent=2) + '\n')
    print('PASS: actual displayed manual commands restore HTTP read/write/shutdown:', work)


if __name__ == '__main__':
    main()
