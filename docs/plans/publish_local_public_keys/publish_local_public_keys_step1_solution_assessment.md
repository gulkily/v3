> **Feature plan:** [Step 1](./publish_local_public_keys_step1_solution_assessment.md) · [Step 2](./publish_local_public_keys_step2_feature_description.md) · [Step 3](./publish_local_public_keys_step3_development_plan.md) · [Step 4](./publish_local_public_keys_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/publish_local_public_keys; [cycle ledger](../effort_ledger/publish_local_public_keys.md), standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.50**

## Original Query

“we've worked on this stuff before, is there  yet a feature to cross-pollinate public keys to prod?”

“please go ahead”

## Understood Intent

Let an operator publish selected local public keys to production, see which already exist, and retry safely. Keep private keys local and approvals separate. This authorizes Step 1 only.

## Problem

Public keys already have a registration path, but no local command compares and publishes a selected set to another instance.

## Option A — Local publisher using existing registration

**Estimate: 0.50**

A local command previews missing identities at an explicitly named destination, then publishes selected public keys through its existing registration path.

- Reuses existing key inspection and registration; no private keys, reachable localhost server, or new remote write API needed.
- Registration derives the initial name from the key. Flag renamed profiles and local/destination retirement differences; do not silently change identity or claim approval.
- Driver: destination checks, partial acceptance, and lost-response recovery. Restricted destinations may refuse access; preserve that boundary.

## Option B — Selective transfer of public identity records

**Estimate: 0.90**

Extend import to accept a selected public identity bundle at the destination, retaining compatible identity history instead of recreating registrations.

- Better fit when exact local identity records and name history must travel; keep approvals and private records excluded.
- Requires selective import, delivery of the bundle, and broader conflict/authority checks. Production cannot directly fetch an ordinary localhost address.
- Driver: archive validation and interrupted import recovery. Shared verification and documentation are included, not added to A.

## Recommendation and completion

Choose A as the first vertical slice. A preview shows selected fingerprints, destination names and missing/existing/refused states. Publishing confirms accepted identities from the destination. A repeated run skips existing keys; a lost response triggers read-back before another write. Names or retirements that cannot be preserved remain explicit unresolved results. Existing keys and approvals are never overwritten.

Localhost-to-production is the intended route; test equivalent local instances first. A live production trial needs separate authorization. Exclude approvals, posts, private-message stores, history bundles, automatic background sync and a new web page. PF-20261121-F03 is the broader reverse-import request; PF-20261121-F11 remains a separate approval-transfer request. Their accepted records currently live in the saved triage worktree, not this main checkout.

## Concepts touched

Public-key publication: existing behavior, proposed inventory entry if implemented. Use [approval acceptance](../../concepts/approval_acceptance.md) and [key retirement](../../concepts/key_retirement.md) unchanged. B would extend [instance content import](../../concepts/instance_content_import.md). [Source/reuse evidence and risks](publish_local_public_keys_assessment_evidence.txt).

## Study snapshots

Intake: 2026-11-22T21:04:12+00:00; Codex, single exposed rater; scope v1, starting revision 22fc9f10a4d4f8cba9ecaed501aba2ea90d129e4; R1/P2; 0.50. Exposure: prior conversation, import/approval documentation, and earlier identity API review; not a pristine intake. Frozen R1 hashes verified against docs/plans/effort_estimation_trial.md before rating. Includes planning, implementation, required verification, documentation and retry recovery; not hours or approval. Detailed current-path inspection follows this snapshot.

Step 1 handoff: 2026-11-22T21:06:22+00:00; same rater/scope/reference; 0.50 reaffirmed for A. B is 0.90, independently judged. New understanding: unsigned public-key registration is sufficient, but renamed profiles need explicit refusal or deferral. No independent rating, measured effort or outcome acceptance.

*— Codex · 2026-11-22 UTC*
