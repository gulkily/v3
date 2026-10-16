"""Apply the frozen release gates after anonymous final-answer ratings are saved."""
import hashlib
import json
from pathlib import Path
from statistics import median
import sys

RUBRIC = ['direct useful answer', 'proportionate and nonrepetitive',
          'accurate quotation and translation', 'faithful contextual application',
          'complete initial or additive expansion', 'honest interaction promises']


def evaluate(rows, ratings, key):
    if len(rows) != 32 or {(r['variant'],r['case'],r['repeat']) for r in rows} != {
            (v,c,r) for v in ['A','B'] for c in range(1,9) for r in [1,2]}:
        raise ValueError('All 32 fixed initial samples must be retained')
    def rating(row):
        result = ratings[key[row['sample']]]
        if not isinstance(result.get('rubric'), list) or len(result['rubric']) != 6 or any(type(v) is not bool for v in result['rubric']):
            raise ValueError('Six explicit rubric ratings required')
        if type(result.get('source_pass')) is not bool or type(result.get('critical_error')) is not bool or not result.get('notes'):
            raise ValueError('Source check, critical-error rating, and notes required')
        return result
    def passed(row):
        r = rating(row)
        return row['outcome']=='complete' and all(r['rubric']) and r['source_pass'] and not r['critical_error']
    groups = {v:[r for r in rows if r['variant']==v] for v in ['A','B']}
    stats = {}
    for label, samples in groups.items():
        times = [r['total_ms'] for r in samples if r['outcome']=='complete']
        stats[label] = {'completed':len(times), 'passes':sum(passed(r) for r in samples),
                        'everyday_passes':sum(passed(r) for r in samples if r['case']<=4),
                        'critical_errors':sum(rating(r)['critical_error'] for r in samples),
                        'median_ms':median(times) if times else None, 'max_ms':max(times) if times else None}
    paired = {(r['variant'],r['case'],r['repeat']):r for r in rows}
    improvements = sum(passed(paired['B',c,r]) and not passed(paired['A',c,r]) for c in range(1,5) for r in [1,2])
    degraded = any(rating(paired['A',c,r])['rubric'][4] and not rating(paired['B',c,r])['rubric'][4] for c in range(5,9) for r in [1,2])
    a,b = stats['A'],stats['B']
    checks = {'quality_floor':b['passes']>=15, 'everyday':b['everyday_passes']==8,
              'four_everyday_improvements':improvements>=4, 'complex_completeness':not degraded,
              'no_critical_errors':b['critical_errors']==0,
              'source_checks':all(rating(r)['source_pass'] for r in groups['B'] if r['outcome']=='complete'),
              'complete_latency_evidence':a['completed']==16 and b['completed']==16,
              'median_20s':b['median_ms'] is not None and b['median_ms']<=20000,
              'improves_30_percent':a['median_ms'] is not None and b['median_ms'] is not None and b['median_ms']<=a['median_ms']*.7,
              'maximum_40s':b['max_ms'] is not None and b['max_ms']<=40000}
    return {'passed':all(checks.values()),'checks':checks,'stats':stats,'everyday_improvements':improvements}


def file_hash(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def save_gates(root):
    rows = [json.loads(s) for s in (root/'results.jsonl').read_text().splitlines()]
    ratings = json.loads((root/'ratings.json').read_text())
    key = json.loads((root/'blind-key.json').read_text())
    gates = evaluate(rows, ratings, key)
    gates['evidence_hashes'] = {name:file_hash(root/name) for name in ['results.jsonl','ratings.json','frozen.json','protocol.json']}
    (root/'initial-gates.json').write_text(json.dumps(gates,indent=2)+'\n')
    print(json.dumps(gates,indent=2))


if __name__ == '__main__':
    save_gates(Path(sys.argv[1]))
