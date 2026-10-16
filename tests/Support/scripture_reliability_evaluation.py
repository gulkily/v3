"""Approved two-candidate reliability trials; isolated state and no automatic retries.

rehearse NEW_DIRECTORY    -- stub transport through normal HTTP/worker/browser flow
initial NEW_DIRECTORY     -- 32 paid initial trials, fixed 48/96/$5 total ceiling
expand EXISTING_DIRECTORY -- only after saved initial gates pass
"""
import hashlib
import io
import json
from pathlib import Path
import sqlite3
import subprocess
import sys
import time

from scripture_evaluation import PROJECT, setup
from scripture_live_evaluation import ProviderProxy, proxy_server, run_trial
from scripture_trial_review import export

PROTOCOL_PATH = PROJECT / 'tests/fixtures/scripture_reliability/protocol.json'


def stub_transport(request):
    payload = json.loads(request.data)
    verified = 'specified JSON document' in payload['messages'][0]['content']
    segments = [{'type': 'reflection', 'text': 'Choose a responsible stopping point and defer optional plans. In paraphrase, thoughtful diligence helps us act well without promising a guaranteed outcome.'},
                {'type': 'quotation', 'book': 'Proverbs', 'chapter': 21, 'start': 5, 'end': 5, 'translation': 'KJV'}]
    if 'Application source status: Requested translation cannot' in payload['messages'][1]['content']:
        segments.pop()
    text = json.dumps({'segments': segments}) if verified else 'Scripture-oriented analysis: Choose a responsible stopping point and defer optional plans.'
    return io.BytesIO(json.dumps({'model': 'stub-for-gpt-5-mini',
        'usage': {'prompt_tokens': 100, 'completion_tokens': 200},
        'choices': [{'finish_reason':'stop', 'message': {'content':text}}]}).encode())


def hashes(directory):
    return {str(p.relative_to(directory / 'app')): hashlib.sha256(p.read_bytes()).hexdigest()
            for name in ('src', 'prompts', 'resources', 'scripts', 'templates')
            for p in (directory / 'app' / name).rglob('*') if p.is_file() and '__pycache__' not in p.parts}


def prepare(root, rehearsal):
    root.mkdir(mode=0o700)  # Never reuse old ledgers or overwrite a trial.
    protocol = json.loads(PROTOCOL_PATH.read_text())
    protocol.update(rehearsal=rehearsal, input_per_million=.25, output_per_million=2.,
                    pricing_source='https://developers.openai.com/api/docs/models/gpt-5-mini',
                    pricing_checked='2026-10-16',
                    conditions='Serial, fresh browser per trial, persistent isolated variant state; automatic dispatch and polling for both; first trial cold; automatic fast-scoring disabled equally.')
    frozen = {}
    for label, candidate in protocol['candidates'].items():
        directory = setup(root / label)
        config = json.loads((directory / 'environment.json').read_text())
        config.update(LLM_PROVIDER='openai', LLM_MODEL=candidate['model'], LLM_API_KEY='local-budget-proxy',
                      SCRIPTURE_RESPONSE_GUIDANCE=candidate['guidance'],
                      SCRIPTURE_RESPONSE_REASONING_EFFORT=candidate['reasoning'],
                      SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH='true', SCRIPTURE_RESPONSE_TIMING_ENABLED='true')
        (directory / 'environment.json').write_text(json.dumps(config, indent=2))
        frozen[label] = hashes(directory)
    (root / 'frozen.json').write_text(json.dumps(frozen, indent=2))
    (root / 'protocol.json').write_text(json.dumps(protocol, indent=2))
    (root / 'results.jsonl').touch()
    (root / 'exchanges.jsonl').touch()
    return protocol


def connect(root, protocol):
    if protocol['rehearsal']:
        key, transport = 'stub-not-a-real-key', stub_transport
    else:
        # Read the already authorized existing key into memory; never log/copy it.
        key = subprocess.check_output(['php', '-r',
            'require "autoload.php"; $c=ForumRewrite\\Support\\PrivateConfig::load(getcwd()); echo $c["LLM_API_KEY"] ?? "";'], cwd=PROJECT, text=True).strip()
        if not key:
            raise RuntimeError('Configured provider key is missing')
        transport = None
    proxy = ProviderProxy(root, key, model='gpt-5-mini', input_rate=.25, output_rate=2.,
                          writing_limit=48, moderation_limit=96, transport=transport)
    server = proxy_server(proxy)
    frozen = json.loads((root / 'frozen.json').read_text())
    for label in ['A', 'B']:
        directory = root / label
        if hashes(directory) != frozen[label]:
            server.shutdown()
            raise ValueError('Frozen candidate changed')
        path = directory / 'environment.json'
        config = json.loads(path.read_text())
        config['LLM_API_BASE_URL'] = f'http://127.0.0.1:{server.server_port}'
        path.write_text(json.dumps(config, indent=2))
    return proxy, server


def trial(root, proxy, label, case, repeat, kind='initial', initial=None):
    sample = f'{kind}-{case}-{repeat}-{label}'
    row = {'sample':sample, 'variant':label, 'case':case, 'repeat':repeat, 'kind':kind,
           'question':json.loads((root / 'protocol.json').read_text())['cases'][case-1], 'automatic':True}
    if initial:
        row.update(source=initial['status']['agent_post_id'], thread=initial['source'], initial_answer=initial['final_response_text'])
    # Durable attempt marker precedes any dispatch; interrupted samples cannot be
    # silently repeated or disappear between provider reservation and result save.
    path = root / (sample + '.json')
    if path.exists():
        raise ValueError('Refusing to repeat a dispatched trial')
    path.write_text(json.dumps(row))
    proxy.sample = sample
    try:
        result = run_trial(root / label, row, path)
    except Exception as error:
        result = {'outcome':'harness_error', 'error':type(error).__name__ + ': ' + str(error)}
    row.update(result)
    with (root / 'results.jsonl').open('a') as output:
        output.write(json.dumps(row) + '\n')
    if row['outcome'] in ['harness_error', 'timeout']:
        raise RuntimeError('Operational trial failure retained; no automatic retry')
    return row


def run_initial(root, rehearsal=False):
    protocol = prepare(root, rehearsal)
    proxy, server = connect(root, protocol)
    try:
        cases, repeats = ([1,8], [1]) if rehearsal else (range(1,9), [1,2])
        results = []
        for repeat in repeats:
            for case in cases:
                labels = ['A','B'] if (case+repeat)%2 == 0 else ['B','A']
                for label in labels:
                    results.append(trial(root, proxy, label, case, repeat))
        if rehearsal:
            for label in ['A','B']:
                initial = next(r for r in results if r['variant']==label and r['case']==1)
                result = trial(root, proxy, label, 1, 1, 'expansion', initial)
                if result['outcome'] != 'complete':
                    raise RuntimeError('Stub expansion failed')
        if rehearsal:
            def malformed_transport(request):
                return io.BytesIO(json.dumps({'usage':{'prompt_tokens':100,'completion_tokens':200},
                    'choices':[{'finish_reason':'stop','message':{'content':'malformed document'}}]}).encode())
            proxy.transport = malformed_transport
            failed = trial(root, proxy, 'B', 2, 1)
            if failed['outcome'] != 'failed':
                raise RuntimeError('Malformed-output recovery was not terminal')
        export(root, require_final=True)
    finally:
        server.shutdown()


def run_expansions(root):
    protocol = json.loads((root / 'protocol.json').read_text())
    gate = json.loads((root / 'initial-gates.json').read_text())
    if protocol['rehearsal'] or gate.get('passed') is not True:
        raise ValueError('Saved passing initial gates required before paid expansions')
    from scripture_reliability_review import file_hash
    if any(file_hash(root/name) != digest for name,digest in gate.get('evidence_hashes', {}).items()) or len(gate.get('evidence_hashes', {})) != 4:
        raise ValueError('Initial-gate evidence changed')
    results = [json.loads(line) for line in (root / 'results.jsonl').read_text().splitlines()]
    if len(results) != 32 or any(r['outcome'] != 'complete' for r in results):
        raise ValueError('Complete initial comparison required')
    proxy, server = connect(root, protocol)
    try:
        for repeat in [1,2]:
            for case in protocol['expansion_cases']:
                labels = ['A','B'] if (case+repeat)%2 == 0 else ['B','A']
                for label in labels:
                    initial = next(r for r in results if r['variant']==label and r['case']==case and r['repeat']==repeat)
                    trial(root, proxy, label, case, repeat, 'expansion', initial)
        export(root, require_final=True)
    finally:
        server.shutdown()


if __name__ == '__main__':
    mode, directory = sys.argv[1:]
    root = Path(directory).resolve()
    if mode in ['rehearse','initial']:
        run_initial(root, mode=='rehearse')
    elif mode == 'expand':
        run_expansions(root)
    else:
        raise SystemExit('Expected rehearse, initial, or expand')
