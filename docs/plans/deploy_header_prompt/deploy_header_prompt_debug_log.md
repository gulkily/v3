
## 2026-11-10 08:25 UTC — Step 2

### Necessary/useful instructions
- `step2_feature_description.md` › Known user or environment: forced the iPhone recovery path (lost reply mid-deploy) into the plan. Retain.
- `implementation_standards.md` › inspect existing implementations: surfaced `version_check.js` as the closest existing deferred-check pattern. Retain.
- `RAILSPIKE.md` › Enhancement Requests: gave a clear place to record the feature-flag request made with the approval. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only scope fields: this is an application change.

### Uncertain assessments
- Step 2 forbids implementation details, yet the shared component inventory needs file paths and reuse decisions. The boundary between the two is judged case by case; suggest one example in the Step 2 file.

## 2026-11-10 08:45 UTC — Step 3

### Necessary/useful instructions
- `step3_development_plan_do.md` › replay stage for a known user: produced Stage 4's iPhone replay, including a dropped connection. Retain.
- `step3_development_plan_do.md` › Key Risks with `**High risk:**`: flagged header crowding, which sets Stage 2's screenshot gate. Retain.

### Redundant/unnecessary instructions
- `step3_development_plan_before.md` › "Confirm ledger bookkeeping is inside the approved scope": repeats the effort-estimation paragraph in each phase file. Suggest consolidating into one pointer.

### Inapplicable instructions
- Documentation-only stage rules: application change.

### Uncertain assessments
- Stage sizing "<=50 lines": Stage 2 spans a template, a script, CSS and a browser test; it may exceed 50 lines while staying under an hour.
