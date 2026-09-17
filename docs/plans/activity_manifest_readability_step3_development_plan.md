# Activity Manifest Readability — Step 3: Development Plan

## Stage 1
- Goal: Make shared manifest file entries easier to scan.
- Dependencies: Existing `source_commit_files` manifest contract.
- Expected changes: Extend the shared activity-manifest presentation so status/role precede a separate non-public-key path line, while signer and public-key lines retain their required formatting.
- Verification approach: Render coverage confirms a changed file path follows its status/role on a separate line and a public-key path remains inline.
- Risks or open questions:
  - Rename entries must preserve their old-to-new path context without reintroducing an inline primary path.
- Canonical components/API contracts touched: `templates/partials/activity_commit_manifest.php`; shared activity manifest presentation contract.

## Stage 2
- Goal: Remove duplicate activity source/signature file listings without reducing fallback information.
- Dependencies: Stage 1 shared manifest markup.
- Expected changes: In Classic and Forte activity consumers, show existing source metadata only when no commit manifest is available; leave post-page source metadata unchanged.
- Verification approach: Classic and Forte render tests confirm one listing per manifest file, matching presentation, and retained fallback metadata for unavailable manifests.
- Risks or open questions:
  - The commit hash must remain visible when a manifest replaces the former source metadata block.
- Canonical components/API contracts touched: `templates/pages/activity.php`; `templates/partials/paned_activity_detail_pane.php`; `templates/partials/source_metadata.php` consumer boundary.

## Approval Gate

Reply **Approved Step 3** to begin implementation on a feature branch.
