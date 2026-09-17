# Activity Commit File Manifest — Step 1: Solution Assessment

## Problem

Classic Activity and Forte Activity show only the activity record's source file, even when its commit atomically changes additional canonical files, and do not consistently expose the public key for an included signature's signing identity.

## Option A — Improve the existing commit-detail endpoint only

- Pros: Reuses the existing commit link and complete filename list; smallest change.
- Cons: Requires leaving either feed; plain-text output does not enumerate linked files or explain each file's role in the change.

## Option B — Shared commit manifest in both activity detail views

- Pros: Each activity item shows every changed file for its commit, with a path, change status, safe source link where available, and concise canonical role; one shared manifest keeps Classic and Forte consistent.
- Pros: The triggering activity file remains identifiable within the complete commit context.
- Pros: Every included signature is accompanied by its signing identity's public-key link, whether or not that key is part of the commit.
- Cons: Requires careful handling of deleted/renamed files and caching when several activity items share a commit.

## Option C — Persist commit manifests in the read model

- Pros: Avoids repository inspection while rendering feeds; can preserve a historical manifest snapshot.
- Cons: Adds redundant, per-activity stored data and schema/rebuild complexity; duplicates information Git already authoritatively supplies.

## Recommendation

Choose **Option B**. Derive one reusable, Git-authoritative commit manifest per unique commit during a page render and use the same component in Classic Activity cards and Forte's detail pane. Augment any listed signature with its signer's canonical public-key link, independently of the manifest. It directly meets the request without a database migration; cache the manifest for the render and label paths by their observable canonical role rather than inferring intent.

## Approval Gate

Reply **Approved Step 1** to proceed to the feature description.
