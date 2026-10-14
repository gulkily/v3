> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

# Signed Thread Label Records Step 1 Solution Assessment

## Original Query

I would like this, I kinda thought it was already the case. We should keep unsigned compatible though.

(Context: thread-label records, e.g. a "like" tag, are committed as a single `.txt` file with no detached signature, while posts carry a `.asc`.)

## Understood Intent

Likes and tags should be cryptographically attributable to the identity that applied them, the way posts are. Existing unsigned label records must keep working unchanged.

## Problem Statement

Thread tags and likes are recorded unsigned, so the `Author-Identity-ID` header is only the server's word; the offline signed-reaction path verifies a signature and then discards it.

## Option A: Browser-signed label records, optimistic single request with prepared fallback

The browser builds the label record itself, signs its exact bytes with the viewer's stored key, and submits record and signature in one request. The server validates the record, signature, target, and timestamp, then commits it with a `.asc` beside it. If the server rejects the request because the client clock is too far from server time, the browser falls back to a second request: the server prepares the record with its own identifiers and timestamp, the browser signs those bytes, and the server commits them, reusing the post and retraction flow.

Pros:
- One round trip in the common case; two only when the clock disagrees.
- Same trust model as posts; signed labels are verifiable on import, restore, and in the source view.
- The fallback reuses the existing prepare/finalize flow.

Cons:
- Two submission paths to build and test, plus a skew threshold to tune.
- Server must validate client-chosen record identifiers.
- Viewers without a usable key still need the unsigned fallback.

## Option B: Keep the verified offline signature

The offline signed-reaction path already verifies a detached signature over an intent document. Store that intent and signature as evidence beside the label record instead of dropping them.

Pros:
- Smallest change; no new browser flow.
- Records signatures that already exist.

Cons:
- Covers only offline-queued likes, not normal online tags.
- Signature is over a different document than the record, so it is a weaker, non-uniform proof.

## Option C: Server-attested labels

The server signs label records with an instance key, asserting it checked the viewer's session.

Pros:
- Works for every viewer, including those without a key; no UI change.

Cons:
- Attests the server, not the author; adds no author authority.
- Introduces instance-key management and a second trust model.

## Revised Direction

After review: the server need not prepare every label record. Use an optimistic single request with a server-prepared two-request fallback when client/server time skew is too large (Option A as revised above).

## Recommendation

Recommend Option A as revised, tolerant of what exists: signed labels are committed with a `.asc` and verified when present; unsigned labels remain valid and are shown as unsigned; a present-but-invalid signature is rejected. First slice: thread likes and tags end to end (apply, verify on write and import, read-model "signed" flag, source view). Post reactions follow the same pattern and fit the same slice only if it stays within eight stages; otherwise they follow separately. Whether to eventually require signatures is a later policy decision.

Open questions for Step 2: skew threshold and who owns it; client-chosen record identifier rules; viewers without keys (including guests); labels auto-created from hashtags in a post body; whether the offline outbox adopts the same signed record; and how unsigned labels are displayed.
