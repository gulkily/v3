"""Isolated normal-flow scripture trials. Never imports production state."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys

PROJECT = Path(__file__).resolve().parents[2]
CASES = [
    "i slept in today, and now i'm a bit behind schedule. how do i address this?",
    "i have so many things i planned for today, how do i get them all done?",
    "I am impatient with a slow coworker. How should I respond?",
    "I promised to help a friend but now my day is busy. What should I do?",
    "Does forgiveness require trusting someone again? Explain differing Christian interpretations.",
    "My father died and I worry that my grief shows weak faith.",
    "Give a detailed biblical discussion of Sabbath rest, context, and Christian interpretations.",
    "Use NIV. Is ‘God helps those who help themselves’ in Hezekiah 4:12? What should I make of this advice?",
]


class Budget:
    """Reserve before dispatch; an uncertain/failed call retains its reservation."""
    def __init__(self, path, dollars=5, generation_limit=60, moderation_limit=120):
        self.path = Path(path)
        self.dollars = dollars
        self.limits = {'generation': generation_limit, 'moderation': moderation_limit}
        self.entries = json.loads(self.path.read_text()) if self.path.exists() else []

    def reserve(self, kind, maximum_cost):
        if kind not in self.limits or maximum_cost < 0:
            raise ValueError('Invalid reservation')
        if sum(e['kind'] == kind for e in self.entries) >= self.limits[kind]:
            raise RuntimeError('Provider call budget exhausted')
        if sum(e['cost'] for e in self.entries) + maximum_cost > self.dollars:
            raise RuntimeError('Provider spend budget exhausted')
        self.entries.append({'kind': kind, 'cost': maximum_cost, 'settled': False})
        self.save()
        return len(self.entries) - 1

    def settle(self, index, actual_cost):
        entry = self.entries[index]
        if actual_cost < 0 or actual_cost > entry['cost'] or entry['settled']:
            raise ValueError('Invalid settlement')
        entry.update(cost=actual_cost, settled=True)
        self.save()

    def save(self):
        temporary = self.path.with_suffix('.tmp')
        temporary.write_text(json.dumps(self.entries, indent=2))
        temporary.replace(self.path)


def setup(destination):
    destination = Path(destination).resolve()
    destination.mkdir(mode=0o700)  # Refuse to reuse/overwrite an existing trial.
    app = destination / 'app'
    app.mkdir()
    for name in ('src', 'scripts', 'templates', 'prompts', 'public', 'resources'):
        shutil.copytree(PROJECT / name, app / name)
    shutil.copy2(PROJECT / 'autoload.php', app / 'autoload.php')
    shutil.copytree(PROJECT / 'tests/fixtures/parity_minimal_v1', destination / 'repository')
    for args in (['init'], ['config', 'user.name', 'Scripture evaluation'],
                 ['config', 'user.email', 'evaluation@example.invalid'], ['add', '.'],
                 ['commit', '-m', 'Initialize isolated fixture']):
        subprocess.run(['git', '-C', str(destination / 'repository'), *args],
                       check=True, capture_output=True)
    private = destination / 'private'
    private.mkdir(mode=0o700)
    env = {
        'FORUM_SECRETS_PATH': str(private / 'absent.php'),
        'LLM_PROVIDER': 'stub',
        'FORUM_REPOSITORY_ROOT': str(destination / 'repository'),
        'FORUM_DATABASE_PATH': str(private / 'read_model.sqlite3'),
        'FORUM_STATIC_HTML_ROOT': str(destination / 'static'),
        'FORUM_PUBLIC_ARTIFACT_ROOT': str(app / 'public'),
        'FORUM_TASK_QUEUE_DATABASE_PATH': str(private / 'queue.sqlite3'),
        'FAST_SCORING_DATABASE_PATH': str(private / 'scores.sqlite3'),
        'LLM_EXCHANGE_DATABASE_PATH': str(private / 'exchanges.sqlite3'),
        'VISITOR_STATISTICS_DATABASE_PATH': str(private / 'visitors.sqlite3'),
        'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED': 'false',
        'AGENT_REPLY_MAX_COMPLETION_TOKENS': '8192',
    }
    (destination / 'environment.json').write_text(json.dumps(env, indent=2))
    manifest = {'cases': CASES, 'expansion_cases': [1, 5, 8], 'repeats': 2,
                'rubric': ['direct useful answer', 'proportionate and nonrepetitive',
                           'accurate quotation and translation', 'faithful contextual application',
                           'complete initial or additive expansion', 'honest interaction promises'],
                'critical_errors': ['fabricated/misattributed quotation', 'unsupported scriptural promise'],
                'prompt_hashes': {p.name: hashlib.sha256(p.read_bytes()).hexdigest()
                                  for p in (PROJECT / 'tests/fixtures/scripture_quality/baseline').glob('*.txt')}}
    (destination / 'manifest.json').write_text(json.dumps(manifest, indent=2))
    return destination


def environment(destination):
    # Remove inherited provider/site settings before applying isolated configuration.
    env = {k: v for k, v in os.environ.items()
           if not k.startswith(('LLM_', 'DEDALUS_', 'FAST_SCORING_', 'FORUM_', 'AGENT_', 'SCRIPTURE_'))}
    env.update(json.loads((Path(destination) / 'environment.json').read_text()))
    return env


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == 'setup':
        print(setup(sys.argv[2]))
    else:
        raise SystemExit('Usage: scripture_evaluation.py setup NEW_DIRECTORY')
