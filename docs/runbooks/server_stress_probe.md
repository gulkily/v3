# Server Stress Probe

`scripts/stress_probe.py` sends a small, stepped read load at a v3 site and reports
latency, status codes and high-traffic cache hits for each step. It sends GET requests
only. Use whichever runtime the machine has; the three scripts take the same options
and print the same plan, output and record:

| Script | Needs |
| --- | --- |
| `python3 scripts/stress_probe.py` | Python 3.7+, standard library only |
| `php scripts/stress_probe.php` | PHP 8.1+ with the curl extension |
| `perl scripts/stress_probe.pl` | Perl 5.20+, core modules; HTTPS also needs IO::Socket::SSL (Debian/Ubuntu: `libio-socket-ssl-perl`) |

The three versions are kept on purpose; see [multi-language scripts](../reference/multi_language_scripts.md).

Network error names come from each runtime, so they read differently; everything
else matches. The examples below use the Python script.

Run it from a machine the host already knows, such as one that has signed in to the
hosting control panel. Do it at a quiet time, and stop if the host warns you.

## Plan first

Without `--confirm`, the script prints what it would do and sends nothing:

```bash
python3 scripts/stress_probe.py https://zenmemes.com
```

To send traffic, repeat the target's host name:

```bash
python3 scripts/stress_probe.py https://zenmemes.com --confirm zenmemes.com
```

A `--confirm` value that doesn't match the host exits with status 2 and sends nothing.

## What it does

Each step runs a fixed number of concurrent readers (`--steps`, default `1,2,4,8`,
at most 32) for `--seconds` (default 20, at most 60), with `--pause` seconds between
steps. All readers share one request-rate cap, `--max-rps` (default 50, at most 200).
Raise the cap a little at a time between runs.

`--mode cached` (the default) reads `/`, `/threads/`, `/about/` and a few thread pages
found on the home page. High-traffic mode serves these from its cache, so the result
should show cache hits near 100%. `--mode uncached` reads the same routes with a unique
query string, plus `/recent/` and `/search`, so every request reaches PHP. `--mode both`
mixes the two and reports them separately. Add routes with `--path`.

The run stops after the first step that breaks a stop rule:

- any `429 Too Many Requests`;
- 5xx and network errors above `--max-error-rate` (default 2%), not counting load-shedding
  `503` responses that carry `Retry-After`;
- load-shedding `503` responses above `--max-shed-rate` (default 25%);
- p95 latency above `--p95-ms` (default 2000).

While it runs, it says when it reads the home page for thread links, starts each step
and rests between steps. During a step it prints a progress line every `--progress`
seconds (default 5; `0` turns it off) with elapsed time, requests so far, the rate, p95
over the last interval and the status codes so far.

The exit status is 0 when every step passed and 1 when the run stopped early.
`--record FILE` saves the result as a `Key: value` text record.

## Suggested order

1. `--mode cached` at the defaults: the baseline for Apache-served cache hits.
2. `--mode uncached --steps 1,2,4`: where PHP worker limits show up.
3. Writes, by hand: while a cached step runs, post one reply with `frm.py` and note how
   long it takes and whether it appears on its thread page right away.

For a cold cache, run `./v3 high-traffic purge` on the server first; expect some `503`
responses with `Retry-After: 5` while pages generate, two at a time.

## While it runs

On the server, watch `./v3 status`, the Apache error log and the load average. Keep
the record of each run with the release it ran against.
