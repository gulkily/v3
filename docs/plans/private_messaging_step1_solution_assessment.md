# Private Messaging Step 1 Solution Assessment

> **Feature plan:** [Step 1](./private_messaging_step1_solution_assessment.md) · [Step 2](./private_messaging_step2_feature_description.md) · [Step 3](./private_messaging_step3_development_plan.md) · [Step 4](./private_messaging_step4_implementation_summary.md)

## Original Query

As a user, I want to be able to private-message another user. Please explore our codebase and help me come up with something. Ideas, not necessarily good ones:

- Store private messages in a separate repo or database from public data.
- Encrypt with a password and then encrypt that password to all the user's public keys (could get large).
- Anything else?
- Should almost definitely use PGP.

Also, our deliverable should be Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

We don't care that much about hardening; this forum is intended for low-value content.

## Understood Intent

Provide low-value direct messages that stay out of public artifacts and use the existing browser OpenPGP identities.

## Problem Statement

The forum has browser OpenPGP identities and private SQLite precedent, but no private content store or delivery model.

## Option A: Public Git records containing encrypted message envelopes

Store each encrypted, signed message as a public-repository record.

Pros:
- Reuses the durable Git write model.
- Needs no separate message store.

Cons:
- Ciphertext and metadata become permanent public-archive material.
- Deletion and retention are a poor fit for private correspondence.

## Option B: Encrypted mailbox in a private runtime SQLite database

Keep encrypted envelopes and delivery metadata in a database outside Git, `public/`, public read models, and offline/static artifacts. Encrypt and sign in the browser.

Pros:
- Matches existing private queue/LLM-store deployment patterns.
- Leaves message bodies out of public archives and supports a small end-to-end slice: send, inbox, local decrypt.

Cons:
- The operator still sees metadata and controls availability, retention, and backups.
- The mailbox needs an explicit backup/retention policy.

## Option C: A separate private Git repository of encrypted envelopes

Use a separate, non-public repository of encrypted message files.

Pros:
- Preserves Git history and portable encrypted backups.
- Separates messages from public content.

Cons:
- Adds repository, locking, deployment, and retention complexity.
- Retains metadata and ciphertext history unless actively pruned.

## Recommendation

Recommend Option B. Use OpenPGP envelope encryption, not a user-chosen password: generate a fresh per-message content key and encrypt it for every approved profile key in the recipient's and sender's username group, so each composite user can read its copy. Sign with the sending key.

This is the smallest viable vertical slice and reuses browser OpenPGP, approved username-group key lookup, challenge-signature authentication, and the private-SQLite boundary. Step 2 should define conversation scope, retention, sender copies, and the privacy statement: private from public readers, not from the operator or metadata observers.
