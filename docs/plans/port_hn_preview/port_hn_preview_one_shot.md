# Port HN theme and preview

Cycle: v3/port_hn_preview · One-shot · [Plans](../README.md)
Debug mode: enabled by repository marker
Effort study: railspike-effort, P2, R1, scope v1

**Cycle estimate: 0.20 projected**

Ilya requested the HN theme and more preview data, then approved one-shot implementation after reviewing the proposed fields. Follow-up to v3/thread_port and PF-20261104-F50.

Reuse the existing held hn-v1 file and preview template. Keep optional source counts in the held file, and display the actual number of replies to import separately. Show the chosen item's attribution, timestamp and text, with links to the original discussion and external article where applicable. Preserve older bookmarklets and held files. Use the existing canonical Theme header and ThreadTheme resolver to default new HN ports to ochre under site theme policy. Preserve existing verified overrides and do not infer directives from imported text.

Update the Ported thread concept. Verify the normal preview/publish flow, selection and story cases, canonical rebuild, duplicate and retry behavior, and the existing theme authorization rules with focused tests plus smoke. No production deployment in this cycle.

Study: 0.20 after initial source inspection, single exposed rater, extended estimates disabled. Pristine intake unavailable. R1 hashes not verified. Baseline fe4a456491a26264c4d6c3b82acfa44630a50d9b. Planning commit `2c2e46b85ef1861dc91c5fcaca35e406d983d775` on `port-hn-preview`.

*— Codex · 2026-11-23 UTC*

## Outcome

The bookmarklet on Tools → Bookmarklets now carries optional story points and HN comment count into the existing held-file preview. The preview displays these separately from the imported reply count and shows the root text and destination when present. For a selection, the original comment and its parent story have separate links. Older payloads still preview and publish. Copy the updated bookmarklet after deployment to capture the new counts.

New ports persist the default in the root's Theme header. The standard thread page resolver applies it subject to site policy, with approved theme overrides evaluated as before. No schema changes. The Ported thread concept records the new behavior.

## Verification

- The 21 focused Port and ThreadTheme tests passed. They cover preview, approved-member publication, guest access, failed-write retry, duplicate handling, rendered theme and persistence across rebuild.
- The smoke set plus focused tests yielded 495 passes and 12 failures. The same command on the starting revision produced the same failing tests. GPG agent startup and missing GD contribute to the baseline failures. The full suite was not run.
- The bookmarklet fixture extraction passed using a DOM harness for story, comment, selection and zero counts. Chromium's browser run was blocked at startup by the environment's socket restriction. Real iPhone Safari acceptance is pending deployment.
- Final diff reviewed for scope and whitespace. There were no additional requests or scope revisions. The implementation is local to the feature branch. Completion revision is recorded in Git.

## Study closeout

Execution entry reaffirmed the 0.20 projection. Retrospective functional scope assessment is 0.20. Environment setup and baseline comparison were additional verification work. Maintainer fit and acceptance are pending.

*— Codex · 2026-11-23 UTC*
