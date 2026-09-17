# Approved Members Identity Publication Step 1 Solution Assessment

## Problem Statement

In a private approved-members-only instance, an unapproved user must automatically publish a new browser key’s public key, create or reuse the corresponding profile, inherit any existing approval, and authenticate without manual recovery steps.

## Option A: Keep the existing signed identity-publication flow and make it private-mode aware

Pros:

- Reuses the existing key-generation, prepare, sign, and finalize workflow.
- Preserves the private-site boundary by allowing only identity-lifecycle actions before approval.
- Keeps profile creation and approval as separate canonical records with clear auditability.
- Allows existing profiles to be recognized without exposing general profile APIs to lobby users.

Cons:

- Requires careful coordination between browser publication state and lobby authentication.
- Needs explicit recovery and status handling for prepared-but-unfinalized identities.
- Must avoid relying on protected content lookups before authentication.

## Option B: Create the profile directly when the browser generates the keypair

Pros:

- Provides a shorter apparent setup path.
- Avoids a separate prepared-publication state in the user experience.

Cons:

- Couples key generation to a canonical write before the user confirms publication.
- Makes retries, interrupted requests, and duplicate identities harder to reason about.
- Weakens the existing signed-bootstrap boundary unless the generated key signs the complete identity record.

## Option C: Authenticate directly from the browser public key and derive the profile on demand

Pros:

- Could avoid a separate profile-publication step.
- Makes the browser key the immediate source of identity.

Cons:

- Cannot apply canonical approval records until a profile exists.
- Risks creating a second identity model parallel to the repository and read model.
- Complicates auditability, profile history, and future approval features.

## Recommendation

Recommend Option A: finish and harden the existing signed identity-publication flow for lobby users, including automatic retry/recovery and a private-safe existing-profile check.

This preserves the repository’s canonical identity and approval model, keeps inaccessible content protected, and satisfies the desired one-shot user experience: generate a keypair, automatically publish the public key, then enter the lobby or full site according to approval state.
