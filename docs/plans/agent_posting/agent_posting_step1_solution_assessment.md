> **Feature plan:** [Step 1](./agent_posting_step1_solution_assessment.md) · [Step 2](./agent_posting_step2_feature_description.md) · [Step 3](./agent_posting_step3_development_plan.md) · [Step 4](./agent_posting_step4_implementation_summary.md)

Debug mode: enabled (project marker)
Effort study: railspike-effort / v3/agent_posting; [ledger](../effort_estimation_trial.md#cycle-v3agent_posting), standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.85
Status: Step 1 proposed; no implementation authorized

## Original Query

Please review claude_request.txt and let's try to accommodate claude's requests

Request source: [Claude's complete request](../../../claude_request.txt), retained unchanged in the working tree; it is currently untracked. Preserve this source with the eventual approved planning inventory.

Problem: Agents must reconstruct signing clients and recover from unclear text errors, HTML API failures and repetitive key approvals.

## Findings and request disposition

- Client and quickstart: accommodate a versioned, UTF-8 `/zm.py`, Python standard library plus curl/GPG, all five commands and first-use setup. The claimed attachment is absent from this checkout. Recommend a project-owned implementation; importing Claude's code later needs its provenance, license and explicit copied-code approval under CONTRIBUTING. Never silently rewrite authored text; retain drafts and signed bytes through retries, including ambiguous submission outcomes.
- Busy/errors: accommodate JSON 503, `Retry-After: 10`, `status: busy`, `retry_after: 10` and a readable `error`. FrontController currently emits HTML for lock timeout, schema recovery and unexpected errors. Audit app and host error paths. Existing legacy APIs deliberately return plain text (e.g. `/api/approve_user`); preserve that contract or explicitly resolve migration before promising JSON for every legacy error. Successful text endpoints stay text.
- Text rules: accommodate accurate mode-aware documentation and character/code-point/index diagnostics, including ASCII failures. UnicodeTextPolicy confirms letters/marks/numbers/punctuation and printable ASCII, with additional emoji behavior when enabled; noncharacters and normalization also matter. Define zero-based Unicode code-point positions against submitted field text, accounting for current trimming/NFC/line-ending normalization. Include all three requested errors and the unchanged accepted sample. Recommend optional `/api/text_rules` in this slice so new clients need not parse prose.
- Approval queue: accommodate username, full fingerprint, registration time and earliest recorded post, plus one manual action for the displayed pending keys of a username. Existing rows show latest activity and shortened profile identifiers. Reuse signed per-key approvals; show partial failures and retry remaining keys, and never include keys arriving after selection automatically. A shared name is a grouping label, not evidence of common key ownership. Rejected, unsaved post attempts cannot be displayed as recorded posts; do not start retaining rejected drafts implicitly.
- Optional session label: defer until an actual need for new identity metadata is established. No auto-approval, text-policy relaxation, server key custody, new dependencies or deployment proposed.

## Option A — Deliver posting first, approval convenience next (recommended)

**Estimate: 0.85**

Complete one releasable cycle for the downloadable client, first-use signed posting/reading, actionable API errors, rule discovery and documentation; keep approval UI as a separately approved follow-up covering request 4.

- Benefit: Claude can complete index/read/check/thread/reply and recover from text or busy failures without waiting for UI work.
- Cost: bulk approval remains pending. Main uncertainty is legacy error compatibility and retry behavior after an ambiguous write; the absent client is replaced with project-owned code.

## Option B — Deliver the whole mandatory request together

**Estimate: 1.30**

Combine Option A with richer pending-key views and manual grouped approval, including phone/desktop, themes, first-paint and partial-failure verification.

- Benefit: all mandatory requests reach acceptance together.
- Cost: exceeds the comfortable-cycle reference; approval/read-model and UI verification create a separate substantial workstream. Prefer the split in A; estimates are alternative whole-cycle judgments, not sums or hours.

## Recommendation and approval

Choose A, retaining request 4 as a visible follow-up rather than dropping it. Acceptance includes serving the script as text, minimal-tool first-use posting and reading, exact preservation of the requested Unicode sample under existing canonicalization, pinpointed rejection, JSON busy handling and retry recovery. The approval follow-up must establish available first-post evidence and any read-model schema obligations before implementation. No runtime or live-site verification has run in this assessment; code inspection establishes the findings only.

Dependency inventory: zero additions for the recommended project-owned approach; no external code copied. Scope v1/A starts at b3a5b1621e9df25bac6e023c26aca66537852dfe. Documentation scope includes this cycle's plans/debug log and study ledger; todo.txt stays untouched.

Study checkpoint: 2026-10-29T19:23:19Z, Step 1 handoff, Codex single exposed rater; R1/P2. All three frozen R1 hashes match the ledger. Pristine intake is missing: source inspection preceded rating, and no prospective intake is reconstructed. Recommended total 0.85; alternative B 1.30. No inherited task estimate, measured labor, accepted outcome or independent rating. Original full request remains the parent outcome; a split must retain links and must not count children as independent observations by default.

Next gate: Approved Step 1 selects the recommended direction and permits Step 2 requirements, not implementation.

*— Codex · 2026-10-29 UTC*

Approval update: 2026-10-29 UTC — user supplied Approved Step 1, selecting recommended Option A. Subsequent naming instruction is captured in Step 2 as scope v1.1/A; original requests and estimates above remain preserved.

*— Codex · 2026-10-29 UTC*
