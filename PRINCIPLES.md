# Principles

This project deliberately differs from common practice.
When a familiar industry default conflicts with a principle here, the principle wins.
If a change seems to need an exception, raise it before building.

## Summarized

### What the platform enables

- You can participate right away, without any barriers or special setup.
- The community transparently decides the rules and whom to let in.
- People and agents are accommodated in whatever they need to access the resource.
- Your secret keys stay on your devices, in your custody.
- Digital signatures are the authority, not the server or database.*
- Any individual or group, anytime, can take their content and history and move somewhere else.

### How we build it

- All content is kept in simple files that will be readable decades from now.
- Cut anything that isn't necessary, especially words and UI.
- Never lose what the user wrote. If it can't be sent, say why.
- The basics work on any browser or client, no matter how old or limited.
- Build with a stack of technologies that have been around for 20+ years.
- Write it ourselves; don't rely on other people's code.*
- A site should keep running for years with minimal or no maintenance.
- Don't break what already works: old posts, links, clients and APIs keep working.

## People

### Keep participation open to people without a key

Keyless participation (anonymous posts, hint-only votes) is a capability to
keep, not a path to phase out. Close abuse by recording how much trust an
action carries, not by turning people away.

- Rules out: "require a key or account" as the fix for impersonation.
- Source: maintainer, unsigned attribution review (2026-10-23).

### Identity, access and approval are separate

A saved key, a published identity, a signed action, a signed-in session and
membership approval are different states. Do not merge them for convenience.

- Rules out: treating "has a key" as "is a member", or a username as proof of identity.
- Source: [trust model](docs/architecture/public_architecture_and_trust.md#identity-and-access-are-separate).

### Membership grows through people who vouch for each other

Approved members approve others with signed actions, and the approval history
stays visible.

- Rules out: anonymous open sign-up as the default growth path.
- Source: [10 reasons](docs/product/10_reasons_to_consider_v3.md#3-membership-can-grow-through-people-who-know-one-another).

### Serve people and language models fairly

Agents are participants with their own documented entry points (`/llms.txt`,
plain-text APIs). They get the same honesty and clear errors as people.

- Rules out: hiding the API, or treating automated clients as abuse by default.
- Source: [BLESSING.md](BLESSING.md), [agent API](docs/reference/agent_api.md).

## Records and trust

### The record is plain text in Git; everything else is derived

Posts, keys, approvals and reactions are small text files in a Git repository.
The database and the generated pages can be deleted and rebuilt.

- Rules out: content that exists only in a database, and schema changes where existing fields will do.
- Source: [trust model](docs/architecture/public_architecture_and_trust.md#the-public-record-lives-in-git), [CONTRIBUTING](CONTRIBUTING.md#read-model-schema-changes).

### Private keys stay in the member's browser

The server holds public keys and signatures, never private keys.

- Rules out: server-side key custody, even to make recovery easier.
- Source: [trust model](docs/architecture/public_architecture_and_trust.md#your-browser-holds-your-private-key).

### Authorship is shown by signature, not by display name

A signature proves control of a key, not that a claim is true or who someone
is in real life. Say exactly that much and no more.

- Source: [10 reasons](docs/product/10_reasons_to_consider_v3.md#4-authorship-can-be-checked-beyond-a-displayed-username).

### A community can take its history with it

Backups include the full Git history, and a new steward can rebuild the site
from the record alone.

- Rules out: features whose data cannot be exported or rebuilt.
- Source: [10 reasons](docs/product/10_reasons_to_consider_v3.md#1-your-community-can-keep-its-history-when-its-circumstances-change).

## Interface

### Remove text before rewriting it

The interface should explain itself. Keep warnings, consequences and recovery
steps; drop copy that repeats what the controls already show.

- Source: [AGENTS.md](AGENTS.md#ui-copy), [CONTRIBUTING](CONTRIBUTING.md#ui-copy-and-verification).

### Never lose someone's work silently

Refuse with a clear reason and a next step instead of dropping or rewriting
input. Keep drafts when sending fails.

- Rules out: silently converting a refused post to a different kind of post.
- Source: maintainer (2026-10-23); the API's error-first contract and the compose form keeping drafts on failure.

### Basic reading and posting work without JavaScript

Old and limited browsers and clients can still read and post. This is also why
instances serve HTTP alongside HTTPS: clients too old for current TLS can still
connect.

The previous prototype, Pollyanna, works in Netscape and Mosaic and in the text
browsers Links, Lynx and w3m. v3 works toward that bar over the long term.

- Source: maintainer (2026-10-23); anonymous posting works as a plain form, and past verification checks pages with JavaScript on and off.

## Stack and longevity

### Choose the stack carefully, for the long run

Pick technology by how long it has lasted, not how new or popular it is (the
Lindy effect). Prefer components with 20 or more years of backward
compatibility, such as PHP, SQLite, Git, Apache, plain HTML and OpenPGP.

- Rules out: adopting a newer tool for convenience when a long-lived one does the job.
- Source: maintainer (2026-10-23).

### Keep our own backward compatibility

Old records, links, APIs and clients keep working. Change a format by adding to
it; retire an interface only with a documented error that tells the caller what
to use instead.

- Rules out: breaking changes for tidiness, and data migrations where an additive change would do.
- Source: maintainer (2026-10-23).

### Avoid third-party components and frameworks

Use the language, the platform and our own code first. Every new library, asset,
framework or vendored tree needs explicit approval, with a dependency-free
alternative considered.

- Rules out: frameworks, package-manager trees and CDN-hosted runtime code as defaults.
- Source: maintainer (2026-10-23); [CONTRIBUTING](CONTRIBUTING.md#third-party-dependencies-and-copied-code). The Markdown library was removed and scrubbed from history on these grounds.

### Design for longevity and service-free maintenance

An instance should keep running for years without ongoing upkeep, and without
depending on outside services, accounts or subscriptions. Optional features
that need a service (such as LLM providers) must be safe to switch off.

- Rules out: features that stop working when a vendor, API or hosted service goes away.
- Source: maintainer (2026-10-23).

## Method

### Plan before building, with explicit approvals

Non-trivial work goes through [Railspike](docs/railspike/RAILSPIKE.md): compare
options, describe, plan, then implement in committed stages.

- Source: [AGENTS.md](AGENTS.md#railspike).

### State limits plainly

Documentation and reports say what was not checked or does not work yet. A
focused test pass is not a full-suite pass.

- Source: maintainer (2026-10-23); the product documents and Railspike's reporting rules.
