# Multi-language scripts

Some operator and client scripts ship in **PHP, Perl and Python on purpose**. Each set is one tool written three times, with the same options, output and exit codes. It is not leftover duplication, so code-quality passes should not merge or remove them. We expect to add more sets.

## Why

- **The API works from anywhere.** A working client in three languages shows that the write and read APIs need nothing v3-specific: plain HTTP, form fields, and `gpg` for signing. Each version is a short, readable worked example for people writing their own clients.
- **It runs on the machine you have.** A shared host may have Perl but no Python, or PHP without the extensions you'd expect. With three versions, at least one usually runs on a minimal host with nothing to install.
- **Each version checks the others.** When the versions disagree, the parity tests below fail, which catches unclear spots in the API or the docs.

## Current sets

| Tool | Files | Docs | Parity tests |
|---|---|---|---|
| Text file publisher | `scripts/publish_text.{py,php,pl}` | [Agent API: text file publisher scripts](agent_api.md#text-file-publisher-scripts) | `tests/publish_text_transport_test.py`, `AgentApiWorkedExampleTest` |
| Read-load probe | `scripts/stress_probe.{py,php,pl}` | [Server stress probe runbook](../runbooks/server_stress_probe.md) | `StressProbeScriptTest` |

## Rules for a set

1. **Same interface.** All versions take the same options, print the same output, and use the same exit codes. Any version's `--help` describes all of them.
2. **Standard tools only.** Use each language's standard library plus tools already present: `gpg`, and `curl` for Perl. Nothing extra to install. Each header states the minimum version and any extensions it needs.
3. **No shell.** Run processes with argument lists (`proc_open([...])`, list-form `open`, `subprocess.run([...])`), never a command string.
4. **Self-describing headers.** Each file says it belongs to a set, names its siblings, and links here.
5. **Tested together.** One test runs every version against the same cases. A change to one version lands with the same change to the others in the same PR.

## Adding a set or a language

- **New set:** write the first version, then port it. Add a row to the table above, a docs section, and a parity test that loops over every version.
- **New language:** add a file with the same stem (for example `publish_text.rb`), add its interpreter to that set's parity test, and add it to the table and the tool's docs. Keep to the rules above.
