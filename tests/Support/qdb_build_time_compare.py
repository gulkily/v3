#!/usr/bin/env python3
"""Serial, matched fresh-process measurements; retains every sample and row output."""
import pathlib, subprocess, os, statistics, hashlib, shutil, sqlite3, json
project=pathlib.Path(__file__).resolve().parents[2]
baseline=pathlib.Path(os.environ.get('QDB_BENCH_BASELINE','/tmp/qdb-build-time-baseline'))
corpus=pathlib.Path(os.environ.get('QDB_BENCH_CORPUS','/tmp/qdb-build-time-corpus'))
results=pathlib.Path(os.environ.get('QDB_BENCH_OUTPUT','/tmp/qdb-build-time-results/matched'));results.mkdir()
report=results/'samples.txt'
rows=[]
hashes={}
def run(mode, workload, variant, repeat):
    name=f'{mode}-{workload}-{variant}-{repeat}'
    root=project if variant=='combined' else baseline
    repo=baseline/'tests/fixtures/parity_minimal_v1' if mode in ('profiles','runtime','full') else corpus/('ordinary' if workload=='small' else workload)
    source=corpus/('profiles.sqlite' if mode in ('profiles','runtime','full') else 'small.sqlite' if workload=='small' else 'model.sqlite')
    db=results/(name+'.sqlite');shutil.copyfile(source,db)
    out=results/(name+'.output')
    trace=results/(name+'.trace')
    env={**os.environ,'QDB_BENCH_VARIANT':variant,'FORUM_STATIC_PROFILE_PAGES_ENABLED':'false' if variant in ('profile-only','combined') else 'true','GIT_TRACE':str(trace),'VISITOR_STATISTICS_DATABASE_PATH':str(results/'visitors.sqlite'),'FORUM_SECRETS_PATH':str(results/'absent.php')}
    command=['/usr/bin/time','-f','Process tree RSS KiB: %M\nProcess tree user seconds: %U\nProcess tree system seconds: %S','php','-d','memory_limit=128M',str(project/'tests/Support/qdb_build_time_benchmark.php'),str(root),str(repo),str(db),mode,str(out)]
    if mode=='cli':
        bindir=results/'bin';bindir.mkdir(exist_ok=True)
        wrapper=bindir/'php';wrapper.write_text('#!/bin/sh\nexec '+shutil.which('php')+' -d memory_limit=128M "$@"\n');wrapper.chmod(0o755)
        env['PATH']=str(bindir)+os.pathsep+env['PATH']
        command=['/usr/bin/time','-f','Process tree RSS KiB: %M\nProcess tree seconds: %e\nProcess tree user seconds: %U\nProcess tree system seconds: %S',str(root/'v3'),'build-static',str(repo),str(db),str(out)]
    result=subprocess.run(command,env=env,capture_output=True,text=True)
    text=f'Sample: {name}\nExit: {result.returncode}\n'+result.stdout+result.stderr
    if mode=='cli' and result.returncode==0:
        connection=sqlite3.connect(db)
        for table in ['posts','profiles','activity','commits']:
            data=connection.execute('SELECT * FROM '+table+' ORDER BY rowid').fetchall()
            digest=hashlib.sha256(json.dumps(data).encode()).hexdigest()
            text+=f'Table {table} rows: {len(data)}\nTable {table} SHA-256: {digest}\n'
            if hashes.setdefault((mode,workload,table),digest)!=digest:result.returncode=1;text+='Read-model parity failure\n'
        connection.close()
    if out.is_file():
        digest=hashlib.sha256(out.read_bytes()).hexdigest();text+='Output SHA-256: '+digest+'\n'
        expected=hashes.setdefault((mode,workload),digest)
        if digest!=expected:result.returncode=1;text+='Parity failure: differing output hash\n'
    if out.is_dir():
        files=list(out.rglob('*.html'));profiles=[p for p in files if 'profiles' in p.relative_to(out).parts]
        text+=f'HTML artifacts: {len(files)}\nProfile artifacts: {len(profiles)}\n'
        # Preserve hashes, not hundreds of MiB of repeated generated HTML.
        with (results/(name+'.artifacts')).open('w') as file:
            for path in sorted(files):file.write(hashlib.sha256(path.read_bytes()).hexdigest()+'  '+str(path.relative_to(out))+'\n')
        shutil.rmtree(out)
    elif mode=='profiles':text+='HTML artifacts: 0\nProfile artifacts: 0\n'
    if trace.exists():text+='Git diff-tree processes: '+str(trace.read_text().count('built-in: git diff-tree'))+'\n'
    with report.open('a') as file:file.write(text+'\n')
    print(text,flush=True)
    if result.returncode:raise RuntimeError(name+' failed; sample retained')
    metrics=dict(line.split(': ',1) for line in result.stdout.splitlines() if ': ' in line)
    if mode=='cli':
        metrics.update(dict(line.split(': ',1) for line in result.stderr.splitlines() if ': ' in line));metrics['Seconds']=metrics['Process tree seconds'];metrics['PHP peak bytes']='0'
    rows.append((mode,workload,variant,float(metrics['Seconds']),int(metrics['PHP peak bytes'])))
    db.unlink()
for mode,workload,variants in [
    ('commits','ordinary',['baseline','commit-only','combined']),
    ('evidence','large',['baseline','evidence-only','combined']),
    ('evidence','small',['baseline','evidence-only','combined']),
    ('runtime','profiles',['baseline','profile-only','combined']),
    ('profiles','profiles',['baseline','profile-only','combined']),
    ('full','profiles',['baseline','combined']),
    ('cli','ordinary',['baseline','combined']),
]:
    if os.environ.get('QDB_BENCH_CASES') and mode not in os.environ['QDB_BENCH_CASES'].split(','):continue
    for repeat in range(1,4):
        for variant in variants if repeat%2 else list(reversed(variants)):run(mode,workload,variant,repeat)
with (results/'summary.txt').open('w') as file:
    for key in dict.fromkeys(row[:3] for row in rows):
        samples=[row for row in rows if row[:3]==key];values=[row[3] for row in samples]
        peak=max(row[4] for row in samples) or 'unmeasured (CLI cap enforced)'
        file.write(f'Case: {"/".join(key)}\nMedian seconds: {statistics.median(values)}\nRange seconds: {max(values)-min(values)}\nMaximum PHP bytes: {peak}\n\n')
print('Finished: '+str(report),flush=True)
