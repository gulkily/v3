"""Frozen, budget-guarded live trials through ordinary HTTP/browser/worker delivery.

Usage: python tests/Support/scripture_live_evaluation.py NEW_PRIVATE_DIRECTORY
No retries, secret copies, judge calls, production posts, or changes to candidates.
"""
import hashlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import socket
import subprocess
import sys
import threading
import time
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from scripture_evaluation import Budget, CASES, PROJECT, environment, setup


class ProviderProxy:
    def __init__(self, root, key, model='gpt-5-nano', input_rate=.05, output_rate=.40, writing_limit=60, moderation_limit=120, transport=None):
        self.root, self.key = root, key
        self.model, self.input_rate, self.output_rate = model, input_rate, output_rate
        self.transport = transport
        self.budget = Budget(root / 'ledger.json', generation_limit=writing_limit, moderation_limit=moderation_limit)
        self.lock = threading.Lock()
        self.sample = None

    def call(self, payload):
        # Enforce fixed model, completion allowance, no tools or alternate billing.
        if payload.get('model') != self.model or set(payload) - {
                'model', 'messages', 'response_format', 'max_completion_tokens', 'reasoning_effort'}:
            raise ValueError('Unapproved provider payload')
        maximum = payload.get('max_completion_tokens', 0)
        if not isinstance(maximum, int) or not 1 <= maximum <= 8192:
            raise ValueError('Unapproved completion allowance')
        raw = json.dumps(payload).encode()
        kind = 'moderation' if 'response_format' in payload else 'generation'
        if self.model == 'gpt-5-mini' and kind == 'generation' and (payload.get('reasoning_effort') != 'low' or maximum != 8192):
            raise ValueError('Unapproved mini generation settings')
        # UTF-8 bytes plus ample message overhead bounds the input tokens. Charge
        # all input uncached and all output, including reasoning, at standard rates.
        reserved = ((len(raw) + 10000) * self.input_rate + maximum * self.output_rate) / 1000000
        with self.lock:
            index = self.budget.reserve(kind, reserved)
            sample = self.sample
            self.budget.entries[index].update(sample=sample, dispatched_at_ms=time.time()*1000)
            self.budget.save()
        started = time.perf_counter()
        status = 200
        try:
            request = Request('https://api.openai.com/v1/chat/completions', data=raw,
                              headers={'Authorization': 'Bearer ' + self.key,
                                       'Content-Type': 'application/json'})
            try:
                with (self.transport(request) if self.transport else urlopen(request, timeout=65)) as response:
                    body = response.read()
            except HTTPError as error:
                status, body = error.code, error.read()
            decoded = json.loads(body)
            usage = decoded.get('usage')
            with self.lock:
                if usage is not None:
                    cost = (usage['prompt_tokens'] * self.input_rate + usage['completion_tokens'] * self.output_rate) / 1000000
                    self.budget.settle(index, cost)
                record = {'sample': sample, 'kind': kind, 'index': index, 'status': status,
                          'provider_ms': (time.perf_counter()-started)*1000,
                          'request': payload, 'response': decoded}
                with (self.root / 'exchanges.jsonl').open('a') as output:
                    output.write(json.dumps(record) + '\n')
            return status, body
        except Exception:
            # An uncertain charge retains its full reservation; never retry.
            raise


def proxy_server(proxy):
    class Handler(BaseHTTPRequestHandler):
        def do_POST(self):
            try:
                if self.path != '/v1/chat/completions':
                    raise ValueError('Unsupported endpoint')
                payload = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
                code, body = proxy.call(payload)
            except Exception as error:
                code, body = 502, json.dumps({'error': {'message': type(error).__name__}}).encode()
            self.send_response(code)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def log_message(self, *args):
            pass
    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    return server


def free_port():
    with socket.socket() as connection:
        connection.bind(('127.0.0.1', 0))
        return connection.getsockname()[1]


def run_trial(directory, trial, path):
    path.write_text(json.dumps(trial, indent=2))
    port = free_port()
    with path.with_suffix('.server.log').open('w') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t',
                                   str(directory / 'app/public'), str(directory / 'app/public/index.php')],
                                  env=environment(directory), stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            subprocess.run(['node', str(PROJECT / 'tests/browser/scripture_trial.cjs'),
                            str(directory), f'http://127.0.0.1:{port}', str(path)],
                           check=True, env=environment(directory), timeout=240)
            result = json.loads(Path(str(path) + '.result.json').read_text())
            if result.get('outcome') == 'complete':
                import sqlite3
                config = json.loads((directory / 'environment.json').read_text())
                with sqlite3.connect(config['FORUM_DATABASE_PATH']) as db:
                    row = db.execute('SELECT body FROM posts WHERE post_id = ?', (result['status']['agent_post_id'],)).fetchone()
                if row is None or row[0].strip() != result.get('visible_body_text', '').strip():
                    raise ValueError('Rendered and stored final answers disagree')
                result['final_response_text'] = row[0]
            return result
        finally:
            server.terminate()
            server.wait()


def resume_results(root, frozen, entries):
    if frozen != json.loads((root / 'frozen.json').read_text()):
        raise ValueError('Frozen candidate artifact changed')
    previous_results = [json.loads(line) for line in (root / 'results.jsonl').read_text().splitlines()]
    previous_results = list({row['sample']: row for row in previous_results}.values())
    exchanges = [json.loads(line) for line in (root / 'exchanges.jsonl').read_text().splitlines()]
    accounted_uncertain = set()
    for row in previous_results:
        if row['outcome'] == 'interrupted':
            index = row.get('reservation_index')
            if (not isinstance(index, int) or not 0 <= index < len(entries)
                    or entries[index]['settled']
                    or any(e['sample'] == row['sample'] for e in exchanges)):
                raise ValueError('Invalid interruption accounting')
            accounted_uncertain.add(index)
            continue
        if row['outcome'] != 'complete':
            if (row['outcome'] != 'harness_error' or row.get('source')
                    or 'body must be ASCII only' not in row.get('error', '')
                    or any(e['sample'] == row['sample'] for e in exchanges)):
                raise ValueError('Refusing to repeat a dispatched or uncertain generation')
    if {i for i, row in enumerate(entries) if not row['settled']} != accounted_uncertain:
        raise ValueError('Uncertain provider calls require explicit retained-reservation accounting')
    return previous_results


def main(root, resume=False):
    if not resume:
        root.mkdir(mode=0o700)  # A new run cannot erase previous budget/accounting.
    # Capture only the existing key in memory; never print/copy the private config.
    key = subprocess.check_output(['php', '-r',
        'require "autoload.php"; $c=ForumRewrite\\Support\\PrivateConfig::load(getcwd()); '
        'echo $c["LLM_API_KEY"] ?? "";'], cwd=PROJECT, text=True).strip()
    if not key:
        raise RuntimeError('Configured provider key is missing')
    proxy = ProviderProxy(root, key)
    server = proxy_server(proxy)
    variants = {}
    for label, guidance, effort in [('A', 'current', ''), ('B', 'revised', ''), ('C', 'revised', 'low')]:
        directory = root / label if resume else setup(root / label)
        config = json.loads((directory / 'environment.json').read_text())
        config.update(LLM_PROVIDER='openai', LLM_MODEL='gpt-5-nano', LLM_API_KEY='local-budget-proxy',
                      LLM_API_BASE_URL=f'http://127.0.0.1:{server.server_port}',
                      SCRIPTURE_RESPONSE_GUIDANCE=guidance, SCRIPTURE_RESPONSE_REASONING_EFFORT=effort,
                      SCRIPTURE_RESPONSE_TIMING_ENABLED='true',
                      SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH='false' if label == 'A' else 'true')
        (directory / 'environment.json').write_text(json.dumps(config, indent=2))
        if label == 'A' and not resume:
            for prompt in (PROJECT / 'tests/fixtures/scripture_quality/baseline').glob('*.txt'):
                (directory / 'app/prompts' / prompt.name).write_bytes(prompt.read_bytes())
        variants[label] = directory
    frozen = {label: {str(p.relative_to(directory / 'app')): hashlib.sha256(p.read_bytes()).hexdigest()
                      for group in ['prompts', 'src'] for p in (directory / 'app' / group).rglob('*') if p.is_file()}
              for label, directory in variants.items()}
    previous_results = []
    if resume:
        previous_results = resume_results(root, frozen, proxy.budget.entries)
    else:
        (root / 'frozen.json').write_text(json.dumps(frozen, indent=2))
        (root / 'protocol.json').write_text(json.dumps({
            'model': 'gpt-5-nano', 'generation_maximum': 60, 'moderation_maximum': 120, 'dollars': 5,
            'input_per_million': .05, 'output_per_million': .40,
            'pricing_source': 'https://developers.openai.com/api/docs/models/gpt-5-nano',
            'cases': CASES, 'discovery_A': 'immediate explicit CLI worker and browser reload',
            'discovery_B_C': 'automatic worker dispatch and status polling',
            'conditions': 'serial; fresh browser per trial; persistent isolated application per variant; first trial cold',
        }, indent=2))
    try:
        sequence = 0
        for repeat in range(2):
            for case, question in enumerate(CASES, 1):
                labels = ['A', 'B', 'C']
                rotation = (case - 1 + repeat) % 3
                labels = labels[rotation:] + labels[:rotation]
                for label in labels:
                    sequence += 1
                    sample = f'initial-{sequence:02d}'
                    if any(row['sample'] == sample and row['outcome'] in ['complete', 'interrupted'] for row in previous_results):
                        continue
                    proxy.sample = sample
                    trial = {'sample': sample, 'case': case, 'repeat': repeat + 1,
                             'question': question, 'kind': 'initial', 'automatic': label != 'A'}
                    suffix = '.resumed.json' if resume else '.json'
                    result = run_trial(variants[label], trial, root / (sample + suffix))
                    with (root / 'results.jsonl').open('a') as output:
                        output.write(json.dumps({'variant': label, **trial, **result}) + '\n')
                    if result['outcome'] == 'harness_error':
                        raise RuntimeError('Harness failure: inspect retained evidence; no automatic retry')
    finally:
        server.shutdown()


def run_expansions(root, winner):
    if winner not in ['B', 'C']:
        raise ValueError('A qualifying candidate is required')
    attempts = [json.loads(line) for line in (root / 'results.jsonl').read_text().splitlines()]
    results = list({row['sample']: row for row in attempts}.values())
    if len(results) != 48 or any(row['outcome'] != 'complete' for row in results):
        raise ValueError('Initial comparison must be complete before expansion')
    if (root / 'expansion-results.jsonl').exists():
        raise ValueError('Refusing to repeat expansion trials')
    frozen = json.loads((root / 'frozen.json').read_text())
    for label in ['A', winner]:
        for relative, expected in frozen[label].items():
            actual = hashlib.sha256((root / label / 'app' / relative).read_bytes()).hexdigest()
            if actual != expected:
                raise ValueError('Frozen candidate artifact changed')
    key = subprocess.check_output(['php', '-r',
        'require "autoload.php"; $c=ForumRewrite\\Support\\PrivateConfig::load(getcwd()); '
        'echo $c["LLM_API_KEY"] ?? "";'], cwd=PROJECT, text=True).strip()
    proxy = ProviderProxy(root, key)
    server = proxy_server(proxy)
    for label in ['A', winner]:
        path = root / label / 'environment.json'
        config = json.loads(path.read_text())
        config['LLM_API_BASE_URL'] = f'http://127.0.0.1:{server.server_port}'
        path.write_text(json.dumps(config, indent=2))
    try:
        sequence = 0
        for repeat in [1, 2]:
            for case in [1, 5, 8]:
                labels = ['A', winner] if (case + repeat) % 2 == 0 else [winner, 'A']
                for label in labels:
                    initial = next(row for row in results if row['variant'] == label
                                   and row['case'] == case and row['repeat'] == repeat)
                    sequence += 1
                    sample = f'expansion-{sequence:02d}'
                    proxy.sample = sample
                    trial = {'sample': sample, 'case': case, 'repeat': repeat,
                             'kind': 'expansion', 'automatic': label != 'A',
                             'question': CASES[case - 1], 'thread': initial['source'],
                             'source': initial['status']['agent_post_id']}
                    result = run_trial(root / label, trial, root / (sample + '.json'))
                    with (root / 'expansion-results.jsonl').open('a') as output:
                        output.write(json.dumps({'variant': label, **trial, **result}) + '\n')
                    if result['outcome'] == 'harness_error':
                        raise RuntimeError('Expansion harness failure; retained evidence, no retry')
    finally:
        server.shutdown()


if __name__ == '__main__':
    if len(sys.argv) == 4 and sys.argv[1] == 'expand':
        run_expansions(Path(sys.argv[2]).resolve(), sys.argv[3])
    elif len(sys.argv) == 3 and sys.argv[1] == 'resume':
        main(Path(sys.argv[2]).resolve(), resume=True)
    else:
        main(Path(sys.argv[1]).resolve())
