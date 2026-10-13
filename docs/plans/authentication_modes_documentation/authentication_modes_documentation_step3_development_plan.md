> **Feature plan:** [Step 1](./authentication_modes_documentation_step1_solution_assessment.md) · [Step 2](./authentication_modes_documentation_step2_feature_description.md) · [Step 3](./authentication_modes_documentation_step3_development_plan.md) · [Step 4](./authentication_modes_documentation_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Scope: documentation-only changes to `docs/reference/authentication_modes.md` (new), `README.md`, `docs/architecture/public_architecture_and_trust.md`, and `docs/plans/session_reauthentication_reference.md`, plus this feature's Railspike artifacts and required plan-index/link organization.
- Outcome: visitors, operators, and maintainers can enter through README/architecture guidance, identify access and identity state, determine permitted actions, and follow supported recovery guidance. Historical proposals are clearly distinguished from current behavior.
- Evidence/recovery boundary: support matrix and recovery claims with current source and relevant existing tests; label unresolved behavior and distinguish inspected from executed checks. Material uncertainty blocks dependent claims until resolved or explicitly narrowed; runtime fixes or wider scope require renewed planning approval.
- Completion: all four documents agree, representative scenarios and repository/rendered links pass review, and verification/limitations are recorded in Step 4. Application, test, CI, deployment, and generated-artifact changes are excluded. Production deployment, migrations, and broad runtime regression checks are not applicable; targeted checks supporting documentation claims remain applicable.

## Key Risks

- **High risk:** Confusing identity with approval misdirects readers. Early controller/flag inspection confirms separate gates; validate each permission claim before publication.
- **High risk:** Stale recovery claims or unreadable documentation block useful guidance. Existing guard/tests contradict the session reference; the renderer lacks table support. Trace recovery paths and use supported headings/lists for the access matrix.
- Scope expansion: defects discovered during review could trigger unauthorized changes. Check the four-file boundary first; record follow-up findings instead of changing runtime behavior.

## Execution Gates

- After `Approved Step 3`, create a feature branch, organize this feature's plans/debug log under `docs/plans/authentication_modes_documentation/`, repair links/index, and commit only approved plans plus permitted organization/log changes. Preserve unrelated work, including `todo.txt`.
- Create Step 4 afterward with inherited debug state. Each stage below takes approximately one hour or less; verify, update its summary, and commit before proceeding. Minimum: one planning commit plus two stage commits; preserve commit history.

## Stage 1

- Goal: deliver a discoverable reference from site settings through participation and recovery.
- Dependencies: planning commit; permission and recovery evidence checked before claims are written.
- Expected changes: create the reference with definitions, a list-based access/state matrix, flag defaults/interactions, route exceptions, action limits including invitations, and recovery cases; link it from README and correct README access guidance.
- Verification approach: trace public/members-only and guest-preparation on/off behavior; check anonymous, guest/named, unpublished/published, hinted, authenticated-unapproved, and approved states. Inspect source and relevant tests; execute focused authentication/navigation tests where used to substantiate recovery. Check reference rendering and README links.
- Risks or open questions: incorrect permission implications; validate each matrix entry against its gate and explicitly qualify unverified cases before proceeding.
- Canonical components/API contracts touched: documentation only; reuse authentication, identity, approval, invitation, and signed-action contracts as evidence without changes.

## Stage 2

- Goal: reconcile existing guidance and verify the complete reader journey.
- Dependencies: Stage 1 verification, summary, and commit.
- Expected changes: link architecture guidance to the reference; correct session-reference status, navigation-guard claims, and proposal-era framing while preserving historical context. Make necessary consistency corrections within the four-file scope.
- Verification approach: cross-check all four documents; trace public, unapproved, approved, expired-session, missing-key, and failure scenarios. Check filesystem links and rendered `/docs/` navigation through existing renderer/catalog checks; record evidence, executed results, unresolved labels, whitespace checks, and changed-file scope.
- Risks or open questions: repository-relative links may fail in-app; inspect rendered destinations and use compatible links, escalating any required runtime change before proceeding.
- Canonical components/API contracts touched: existing documentation renderer and entry points reused unchanged; no new UI, API, or catalog component.
