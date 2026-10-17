> **Feature plan:** [Step 1](./root_approval_pages_step1_solution_assessment.md) · [Step 2](./root_approval_pages_step2_feature_description.md) · [Step 3](./root_approval_pages_step3_development_plan.md) · [Step 4](./root_approval_pages_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Account key history opens readable post pages for ordinary approvals but plaintext files for root approval seeds. Readers need an understandable evidence page without changing what those seed records mean.

## User stories

- As an account holder, I want root approval evidence to open a readable page so that I can see which key was approved and the recorded reason.
- As a reader investigating an approval, I want access to its canonical source and available repository history so that I can verify the displayed facts.

## Core requirements

- Link root-seed Approval evidence entries from classic/Forte account and profile history to a dedicated readable page; retain ordinary approvals' post links and separate acceptance receipts.
- Show the approved key, available current account/profile links, recorded seed reason, and a clear explanation that this is a root approval seed. Use existing themed page components and readable, wrapping key labels.
- Distinguish recorded seed facts from unknown authorizer or acceptance time. Any repository commit information is repository evidence, not a claimed approval timestamp or member signature.
- Provide raw-source and available commit/history links using established source components. Validate the seed and its identity/path binding with existing canonical rules.
- Follow existing profile/source access boundaries. Missing, invalid, or unavailable records produce an appropriate error page without fabricated evidence or internal error details.

## Delivery scope and completion boundary

Application change covering seed evidence pages, account-history links, existing evidence lookups, theme styling where needed, and focused tests. No changes to seed authority, canonical records, approval workflows, signatures, revocation, imports, or Activity event indexing. No schema migration or production deployment.

Complete the history → root evidence → profile/source/history flow for existing seeds, including direct URLs, safe error handling, and private-site access checks. Release after focused route/rendering tests and desktop/phone UI checks pass.

## Risks

- False historical certainty: inspect parser fields and history evidence first; label unknown acceptance/authorizer facts explicitly and distinguish repository dates.
- Private evidence exposure or invalid record selection: validate route access and seed identity/path binding before rendering; reuse canonical validation and existing membership rules.
- Misleading links after account changes or missing profiles: check seeds independently of optional current profile details; show available account links without treating current names as historical facts.

## Shared component inventory

Reuse ApprovalSeedRecordParser, CanonicalRecordRepository, and ApprovalPolicy seed-validation conventions. Extend ApprovalHistory's shared account/profile presentation links. Reuse profile lookups, themed detail/card components, and source/commit evidence helpers; add a dedicated detail page because seeds are not posts. Existing source routes remain available. Profile API and CLI approval audit remain consumers of existing facts; no new authority or API format is needed.

## User flow and success criteria

1. Open account key history and select Approval evidence for a root-seeded key.
2. Read the key and recorded reason, understand the limits of historical evidence, and follow available account/profile, source, and repository links.
3. A missing/invalid record shows a clear error; unauthorized private-site visitors receive the established access response.

Success: existing valid root seeds resolve from both history variants and direct URLs; ordinary approval links still open posts; no invented dates/authors/signatures; access, malformed-record, and responsive wrapping checks pass.
