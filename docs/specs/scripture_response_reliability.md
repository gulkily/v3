# Verified scripture replies and expansions

Status: opt-in candidate; the [initial comparison failed release gates](../plans/scripture_response_reliability/evaluation/report.md). Do not activate as an accepted feature.
No production activation is implied by installation or a model change.

Deploy `resources/scripture/` with the application. Its README records the source,
coverage, original notices, importer, and hashes. The runtime accepts only the
pinned JSON digest. Keep the whole chapter text available for contextual review.
`SCRIPTURE_SOURCE_PATH` can point to another installed copy of the same file.
There is no runtime download, automatic update, or silent translation fallback.

In private config, `SCRIPTURE_RESPONSE_GUIDANCE=verified` applies the shared
quotation contract to scripture initial replies and Expand. The approved trial
uses `LLM_MODEL=gpt-5-mini`, `SCRIPTURE_RESPONSE_REASONING_EFFORT=low`, and
`AGENT_REPLY_MAX_COMPLETION_TOKENS=8192`. Other modes retain their existing
behavior; the model setting is shared and was already selected by the user.
Existing worker scheduling, moderation, permissions and duplicate controls still
apply. Optional immediate dispatch needs CLI PHP, `proc_open`, and `nohup` as
documented in the CLI reference; keep scheduled queue recovery enabled.

The writer selects references rather than inventing verse text. Source-owned
quotation segments supply KJV wording and translation labels; optional excerpts
must match ordered source substrings and omissions are marked. Generated prose
is interpretation/practical reflection; quotation markup and direct-speech forms
there are rejected. This structural safeguard does not prove arbitrary prose is
faithful interpretation. Independent final-answer/context review remains required.
Typographic apostrophes/dashes follow the existing display normalization policy;
normalization that further changes a rendered quotation fails publication.

Requested/supplied unsupported translations receive an explicit limitation and
no substituted KJV quotation. A complete answer may use labeled paraphrase;
otherwise it fails. Missing/corrupt source behaves the same way. Incorrect source
references, mismatched excerpts, malformed documents and unverified old drafts
fail without partial publication. Status polling never generates another answer.

Operators inspect the private generation failure, correct configuration/source
availability where relevant, and use the existing targeted agent-reply retry
command. A retry is explicit and may make a new paid call. Recovered unpublished
documents are revalidated before publication. Existing published replies are not
rewritten or retrospectively labeled verified. New expansions independently
verify new content, including when the original reply predates this feature.

For rollback, first disable `agent_response_requests_enabled` in feature flags
and stop new trial/provider dispatch. Drain or explicitly fail pending verified
work under the same verification settings; do not switch it to unverified writing.
Then restore prior opt-in settings as appropriate. Historical canonical replies
and stored expansions remain readable. Re-enable requests only after deciding
which behavior is intended. A persisted verified draft cannot be published under
a different guidance mode without explicit recovery.

Evaluation uses `tests/fixtures/scripture_reliability/protocol.json` and
`tests/Support/scripture_reliability_evaluation.py`. `rehearse NEW_PRIVATE_DIR`
uses a stub transport through real HTTP/worker/browser delivery; `initial
NEW_PRIVATE_DIR` performs the 32 approved initial calls. `expand EXISTING_DIR`
requires saved passing initial gates. Both candidates use automatic dispatch and
polling. The ledger reserves before calls and retains unknown charges across
restart; hard ceilings are 48 writing calls, 96 moderation calls and $5 total.
No automatic retries, extra judge calls, or production trial posts are allowed.
Final stored answers must match rendered browser text before anonymous review.
Saved records include question, initial context for expansions, final text,
failures, timings and hashes; the key stays in memory, outside copied config.
Mini pricing was verified 2026-10-16 against the [official model page](https://developers.openai.com/api/docs/models/gpt-5-mini):
$0.25 per million uncached input tokens and $2 per million output tokens,
including reasoning. Reservations conservatively ignore input caching.
The official page currently marks the selected model deprecated; no substitute
is authorized. If the endpoint rejects it, retain the failure and reassess.

Frozen artifacts must not change during trials. A failed gate or exhausted budget
blocks promotion and dependent paid expansions. An experiment report is evidence,
not successful feature completion.
