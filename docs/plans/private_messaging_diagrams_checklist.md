# Private Messaging Mermaid Diagrams: Checklist

Target doc: `docs/architecture/private_messaging.md`. No doc in `docs/` uses mermaid yet,
so the first diagram also sets the convention.

## Before starting

- [ ] Confirm every place these docs are rendered supports ```` ```mermaid ```` (GitHub does; check any in-app or static doc rendering).
- [ ] Decide placement: each diagram goes directly under its section heading, prose stays the source of truth.
- [ ] Decide detail policy: keep diagrams minimal; do not repeat numbers like the 25-item page size or the 1 MiB envelope limit.

## Layout rule: roughly square, must fit one page

An earlier set of mermaid diagrams in this project came out tall, narrow and too big for
a page. Aim for a roughly 4:3 to 1:1 aspect ratio, and no more than about one screen tall.

- [ ] Flowcharts: use `flowchart LR` (or `direction LR` inside subgraphs), not the default top-down. Use subgraphs to make 2-3 columns instead of one long chain.
- [ ] State diagrams: start with `direction LR`; if still tall, group states in composite states.
- [ ] Sequence diagrams grow with every message, so cap them at about 4 actors and 8-10 messages. If a flow needs more, split it into two diagrams or redraw as `flowchart LR` with one subgraph per actor.
- [ ] Prefer short node labels (about 3-5 words); put detail in the prose, not the node. Long labels force tall or wide boxes.
- [ ] No more than about 3 nodes in any unbroken vertical chain.
- [ ] Render each diagram (GitHub preview or `mmdc` from mermaid-cli) and look at its shape before accepting it. Do not judge layout from the source text.
- [ ] If a diagram still renders tall or narrow, split it rather than shrinking text.

## Joining split diagrams

Mermaid cannot link separate diagrams, so use one of these:

1. **One flowchart, two subgraphs, one connector (preferred).** Draw each half as a
   subgraph and join them with a single edge between the subgraphs themselves
   (`Send -- "label" --> Read`). Connect the subgraph ids, not nodes inside them:
   an edge from an inner node to an outside node makes Mermaid ignore the subgraph's
   `direction`. Sequence diagrams cannot be nested this way.
2. **Two diagrams with a matching boundary.** End the first and begin the second on the
   same label (for example "envelope stored in private SQLite") and add a one-line caption.
   The join is visual only. Use this when a sequence diagram is truly needed.
3. **Composite states (state diagrams only).** Wrap each half in a composite state and
   draw one transition between them.

## High value

### 1. Sending and reading a message (sequence diagram)

Section: "Sending and reading a message" (lines 32-43).

- [x] Actors: sender browser, server (mailbox service), private SQLite, recipient browser.
- [x] Show: recipient key lookup, local encrypt and sign, `POST /api/private_messages`, store envelope, acknowledgment.
- [x] Show: recipient fetches envelopes, decrypts, verifies signature, then renders text.
- [x] Make explicit that the server never sees plaintext and does not cryptographically validate contents.
- [x] Verify against steps 1-6 and the paragraph after them.
- [x] Layout: this is the most likely to come out tall. Consider two diagrams (send, then read) or a `flowchart LR` with one subgraph per actor.
- Done: one flowchart, three stacked subgraphs (send, server, read) joined by two connectors; renders 783x631 (about 1.24:1). Sequence form was dropped in favor of option 1 from "Joining split diagrams".

### 2. Unread acknowledgment and receipts (sequence diagram)

Section: "Unread conversations" (lines 75-85).

- [x] Actors: reader browser, server, private metadata (tracking and seen tables).
- [x] Show: fresh conversation window returns an HMAC receipt bound to viewer, counterpart and boundary.
- [x] Show: ack conditions met (reads settled, visible and focused, latest message visible), then `POST /api/private_messages/read` with the receipt.
- [x] Show: progress only moves forward; retry reuses the same receipt.
- [x] Note that list previews, loading older pages and sending do not move the boundary.
- [x] Verify against the receipt and race rules in the prose.
- [x] Layout: keep to the receipt handshake only; put the "do not move the boundary" cases in prose or a caption so the diagram stays short.
- Done: three stacked steps (open, acknowledge, record); renders 609x658 (about 0.93:1). Non-advancing cases kept in the lead sentence, not the diagram.

### 3. Draft and uncertain delivery (state diagram)

Section: "Drafts and uncertain delivery" (lines 59-65).

- [x] States: draft saved, send in flight, uncertain (network result unknown), acknowledged, conflict (rejected reuse).
- [x] Transitions: retry with same message ID and exact envelope; exact retry returns original ack; mismatched reuse is rejected.
- [x] Guard: draft cleared on success only if not edited in the meantime.
- [x] Show: unresolved earlier send must be checked before an edited draft is sent.
- [x] Verify against the idempotency rules (sender identity, username, recipient, envelope must match).
- Done: `stateDiagram-v2` with `direction TB`; renders 719x534 (about 1.35:1). `direction LR` came out 784x166 (too wide), so TB was used.

## Medium value (nice to have)

### 4. Storage and privacy boundaries (flowchart)

Section: "Privacy and storage boundaries" (lines 95-108).

- [x] Nodes: browser (memory, localStorage, identity storage), private SQLite, public Git repo, public read model, static and offline outputs.
- [x] Edges: what flows to the server (ciphertext, metadata) and what never crosses (plaintext, private keys).
- [x] Show private data excluded from public repo, read model, static releases, offline snapshots.
- [x] Verify against the existing table; keep the table.
- Done: three stacked groups (browser, private server state, public outputs); renders 784x623 (about 1.26:1). Invisible `~~~` links inside rows keep nodes horizontal; without them it rendered 695x966.

### 5. Identity and key coverage (flowchart)

Section: "Identity and key coverage" (lines 22-30).

- [x] Show: several approved profiles under one username feed a deduplicated recipient key set.
- [x] Show: pending profiles and profiles without public keys excluded.
- [x] Show: sender key included, one envelope covers incoming and sent views.
- [x] Optionally annotate: newly approved key cannot read old ciphertext.
- Done: two username groups feeding one message chain; renders 784x487 (about 1.6:1, slightly wider than target but fits one page). The optional "new key cannot read old ciphertext" annotation was left in prose to keep the diagram small.

## Explicitly skipped

- Endpoint table: already clearest as a table.
- Architectural choices and current limits: lists of decisions, not flows.
- Pagination cursors: complex, low payoff.

## Per-diagram acceptance

- [x] Renders correctly in GitHub's preview. (Confirmed by the user on the branch preview before merge.)
- [ ] Legible in both light and dark themes (avoid hard-coded colors).
- [ ] Every element in the diagram is traceable to a sentence in the doc, and nothing contradicts the prose.
- [ ] No more than about 10-12 nodes or participants; split otherwise.
- [x] Rendered shape is roughly square (see the layout rule) and fits on one page without scrolling or zooming.
- [ ] A one-line caption or lead sentence says what the diagram shows.
- [ ] Line-number references in this checklist updated if the doc has shifted.

## After finishing

- [ ] Re-read the whole doc once to confirm diagrams and prose agree.
- [ ] Tick off and archive this checklist per the `docs/plans/archive/` convention.
