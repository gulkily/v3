> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

# Signed Thread Label Records Step 2 Feature Description

## Problem
Thread labels and post reactions (including likes and flags) name an author without preserving that author's signature over the saved record. Readers cannot independently verify this attribution, while existing unsigned records must remain usable.

## User Stories
- As a participant, I want my likes, flags, and tags signed with my identity so that others can verify their attribution.
- As a reader or operator, I want verifiable records and continued unsigned compatibility so that existing content remains usable without overstating its authenticity.

## Core Requirements
- All explicit online voting/tag buttons for both thread labels and post reactions—including thread/reply Like, every Flag, and QDB caption votes— use the browser's usable identity key to sign the exact saved record. Persist its detached signature alongside it; verify author, target, labels/tags, identifier, and signature before accepting it.
- Use one submission normally. The server owns a five-minute clock-skew tolerance; outside it, return a prepared record for browser signing and final submission. Signing errors remain retryable errors, never silent unsigned downgrades.
- Preserve unsigned records, legacy clients, and current eligibility rules. Eligible viewers without a usable key retain unsigned submission; guests use existing identity setup. Invalid supplied signatures are rejected, never treated as absent.
- Verify both signed record families on import, restore, and rebuild; invalid signed records cannot affect labels, scores, or moderation. Expose per-record verified/unsigned status through derived data and source/activity detail, with the signature accessible. Mixed aggregates must not imply every contributing record is signed.
- Preserve score, vote, moderation, and duplicate semantics for each target kind. Repeated submissions cannot overwrite records or count twice; record and signature remain paired through backup, archive, and restore. Recover interrupted writes or derived-state refreshes without asking users to repeat a counted vote.

## Delivery Scope
**Work type:** Application change. The slice covers explicit online thread-label and post-reaction actions through persistence, verification, and inspection, using shared signing behavior. Signing hashtag-generated labels and converting the offline intent/outbox format remain deferred; their unsigned output remains identified as unsigned. Record-format consolidation is a separate design decision pending discussion; this scope does not authorize migration or rewriting canonical records. Existing records are not retroactively signed; mandatory signing is outside scope.

## Completion Boundary
Enter through existing thread, post/reply, and QDB voting/tag controls; finish with one applied label or reaction and independently verifiable attribution, visible after refresh and rebuild. Clock skew, unavailable keys, failed signing, retries, and invalid imported signatures have defined outcomes above. Release requires normal browser-flow checks, signature/compatibility regressions, and record-pair preservation checks to pass within eight development stages; otherwise rescope before implementation.

## Risks
- **Byte or identity mismatch:** valid actions could fail verification. Early validation: trace browser signing and canonical parsing before planning dependent stages; mitigate by preserving exact bytes and binding the signer to the author.
- **Clock skew and retries:** fallback could loop or duplicate votes. Early validation: review prepared-write and duplicate handling; mitigate with bounded fallback, safe unique client identifiers, collision rejection, and repeatable completion. Historical imports are not subject to the live clock window.
- **Misleading trust status:** legacy, hashtag, and offline records could appear verified. Early validation: trace both record families’ producers and readers; mitigate with per-record status and explicit unsigned treatment of deferred producers.
- **Lost signature companions:** archive/import paths could turn signed records into apparently unsigned records. Early validation: inventory record lifecycle handling; mitigate by preserving pairs and testing tampered signatures. Compatibility cannot detect deliberate removal of a signature from an otherwise valid unsigned-format record.

## Shared Component Inventory
- **Actions:** extend shared `thread_reactions.js`, thread/root/post cards, Forte board content and reply trees, and QDB quote actions; reuse browser signing, identity setup, and prepared post/retraction conventions.
- **Write APIs:** extend `TagApiController` and `LocalWriteService` thread-tag and post-tag flows with shared signing/verification; preserve signed-intent/outbox and compose/hashtag flows under the scope above.
- **Readers:** reuse thread/authored-content repositories and shared rendering across home, board, thread/post, tag, profile, and QDB views; preserve text API, SQLite viewer/download, and offline snapshot/reader label and score output.
- **Evidence/lifecycle:** extend canonical verification, full/incremental read models, classic/Forte activity detail, and shared source metadata; reuse import/sync, backup/restore, and archive paths. No separate theme-specific signing UI.

## Simple User Flow
1. Select Like, Flag, a QDB vote, or another tag on a thread or post/reply using existing controls.
2. Browser signs and submits; clock skew triggers prepared signing, while an eligible keyless viewer uses the unsigned path.
3. Server validates and saves once; the UI updates or offers recovery on failure.
4. Open activity/source detail to inspect verification status and the saved evidence.

## Success Criteria
Every supported online voting/tag button with a usable key saves a verifiable record/signature pair in one submission under normal clock conditions; skew fallback succeeds without duplication. Unsigned fixtures and clients retain their behavior. Altered bytes, wrong signers, unsafe/colliding identifiers, and invalid signatures cannot produce accepted signed labels or reactions. Refresh, rebuild, and archive/restore retain counts, moderation outcomes, and verification status; source detail distinguishes signed and unsigned records accurately.

Waiting for "Approved Step 2" before drafting Step 3.
