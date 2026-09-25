# Activity Commit File Manifest — Step 3: Development Plan

## Stage 1
- Goal: Define one cached, Git-authoritative changed-file manifest for a validated source commit.
- Dependencies: Existing source-commit validation and repository Git checkout.
- Expected changes: Add a shared manifest contract covering every changed path and its add/modify/delete/rename status; have the existing commit-detail route and activity data use that contract.
- Verification approach: Git-backed tests cover multi-file commits and every supported change status, plus unavailable/no-Git behavior.
- Risks or open questions:
  - Renamed and deleted paths may not have a readable blob at the commit and must remain listed without a broken link.
  - A render-local cache must avoid repeated repository commands for a shared commit.
- Canonical components/API contracts touched: `Application` source-commit detail handling; `Application::fetchActivity()` activity item model; `/source/commits/{sha}`.

## Stage 2
- Goal: Make each manifest entry understandable and safely navigable.
- Dependencies: Stage 1 manifest contract; existing canonical source-path policy.
- Expected changes: Classify observable canonical roles (record, detached signature, public key, and related record families) and attach only source links that satisfy existing access and historic-availability rules.
- Verification approach: Tests assert path, status, role, and safe-link behavior for canonical records, signatures, public keys, and unavailable/deleted paths.
- Risks or open questions:
  - Unknown or non-canonical paths must be listed without becoming browseable source links.
- Canonical components/API contracts touched: Canonical source-path validation; source-blob/current-source routes; shared activity manifest entry contract.

## Stage 3
- Goal: Associate every manifest signature with its actual signing identity and canonical public key.
- Dependencies: Stage 1 manifest entries; existing detached-signature and public-key identity data.
- Expected changes: Add a shared signature-key metadata contract that resolves the signer, exposes its canonical public-key path/link independently of the commit, and reports a clear unavailable state when resolution fails.
- Verification approach: Git-backed coverage proves a signature can link to a key committed earlier; missing or unresolvable keys render an explicit non-link state.
- Risks or open questions:
  - The signer must be derived from trusted signature/identity data, never inferred solely from a filename.
- Canonical components/API contracts touched: Detached-signature inspection/verification support; canonical public-key path resolution; activity manifest entry contract.

## Stage 4
- Goal: Render identical complete commit context in Classic Activity and Forte Activity.
- Dependencies: Stages 1–3 activity manifest data.
- Expected changes: Add one activity-only commit-manifest partial and render it from Classic cards and Forte's detail pane; retain the existing `source_metadata` partial unchanged for post pages.
- Verification approach: Render tests assert the same manifest paths, statuses, roles, signature signer, and public-key link appear in both views for one event.
- Risks or open questions:
  - Large manifests need a compact readable presentation without omitting any entry.
- Canonical components/API contracts touched: `templates/pages/activity.php`; `templates/partials/paned_activity_detail_pane.php`; new shared activity manifest partial.

## Stage 5
- Goal: Lock in behavior across all activity kinds and failure states.
- Dependencies: Stages 1–4.
- Expected changes: Add representative fixtures/assertions for post, reply, identity, bootstrap, approval, label, reaction, feature-flag, and handoff activity where source commits exist; update source-commit route expectations.
- Verification approach: Run targeted Git-backed activity/source tests, PHP syntax checks for changed files, and `php tests/run.php`; manually inspect a multi-file event in each Activity view.
- Risks or open questions:
  - Fixture coverage must not assume every activity kind always creates a signature or public key.
- Canonical components/API contracts touched: Activity/source route smoke tests; Forte Activity smoke tests; canonical repository fixtures.

## Approval Gate

Reply **Approved Step 3** to begin implementation on a feature branch.
