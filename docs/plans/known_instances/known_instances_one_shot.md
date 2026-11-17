# Known instances one-shot record

Cycle: `v3/known_instances`; Mode: one-shot; Record/index: [this record](./known_instances_one_shot.md), [plans index](../README.md)
Debug mode: enabled (no findings beyond the parent cycle's log)
Effort study: `railspike-effort` / `v3/known_instances`; [host ledger](../effort_estimation_trial.md)
Extended estimates: disabled (single work unit)

**Cycle estimate: 0.10**

Status: locally complete; next permitted action: push, open the PR, reply in the thread.

Original request: "we should also offer some existing instance choices" (2026-11-17 UTC). Answers, 2026-11-17 UTC:
- "only list zenmemes.com for now, but anticipate adding more";
- "an instance should hide its own address. this is generally for localhost though";
- "the list ships with the code for now";
- "this feature is largely for use on localhost for now".

Feedback: PF-20261117-F02. Thread: https://zenmemes.com/threads/thread-20261113042243-1cbe8b8a.

Authorization: maintainer in session; one-shot through PR and thread reply.

Outcome and work type: application change.
- `resources/known_instances.txt` (`Key: value` blocks with `Name:`, `Title:` and `URL:`) lists ZenMemes.
- Tools → Import offers it after remembered sources, and prefills it when nothing is remembered.
- The local start page's prefilled source comes from the list instead of a hard-coded URL.
- `./v3 import-instance zenmemes` works, and a `--sources` file still overrides the name.
- Each site leaves out entries whose host (port included, `www.` and default ports ignored) matches the request's Host.

Approach and risks: follows the `resources/tagsets.txt` pattern (tracked plain text with a schema line and comments). Risk: the local start page could lose its prefilled source if the list were empty. The default is now the first other known instance, or empty.

Reuse decisions:
- `KnownInstances` (`src/ForumRewrite/Import/KnownInstances.php`), modeled on `Tagsets`;
- `InstanceSourceResolver` name lookup;
- the existing datalist on `tools_import.php`;
- `LocalDevelopmentPageController`'s default source.

Completion conditions: on localhost, Tools → Import shows https://zenmemes.com prefilled and listed, and `./v3 import-instance zenmemes` resolves. On zenmemes.com, the list leaves it out.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (known instances line).

Verification: see the PR.

Starting revisions and branch: `96462cfd`; `feat/known_instances`.

Study history: intake 0.10 after reading the resolver and both pages (no pristine intake); closeout pending; Claude single exposed rater; R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
