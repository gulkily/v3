# Forte Unauthenticated Vote Identity Setup — Step 1: Solution Assessment

## Problem

For a Forte visitor without a prepared browser identity, a vote can fail with a generic reload instruction instead of loading identity setup or explaining why that load failed.

## Cause

- Votes require the browser identity helper, which is intentionally loaded on demand on Forte.
- The current reaction flow tries that lazy load, but suppresses its failure and then emits `Identity setup is unavailable`; therefore the displayed message means the helper was still absent, not that reloading is necessarily useful.
- Since the current source already includes the first-vote lazy-load bridge, a live occurrence also indicates that Forte may be serving an older/mismatched reaction or loader asset, or that one of the requested signing assets failed to load. Confirm this with a fresh-browser network/console capture before changing product behavior.

## Option A: Make the existing lazy path observable and preserve its failure

- Pros: retains fast Forte page loads; reuses the established shared signing flow; distinguishes a missing loader, asset failure, and identity/OpenPGP failure; add a focused first-vote regression test and release asset checks.
- Cons: first vote still waits for identity assets and setup.

## Option B: Load signing assets with Forte page startup

- Pros: removes the first-vote loader dependency and makes an asset failure visible before interaction.
- Cons: increases every reader's initial download/work; regresses the intentional lazy-loading performance design; still needs useful failure reporting.

## Option C: Require explicit identity preparation before enabling votes

- Pros: makes the prerequisite visible and separates setup from voting.
- Cons: adds friction and a new control/state to Forte; does not resolve a broken asset path by itself.

## Recommendation

**Option A.** Keep the current on-demand model, but surface the real lazy-load failure and verify that the deployed Forte HTML, reaction script, and signing assets use a consistent release. This directly fixes the misleading message without charging every reader for identity code; Option C can remain a later UX enhancement if first-vote setup proves confusing.
