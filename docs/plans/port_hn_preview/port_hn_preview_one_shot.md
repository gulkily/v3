# Port HN theme and preview

Cycle: v3/port_hn_preview · One-shot · [Plans](../README.md)
Debug mode: enabled by repository marker
Effort study: railspike-effort, P2, R1, scope v1

**Cycle estimate: 0.20 projected**

Ilya requested the HN theme and more preview data, then approved one-shot implementation after reviewing the proposed fields. Follow-up to v3/thread_port and PF-20261104-F50.

Reuse the existing held hn-v1 file and preview template. Keep optional source counts in the held file, and display the actual number of replies to import separately. Show the chosen item's attribution, timestamp and text, with links to the original discussion and external article where applicable. Preserve older bookmarklets and held files. Use the existing canonical Theme header and ThreadTheme resolver to default new HN ports to ochre under site theme policy. Preserve existing verified overrides and do not infer directives from imported text.

Update the Ported thread concept. Verify the normal preview/publish flow, selection and story cases, canonical rebuild, duplicate and retry behavior, and the existing theme authorization rules with focused tests plus smoke. No production deployment in this cycle.

Study: 0.20 after initial source inspection, single exposed rater, extended estimates disabled. Pristine intake unavailable. R1 hashes not verified. Baseline fe4a456491a26264c4d6c3b82acfa44630a50d9b. Planning commit pending.

*— Codex · 2026-11-23 UTC*
