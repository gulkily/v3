# Invite Destination Selector — Step 1: Solution Assessment

## Problem statement

On the invites page, destination entry should appear only when opted in and offer a typeable choice of recently used internal locations.

## Option A — Browser-local recent destinations

- Use an editable dropdown populated from destinations successfully used by that browser, plus the current location supplied when the invite flow opens.
- Pros:
  - Small, isolated change to the existing invites page and its client-side script.
  - No account data, API, schema, or cross-device tracking.
  - Preserves free-form entry and the existing server-side internal-path validation.
- Cons:
  - Suggestions are limited to one browser and can disappear when browser storage is cleared.
  - Does not expose the browser's general history, which browsers intentionally do not permit sites to read.

## Option B — Account-backed recent destinations

- Store recent invite destinations for each approved member and return them to the page as suggestions.
- Pros:
  - Suggestions follow the member across browsers and devices.
  - Can support central retention and moderation policies.
- Cons:
  - Requires new persistence, API, access-control, and privacy decisions.
  - Materially expands a small form improvement and retains members' navigation choices server-side.

## Option C — Curated location shortcuts

- Provide a fixed list of common internal destinations alongside editable text entry.
- Pros:
  - Predictable and requires no user-location retention.
  - Useful for known high-value destinations.
- Cons:
  - Does not meet the requested “recent locations” behavior.
  - Needs ongoing curation and can become stale.

## Option D — Browser-local recents with curated shortcuts

- Use one editable dropdown containing browser-local recent destinations and a small fixed set of useful internal shortcuts.
- Pros:
  - Gives first-time users useful choices while recents are empty.
  - Becomes more personal over time without server-side tracking.
  - Retains free-form entry and existing server-side validation.
- Cons:
  - The shortcut set needs lightweight maintenance.
  - Recent destinations remain browser-local.

## Recommendation

Choose **Option D**, combining A and C. Make the destination field group hidden and disabled until its checkbox is checked, then use a native editable dropdown with both valid browser-local recents and curated internal shortcuts. This gives immediate guidance, improves repeat use, and avoids expanding the invitation model or collecting cross-device navigation data.
