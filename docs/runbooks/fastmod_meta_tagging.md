# Score-derived root tags

Fastmod adds effective labels when posts are read or generated output is built.
It writes no tag records, Git commits or separate classification decisions.
The first mapping is `meta`: a visible top-level post's retained meta score must
be at least `FASTMOD_META_THRESHOLD` (default 0.90, inclusive). Replies never
label themselves or their roots. Ordinary and historical/backfill scores qualify.

## Enable and verify

Enable scoring and the meta rubric in Tools → Fastmod, then enable
**Automatic Fastmod tagging** in Tools → Feature Flags → Fastmod. Automatic
enqueue supplies scores for new posts; existing qualifying scores need no new
provider call. One shared switch controls all automatic mappings. This release
ships only meta, with no mapping editor or per-tag switch.

The selected score must be successful and match current content and the current
rubric revision. Missing/failed scores or a missing mapped rubric add no label.
Changing rubrics/content can therefore remove labels until scores match again.
Changing the threshold, replacing scores or pruning them changes derived labels.
No model call happens during reading, enabling tagging or changing the threshold.
Human labels are preserved and duplicate labels are shown only once.

```bash
./v3 fast-score meta-status
./v3 fast-score meta-status --repository-root=PATH --json
```

Status is read-only and does not initialize private state. Candidate counts are
score matches, not a count of visible, content-matching tagged roots. Visit
`/tags/meta` to check actual discovery. Missing scores leave human labels readable;
restore the private score backup or use existing authorized scoring work, then
refresh generated output. A missing score database produces no derived labels.

## Disable and correct

Disabling **Automatic Fastmod tagging** hides derived labels on live reads;
ordinary scoring continues. Re-enabling makes retained qualifying scores eligible
again, including scores produced while tagging was off. Neither action edits
human labels. Existing legacy suppressions remain read-only exclusions for meta.

`meta-remove`, `meta-rollback` and `meta-retry` are retired and return an explicit
error with recovery guidance. There is no new individual exception workflow.
For a failed evaluation, use the ordinary Fastmod retry commands; for unwanted
automatic labels, disable the shared switch or adjust the private threshold.
Do not edit SQLite to clear old suppressions.

## Generated output and upgrade recovery

Static HTML, high-traffic pages and offline snapshots capture effective labels
at their normal build/refresh time. Changes do not immediately invalidate all
copies. Run the existing full `build-static` or offline publication command for
updated complete output; shared refresh only refreshes its documented shared
routes. High-traffic pages use their existing refresh policy. Already downloaded
copies stay snapshots until refreshed. Old Fastmod generation sidecars are ignored.

Upgrade code together, stop old workers first, and preserve a consistent private
SQLite backup (including WAL state). Old active classification decisions no longer
supply labels; existing suppression rows remain authoritative exclusions. Legacy
tables are retained but no longer written. The normal one-year score pruning
policy can now remove derived labels. Canonical Git does not contain private
scores or suppressions and cannot restore them.

For a code downgrade, stop writers and restore a matching pre-upgrade private
backup before running old workers; rebuild generated output. Do not reinterpret
current derived labels as old durable decisions. No automatic downgrade is provided.

Public exports contain effective labels only, never private scores or evidence.
Private scores stay on a separate read-only connection from user-entered SQL.

## Verification

Focused derived-tag, CLI, flag and offline tests plus `node tests/browser/fastmod.mjs`
cover the deterministic mechanics with a local fake provider. These checks do
not measure real-model accuracy.
