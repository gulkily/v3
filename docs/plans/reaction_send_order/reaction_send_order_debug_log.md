# Debug log: v3/reaction_send_order

## 2026-11-15T21:10Z, closeout (technically complete)

### Necessary/useful instructions

- `docs/railspike/docs/workflow/implementation_standards.md`, "replay each reported failure in the relevant user/environment": the real-browser replay found the same-second tie that unit tests with mocked fetches could not show. Retain, and consider naming "repeat fast replays" for ordering bugs.
- Maintainer rule on base failures: `signed_reactions.mjs` failed on the base; fixed in the same work.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons: no navigation change.

### Uncertain assessments

- UI copy audit checklist: two short new status strings; unclear whether such strings need checklist entries. Left out and recorded as a limitation.
