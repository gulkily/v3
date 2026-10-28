# Progressive disclosure

Status: Shared UI guidance
Scope: Optional technical details across public, account, and Tools views

Keep the information needed for the current task visible. Put optional technical
details in clearly labeled, collapsed expandable sections so people can inspect
them without overwhelming the initial view. Remove unnecessary text before
deciding to hide it; disclosures are not a place to retain redundant explanations.

## What belongs inside

- Raw public keys, full fingerprints, and internal identity IDs.
- Detailed signature verification evidence and provenance metadata.
- Raw canonical records, hashes, and commit details.
- Detailed approval history and supporting evidence.
- Diagnostic output and internal storage information.

Apply this according to the task. A fingerprint needed to choose or verify a key,
or diagnostic output on a page opened specifically to inspect it, can be primary
information. Existing useful Source, Public key, and history links can remain
visible; a link to evidence and a disclosure of its raw contents serve different
purposes. This guidance does not require moving every technical value or link.

## What stays visible

Keep names, activity, useful counts, primary actions, and concise status summaries
outside technical disclosures. Keep errors, essential warnings, material access,
privacy or data-loss consequences, and actionable recovery visible where needed
for the current decision. Detailed failure evidence may be expandable, but its
summary and recovery action must remain accessible without opening it.

Private keys retain their deliberate reveal behavior and existing warnings.
Keep the warning with the revealed material and expose any consequence needed
before the reveal decision. A collapsed section is not access control and must
never substitute for authorization or protections for secret material.

## Disclosure controls and content

Prefer native `details` and `summary`, closed initially for optional technical
content. Use a short label that identifies the contents, such as “Public key”;
use “Advanced / technical details” for an established mixed technical section.
Controls must work by keyboard and touch, expose their expanded state to assistive
technology, and have a visible focus indicator. Do not make access depend on hover.
Avoid unnecessary nested disclosures.

Render public keys as complete, escaped ASCII-armored text in a selectable
preformatted block, including the BEGIN and END markers and original line breaks.
A fingerprint or key-record link does not replace the key material when the view
is intended to display the key. Use the existing themed key-viewer styling where
appropriate. Contain overflow within the viewer; visual wrapping must not change
the underlying text. Provide a Copy control where copying is a useful action,
copying only the full key text rather than labels or surrounding markup. Manual
selection must remain possible if clipboard access fails. Show an honest empty
state when key material is unavailable.

For human-facing source and exports, preserve the project's readable record
formats and signed bytes; disclosure does not justify converting them to JSON.
Use concise labels and omit repeated explanatory prose.

## Existing examples

These examples establish the pattern, not a claim that every current view already
meets every recommendation:

- [Account keys](../../templates/pages/account_key.php): collapsed “Keys” section,
  labeled public-key viewer and Copy control; separate private-key reveal.
- [Forte profiles](../../templates/pages/forte_profile.php): collapsed technical
  details containing profile identifiers and the full public key.
- [Tools profile details](../../templates/partials/profile_access.php): collapsed
  technical details with a labeled preformatted public key.
- [Bootstrap post details](../../templates/partials/post_identity_details.php):
  collapsed technical details with public-key text or an unavailable state.

The [profile read contract](../specs/profile_read_contract_v1.md) requires public
key material and enough detail for identity inspection; it does not require that
all those details be expanded initially. The [key storage specification](../specs/public_key_storage_v1.md)
defines the underlying ASCII-armored key format.

## Verification

Follow [UI copy and verification](../../CONTRIBUTING.md#ui-copy-and-verification).
Check collapsed and expanded states on phone and desktop in affected themes,
including long keys, empty data, keyboard operation, focus, copy success/failure,
and overflow. Confirm primary actions, important status, warnings, and recovery
remain discoverable. Record actual UI verification when implementing a change;
this documentation alone does not establish compliance of existing screens.
