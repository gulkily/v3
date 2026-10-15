"""Export anonymous review packets without candidate labels or performance data."""
import json
from pathlib import Path
import random
import sys
import uuid


def export(root):
    attempts = [json.loads(line) for line in (root / 'results.jsonl').read_text().splitlines()]
    results = list({row['sample']: row for row in attempts}.values())
    exchanges = [json.loads(line) for line in (root / 'exchanges.jsonl').read_text().splitlines()]
    key_path = root / 'blind-key.json'
    key = json.loads(key_path.read_text()) if key_path.exists() else {}
    review = root / 'blind'
    review.mkdir(exist_ok=True)
    for row in results:
        sample = row['sample']
        if sample not in key:
            key[sample] = uuid.uuid4().hex[:8]
        anonymous = key[sample]
        destination = review / (anonymous + '.json')
        if destination.exists() and json.loads(destination.read_text())['outcome'] == 'complete':
            continue
        matching = [x for x in exchanges if x['sample'] == sample and x['kind'] == 'generation']
        body = matching[0]['response'].get('choices', [{}])[0].get('message', {}).get('content', '') if matching else ''
        destination.write_text(json.dumps({'id': anonymous, 'case': row['case'],
                                         'question': row['question'], 'outcome': row['outcome'],
                                         'answer': body}, indent=2))
    key_path.write_text(json.dumps(key, indent=2))
    packets = sorted(review.glob('*.json'))
    random.Random(41).shuffle(packets)
    (root / 'review-order.json').write_text(json.dumps([p.name for p in packets], indent=2))
    print(f'{len(packets)} anonymous packets ready')


if __name__ == '__main__':
    export(Path(sys.argv[1]))
