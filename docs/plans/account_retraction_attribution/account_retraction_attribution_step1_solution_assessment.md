> **Feature plan:** [Step 1](./account_retraction_attribution_step1_solution_assessment.md) · [Step 2](./account_retraction_attribution_step2_feature_description.md) · [Step 3](./account_retraction_attribution_step3_development_plan.md) · [Step 4](./account_retraction_attribution_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

Please write Step 1 for this modification.

## Understood Intent

When an operator retracts a post written using another approved key for the same canonical username, display “This post was retracted by its author.” Preserve the actual signing identity and operator authorization in the audit record.

## Problem Statement

Retraction attribution compares exact signing identities even though the application recognizes multiple approved identities sharing a canonical username as keys for one account.

## Option A Match usernames alone

Display author attribution whenever the signer and original author share a canonical username, regardless of approval.

- **Pros:** Simple and consistent with the visible username.
- **Cons:** An unapproved identity claiming that username could receive misleading author attribution; overlooks the account’s approval boundary.

## Option B Recognize approved keys for the same account

Keep exact-identity self-retractions attributed to the author. For different identities, display author attribution only when both identities are approved and share a nonempty canonical username; otherwise retain operator attribution. Preserve the underlying signed action and existing authorization rules.

- **Pros:** Matches existing account grouping and additional-key approval; retains accurate signer evidence without granting new permissions.
- **Cons:** Older records require account evidence; attribution needs a clear policy when approval state changes or historical evidence is incomplete.

## Option C Require a direct same-account approval link

Recognize different keys as the author only when a direct approval between those keys also establishes their shared username.

- **Pros:** Provides an explicit relationship between the two keys.
- **Cons:** Excludes legitimate keys approved through operators or intermediate account keys; introduces a stricter account definition than the application currently uses.

## Recommendation

Choose **Option B**, separating the displayed account attribution from the signed authorization classification.

- **Complete outcome:** Operator retracts a post under another approved key for the same account → all retracted-post notices say “by its author” → signature and audit inspection still identify the actual signer and operator action. Different-account actions continue to say “by an operator.”
- **Approval boundary:** Use canonical account usernames, not display labels or key user-ID text. Both distinct identities must qualify as approved; a matching name alone is insufficient. Direct or mutual approval between those particular keys is unnecessary.
- **Stable meaning:** Prefer account evidence at acceptance time for new actions, so later approval changes do not silently relabel them. Apply author attribution to historical different-key actions only where reliable acceptance-time evidence establishes the relationship; otherwise preserve operator wording. Validate feasibility early rather than infer historical approval from current state.
- **Scope:** Cover every existing retraction notice, including unavailable-source responses. Preserve canonical signatures, actual actor attribution, operator authority checks, and existing suppression behavior. Do not extend ordinary users’ permission to retract posts signed by other account keys.
- **Validation:** Check exact-key self-retraction, approved same-account keys, different usernames, unapproved matching names, case normalization, historical ambiguity, rebuild consistency, and unchanged audit evidence. This is a bounded presentation change; historical evidence is the main risk to resolve before implementation.

## Review

Approved Step 1 by the user; proceed with Option B.
