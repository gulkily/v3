"""Replay unchanged import-publication recovery tests against a bounded docs inventory.

The implementation, templates, assets and canonical fixtures are the real project
files. Only the project's documentation inventory is reduced, so each crash/retry
case does not rebuild all 1,300+ documentation pages twice.
"""
import pathlib
import shutil
import subprocess
import tempfile

project = pathlib.Path(__file__).resolve().parents[1]
shadow = pathlib.Path(tempfile.mkdtemp(prefix='v3-publication-regression-'))
for entry in project.iterdir():
    if entry.name not in ['.git', 'docs', 'state', 'tests', 'node_modules']:
        (shadow / entry.name).symlink_to(entry, target_is_directory=entry.is_dir())
(shadow / 'tests').mkdir()
for entry in (project / 'tests').iterdir():
    if entry.name in ['run.php', 'ImportedContentPublicationTest.php']:
        shutil.copyfile(entry, shadow / 'tests' / entry.name)
    else:
        (shadow / 'tests' / entry.name).symlink_to(entry, target_is_directory=entry.is_dir())
(shadow / 'docs/runbooks').mkdir(parents=True)
shutil.copyfile(project / 'docs/runbooks/high_traffic_mode.md', shadow / 'docs/runbooks/high_traffic_mode.md')
(shadow / 'docs/README.md').write_text('# Publication fixture\n\nA bounded documentation inventory for the publication recovery sequence.\n')
print('Import publication recovery: real code with an isolated two-document inventory.', flush=True)
result = subprocess.run(['php', str(shadow / 'tests/run.php'), 'ImportedContentPublicationTest'], cwd=project)
if result.returncode == 0:
    shutil.rmtree(shadow)
else:
    print('Retained failing publication fixture:', shadow, flush=True)
raise SystemExit(result.returncode)
