# Standard Activity page size fix

The local `/activity/` response measured 88.5 MB for 100 entries. It repeats a
2,323-file commit manifest for 93 entries, producing about 216,000 file rows.
Pagination limits entries but does not limit the evidence embedded in each one.

## Proposed fix

1. **Show relevant files only.** Use the existing per-item relevant-file rules
   for standard Activity: the source record and associated identity/signature/key
   evidence where applicable. Keep the commit link for the complete file list.
2. **Avoid unnecessary enrichment.** Give standard Activity a lightweight path
   that resolves its relevant evidence without building metadata for every
   unrelated file in the commit. Changing the template alone reduces HTML but
   can leave unnecessary server work.
3. **Preserve behavior.** Keep pagination, categories, ordering, attribution,
   signature status, historical source links and security-review evidence.
   Forte, RSS and the full commit page retain their existing contracts.
4. **Test a realistic bulk commit.** Add a Git-backed fixture with many Activity
   entries sharing a commit containing thousands of unrelated files. Assert
   relevant evidence remains available and rendered file rows do not multiply
   by the full commit size. Cover unsigned/missing-history records too.
5. **Measure and verify.** Compare response bytes, file-row count and server
   timing before/after using the reported local page read-only. Verify source
   and commit links, pagination and phone/desktop layouts; refresh published
   static Activity HTML when deploying.

## Implementation and verification

The user approved implementation. Standard Activity now renders relevant files
only and enriches metadata only for those paths. Raw commit paths are indexed
once per commit per request; unrelated files do not trigger signature/key enrichment.
Existing full-manifest methods, Forte, RSS and the full commit page are unchanged.
Unsigned and missing-history evidence, identity pairs and signature/key links are
preserved. No core read-model query or schema changed.

Measured on the reported local page: **88,467,387 → 257,154 bytes** (99.71% smaller),
**216,057 → 258 evidence rows**, with identical Activity item links and Next
position. One before/after request took approximately **1.80 → 0.23 seconds**;
these are local samples, not a general latency guarantee. Original canonical
content was accessed read-only.

Verification: 30 focused PHP tests plus the core-route regression passed. A Git-backed fixture combines 205
Activity posts and 2,300 unrelated files in one import commit; 100 rendered entries
have 100 evidence rows, remain below 500 KB, do not warm the full-manifest cache,
and preserve access to the complete commit. Existing signed-reaction checks pass.
The real browser harness now uses this bulk-commit fixture and verifies navigation
with/without JavaScript, all 13 themes at 390/1280, source/full-commit links and
unchanged Forte Activity/Commits append behavior. Actual local Activity also
passes source access, Next/Back and overflow checks at both widths.

Deployment note: republish existing static Activity HTML after updating the app.
Production rollout, physical devices and a full-suite pass are not claimed.

