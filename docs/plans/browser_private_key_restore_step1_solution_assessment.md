# Browser Private Key Restore Step 1 Solution Assessment

## Problem Statement

Users need a frontend path to restore a browser-held posting identity from private key material copied from another browser without sending private keys to the server.

## Option A: Add local-only private key import on `/account/key/`

Pros:
- Directly supports the reported workflow: paste a copied armored private key into the account page.
- Preserves the existing browser-held identity model because private key material stays client-side.
- Can derive the matching public key and identity fingerprint before linking the identity.
- Fits the current account-key surface and avoids database changes.

Cons:
- Requires careful validation and failure messages so users do not store malformed or mismatched key material.
- Clipboard/paste handling makes the security posture more visible and must be explained tersely.
- Restored metadata such as username may need confirmation when the key alone does not carry the desired local label.

## Option B: Add an explicit export/import identity backup bundle

Pros:
- Gives users a cleaner cross-browser migration flow than copying raw private key text.
- Can include local metadata such as username, public key, fingerprint, and format version.
- Creates a more durable base for future backup warnings, file import, and migration UX.

Cons:
- Does not fully solve users who already copied only the private key unless raw-key import is also supported.
- Larger product surface than the immediate restore need.
- Introduces backup format design and compatibility decisions before the basic recovery path exists.

## Option C: Add server-assisted identity recovery

Pros:
- Most convenient long-term cross-device experience if users lose browser-local storage.
- Could avoid exposing raw private key text in the UI.

Cons:
- Conflicts with the current browser-held private key model.
- Would require storing or escrow-managing highly sensitive key material.
- Adds significant security, trust, and operational risk for a feature that can be handled locally.

## Recommendation

Recommend Option A.

Brief justification:
- The user need is specifically restoring from copied private key material, so raw private-key import is the smallest complete solution.
- Local-only import keeps private keys out of server storage and preserves the existing signing architecture.
- A later backup bundle can build on the same validation path, but it should not block the first restore feature.
