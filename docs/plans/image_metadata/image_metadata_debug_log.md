# image_metadata debug log

## 2026-11-15T18:45Z — Step 1

### Necessary/useful instructions
- `step1_solution_assessment.md`, Understood Intent: needed, because "attaching the metadata" could mean the file or the post.
- `AGENTS.md`, Human-readable formats: decided the recommendation between binary EXIF and `Key: value` lines.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Step 1 `/new` reminder: the conversation had no discussion before this Step 1.

### Uncertain assessments
- Effort-study ledger write timing: the ledger lives in `docs/plans/`, but Step 1 must stay uncommitted, so the index row is recorded as pending.

## 2026-11-15T18:50Z — Step 2

### Necessary/useful instructions
- `step2_feature_description.md`, Known user or environment: surfaced the iPhone Safari case, where the photo picker may strip location before the page sees it.
- `implementation_standards.md`, inspecting existing surfaces: found that `image_prepare.js` already walks JPEG markers, so the EXIF reader extends it.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only scope fields: this is an application change.

### Uncertain assessments
- No supported findings.

## 2026-11-15T18:55Z — Step 3

### Necessary/useful instructions
- `step3_development_plan_do.md`, known-user replay requirement: kept the iPhone Safari check as its own pending item instead of counting Chromium as proof.
- `implementation_standards.md`, reuse: the reply retry path stores attachments separately, which made choice storage its own risk.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Database change notes: no schema or read-model change.

### Uncertain assessments
- Mockup request between Steps 2 and 3: recorded as an enhancement request with no scope change; the guidance does not say where design artifacts belong.

## 2026-11-15T19:30Z — Step 4

### Necessary/useful instructions
- `implementation_standards.md`, shared consumers: led to fixing thread titles and board excerpts, which would otherwise show raw `Photo-` lines.
- `step4_implementation_do.md`, stage boundary protocol: per-stage commits made the two mid-stage fixes easy to place.
- Step 2 known-environment rule: kept the iPhone Safari replay marked pending instead of counting Chromium as acceptance.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only verification: application change.
- Nav icon mapping (`AGENTS.md`): no navigation destination added.

### Uncertain assessments
- Step 3 said to store detail keys for retries. Stage 2 stored final lines instead, because the stored attachment carries no metadata. It is recorded as a deviation; the instructions do not say whether this needed renewed approval.
