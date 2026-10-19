# Local retraction sweep

`./v3 retraction-sweep preview` inspects validated retractions and up to 250
exchange rows without creating or changing storage. Override `--limit=1..10000`,
`--repository-root`, `--exchange-database-path`, `--state-path`,
`--static-html-root`, or `--database-path` for an isolated instance.

Coverage: generated HTML in configured presentation roots (including releases
and build candidates), served offline snapshot/update databases and manifests,
and LLM exchange payloads with proven retracted dependencies. Unknown exchange
provenance stays withheld and is not sufficient evidence for deletion.

Exclusions: canonical records and Git history, analysis/Unicode-risk/Fastmod/media
caches, agent-generation stores, application/task/provider logs, read-model
recovery/candidate copies, backups, attachments, published replies, provider and
remote data, downloaded and browser copies. Read-model refresh remains the
existing retraction flow's responsibility. The sweep never modifies signed
authority evidence. Logical cleanup is not forensic secure erasure.
