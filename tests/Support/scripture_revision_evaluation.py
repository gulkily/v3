"""Frozen three-arm revision evaluation. No retries or production-state writes.

rehearse NEW_DIR | regress NEW_DIR | initial DIR | expand DIR
qualify DIR | initial-gates DIR | expansion-gates DIR consume saved blind ratings.
"""
import io
import json
from pathlib import Path
import re
import subprocess
import sys
import tarfile
import threading

from scripture_evaluation import PROJECT, Budget, setup
from scripture_live_evaluation import ProviderProxy, proxy_server, run_trial
from scripture_reliability_evaluation import hashes, stub_transport
from scripture_reliability_review import evaluate, file_hash
from scripture_trial_review import export

PROTOCOL = PROJECT / 'tests/fixtures/scripture_reliability/revision_protocol.json'
RUNNER_FILES = [Path(__file__), PROTOCOL] + [PROJECT / 'tests/Support' / name for name in [
    'scripture_evaluation.py', 'scripture_live_evaluation.py', 'scripture_reliability_evaluation.py',
    'scripture_reliability_review.py', 'scripture_trial_review.py']] + [PROJECT / 'tests/browser/scripture_trial.cjs']


def runner_hashes():
    return {str(p.relative_to(PROJECT)): file_hash(p) for p in RUNNER_FILES}



def prepare(root, rehearsal=False):
    root.mkdir(mode=0o700)
    protocol = json.loads(PROTOCOL.read_text())
    protocol['rehearsal'] = rehearsal
    protocol['implementation_commit'] = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=PROJECT, text=True).strip()
    frozen = {}
    for label, candidate in protocol['candidates'].items():
        directory = setup(root / label)
        if label == 'A':
            archive = subprocess.check_output(['git', 'archive', protocol['control_commit'], 'src', 'scripts', 'templates', 'prompts', 'public', 'resources', 'autoload.php'], cwd=PROJECT)
            with tarfile.open(fileobj=io.BytesIO(archive)) as source:
                source.extractall(directory / 'app', filter='data')
        config = json.loads((directory / 'environment.json').read_text())
        config.update(LLM_PROVIDER='openai', LLM_MODEL='gpt-5-mini', LLM_API_KEY='local-budget-proxy',
                      SCRIPTURE_RESPONSE_GUIDANCE=candidate['guidance'], SCRIPTURE_RESPONSE_REASONING_EFFORT='low',
                      SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH='true', SCRIPTURE_RESPONSE_TIMING_ENABLED='true')
        if label != 'A':
            config['SCRIPTURE_RESPONSE_MODEL'] = candidate['model']
        (directory / 'environment.json').write_text(json.dumps(config, indent=2))
        frozen[label] = {'files': hashes(directory), 'config': config}
    (root / 'runner-hashes.json').write_text(json.dumps(runner_hashes(), indent=2))
    (root / 'frozen.json').write_text(json.dumps(frozen, indent=2))
    (root / 'protocol.json').write_text(json.dumps(protocol, indent=2))
    for name in ['results.jsonl', 'exchanges.jsonl']:
        (root / name).touch()
    return protocol


def verify_freeze(root):
    if json.loads((root / 'runner-hashes.json').read_text()) != runner_hashes():
        raise ValueError('Frozen evaluation runner changed')
    frozen = json.loads((root / 'frozen.json').read_text())
    for label, expected in frozen.items():
        config = json.loads((root / label / 'environment.json').read_text())
        config.pop('LLM_API_BASE_URL', None)
        if config != expected['config'] or hashes(root / label) != expected['files']:
            raise ValueError('Frozen candidate or configuration changed')


class RevisionProxy(ProviderProxy):
    def __init__(self, root, key, transport=None):
        super().__init__(root, key, transport=transport)
        self.protocol = json.loads((root / 'protocol.json').read_text())
        self.budget = Budget(root / 'ledger.json', dollars=10, generation_limit=76, moderation_limit=152)
        self.dispatch_lock = threading.Lock()

    def call(self, payload):
        with self.dispatch_lock:
            if (self.root / 'halt.json').exists():
                raise ValueError('Trial halted; no further dispatch')
            if len(json.dumps(payload).encode()) + 10000 > 272000:
                raise ValueError('Input exceeds frozen short-input pricing tier')
            label = self.sample.rsplit('-', 1)[-1]
            expected = 'gpt-5-mini' if 'response_format' in payload else self.protocol['candidates'][label]['model']
            if payload.get('model') != expected:
                raise ValueError('Writer or moderation routing changed')
            if 'response_format' not in payload and (payload.get('max_completion_tokens') != 8192 or payload.get('reasoning_effort') != 'low'):
                raise ValueError('Frozen writer settings changed')
            self.model = expected
            # Sol input receives an additional 25% conservative cache-write margin.
            self.input_rate, self.output_rate = (5., 20.) if expected == 'gpt-5.6-sol' else (.25, 2.)
            code, body = super().call(payload)
            response = json.loads(body)
            models_path = self.root / 'returned-models.json'
            models = json.loads(models_path.read_text()) if models_path.exists() else {}
            actual = response.get('model', '')
            valid = actual == expected or re.fullmatch(re.escape(expected) + r'-\d{4}-\d{2}-\d{2}', actual)
            problem = None
            if code != 200:
                problem = 'Account/provider rejection'
            elif not valid or (expected in models and models[expected] != actual):
                problem = 'Unexpected returned model ID'
            if problem:
                (self.root / 'halt.json').write_text(json.dumps({'reason': problem, 'status': code, 'sample': self.sample, 'returned_model': actual}, indent=2))
            elif expected not in models:
                models[expected] = actual
                models_path.write_text(json.dumps(models, indent=2))
            return code, body


def revision_stub(request):
    payload = json.loads(request.data)
    response = json.loads(stub_transport(request).read())
    response['model'] = payload['model']
    return io.BytesIO(json.dumps(response).encode())


def connect(root):
    verify_freeze(root)
    protocol = json.loads((root / 'protocol.json').read_text())
    if (root / 'halt.json').exists():
        raise ValueError('Trial halted for reassessment')
    key = 'stub-only'
    if not protocol['rehearsal']:
        key = subprocess.check_output(['php', '-r', 'require "autoload.php"; $c=ForumRewrite\\Support\\PrivateConfig::load(getcwd()); echo $c["LLM_API_KEY"] ?? "";'], cwd=PROJECT, text=True).strip()
        if not key:
            raise ValueError('Existing configured key unavailable')
    proxy = RevisionProxy(root, key, revision_stub if protocol['rehearsal'] else None)
    if any(not entry['settled'] for entry in proxy.budget.entries):
        raise ValueError('Unknown charges require explicit accounting; no redispatch')
    server = proxy_server(proxy)
    for label in protocol['candidates']:
        path = root / label / 'environment.json'
        config = json.loads(path.read_text())
        config['LLM_API_BASE_URL'] = f'http://127.0.0.1:{server.server_port}'
        path.write_text(json.dumps(config, indent=2))
    return proxy, server


def records(root):
    return [json.loads(line) for line in (root / 'results.jsonl').read_text().splitlines()]


def trial(root, proxy, phase, label, case, repeat=1, initial=None):
    protocol = json.loads((root / 'protocol.json').read_text())
    sample = f'{phase}-{case}-{repeat}-{label}'
    row = {'sample': sample, 'phase': phase, 'variant': label, 'case': case, 'repeat': repeat,
           'kind': 'expansion' if initial else 'initial', 'automatic': True,
           'question': protocol['regression_cases' if phase == 'regression' else 'cases'][case-1]}
    if initial:
        row.update(source=initial['status']['agent_post_id'], thread=initial['source'], initial_answer=initial['final_response_text'])
    path = root / (sample + '.json')
    if path.exists():
        raise ValueError('Refusing repeated attempt')
    path.write_text(json.dumps(row))
    proxy.sample = sample
    try:
        row.update(run_trial(root / label, row, path))
    except Exception as error:
        row.update(outcome='harness_error', error=type(error).__name__ + ': ' + str(error))
    with (root / 'results.jsonl').open('a') as output:
        output.write(json.dumps(row) + '\n')
    if row['outcome'] in ['harness_error', 'timeout'] or (root / 'halt.json').exists():
        export(root, require_final=True)
        raise ValueError('Trial failure retained; no automatic retry')
    return row


def evidence(root):
    return {name: file_hash(root / name) for name in ['results.jsonl', 'ratings.json', 'frozen.json', 'protocol.json']}


def load_gate(root, name):
    gate = json.loads((root / name).read_text())
    if gate['evidence_hashes'] != evidence(root):
        raise ValueError('Gate evidence changed')
    return gate


def rating_pass(row, ratings, key):
    rating = ratings[key[row['sample']]]
    if len(rating.get('rubric', [])) != 6 or any(type(v) is not bool for v in rating['rubric']) or type(rating.get('source_pass')) is not bool or type(rating.get('critical_error')) is not bool or not rating.get('notes'):
        raise ValueError('Complete explicit source/context ratings required')
    return row['outcome'] == 'complete' and all(rating['rubric']) and rating['source_pass'] and not rating['critical_error']


def save_gates(root, phase):
    rows = records(root)
    ratings = json.loads((root / 'ratings.json').read_text())
    key = json.loads((root / 'blind-key.json').read_text())
    regression = [r for r in rows if r['phase'] == 'regression']
    if {(r['variant'], r['case']) for r in regression} != {(v,c) for v in ['B','C'] for c in range(1,7)} or len(regression) != 12:
        raise ValueError('All twelve regression attempts required')
    eligible = [v for v in ['B','C'] if all(rating_pass(r, ratings, key) for r in regression if r['variant'] == v)]
    gate = {'eligible': eligible, 'passed': bool(eligible), 'evidence_hashes': evidence(root)}
    if phase == 'initial':
        gate['candidates'] = {}
        initial = [r for r in rows if r['phase'] == 'initial']
        for label in eligible:
            matched = [dict(r, variant='B' if r['variant'] == label else 'A') for r in initial if r['variant'] in ['A', label]]
            gate['candidates'][label] = evaluate(matched, ratings, key)
        qualifiers = [v for v in eligible if gate['candidates'][v]['passed']]
        def ranking(label):
            selected = [r for r in initial if r['variant'] == label]
            stats = gate['candidates'][label]['stats']['B']
            ledger = json.loads((root / 'ledger.json').read_text())
            cost = sum(e['cost'] for e in ledger if e.get('sample') in {r['sample'] for r in selected})
            return (-stats['passes'], stats['median_ms'], cost)
        gate['winner'] = min(qualifiers, key=ranking) if qualifiers else None
        gate['passed'] = bool(qualifiers)
    elif phase == 'expansion':
        from statistics import median
        initial_gate = json.loads((root / 'initial-gates.json').read_text())
        winner = initial_gate.get('winner')
        expansion = [r for r in rows if r['phase'] == 'expansion']
        expected = {(v,c,n) for v in ['A',winner] for c in [1,5,7] for n in [1,2]}
        if len(expansion) != 12 or {(r['variant'],r['case'],r['repeat']) for r in expansion} != expected:
            raise ValueError('All twelve matched expansion attempts required')
        a = [r for r in expansion if r['variant']=='A']
        b = [r for r in expansion if r['variant']==winner]
        complete = all(r['outcome']=='complete' and isinstance(r.get('total_ms'), (int,float)) for r in expansion)
        gate['checks'] = {'all_six_pass': all(rating_pass(r, ratings, key) for r in b), 'complete_timing': complete}
        if complete:
            times = [r['total_ms'] for r in b]
            gate['checks'].update(median_20s=median(times)<=20000, improves_30_percent=median(times)<=median(r['total_ms'] for r in a)*.7, maximum_40s=max(times)<=40000)
        gate['passed'] = initial_gate['passed'] and all(gate['checks'].values())
        gate['winner'] = winner
    (root / (phase + '-gates.json')).write_text(json.dumps(gate, indent=2)+'\n')
    print(json.dumps(gate, indent=2))


def run(root, phase):
    protocol = json.loads((root / 'protocol.json').read_text())
    gate = None
    if phase == 'initial':
        gate = load_gate(root, 'regression-gates.json')
        if not gate['passed']: raise ValueError('No regression qualifier')
    elif phase == 'expansion':
        gate = load_gate(root, 'initial-gates.json')
        if not gate['passed']: raise ValueError('No initial qualifier')
    proxy, server = connect(root)
    try:
        if phase == 'regression':
            for case in range(1,7):
                for label in (['B','C'] if case % 2 else ['C','B']):
                    trial(root, proxy, phase, label, case)
        elif phase == 'initial':
            for repeat in [1,2]:
                for case in range(1,9):
                    labels = ['A'] + gate['eligible']
                    if (case+repeat)%2: labels.reverse()
                    for label in labels: trial(root, proxy, phase, label, case, repeat)
        elif phase == 'expansion':
            initial = records(root)
            for repeat in [1,2]:
                for case in [1,5,7]:
                    labels = ['A', gate['winner']]
                    if (case+repeat)%2: labels.reverse()
                    for label in labels:
                        source = next(r for r in initial if r['phase']=='initial' and r['variant']==label and r['case']==case and r['repeat']==repeat)
                        trial(root, proxy, phase, label, case, repeat, source)
        elif phase == 'rehearsal':
            for label in ['A','B','C']:
                first = trial(root, proxy, 'initial', label, 1)
                trial(root, proxy, 'initial', label, 8)
                trial(root, proxy, 'expansion', label, 1, initial=first)
        export(root, require_final=True)
    finally:
        server.shutdown()


if __name__ == '__main__':
    mode, path = sys.argv[1:]
    root = Path(path).resolve()
    if mode in ['rehearse', 'regress']:
        prepare(root, mode == 'rehearse')
        run(root, 'rehearsal' if mode == 'rehearse' else 'regression')
    elif mode in ['qualify', 'initial-gates', 'expansion-gates']:
        save_gates(root, {'qualify':'regression', 'initial-gates':'initial', 'expansion-gates':'expansion'}[mode])
    elif mode in ['initial', 'expand']:
        run(root, 'initial' if mode == 'initial' else 'expansion')
    else:
        raise SystemExit('Unknown phase')
