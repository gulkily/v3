# The Nostr relay debate and how v3 answers it

Reviewed 2026-10-29 against this checkout. Source: the Hacker News discussion
["Nature's many attempts to evolve a Nostr"](https://news.ycombinator.com/item?id=46225803)
(170 comments) of Gordon Brander's
[essay of the same name](https://newsletter.squishy.computer/p/natures-many-attempts-to-evolve-a).
This is a product and architecture comparison, not a claim that v3 competes with
Nostr. Links point at the documents and plans that back each claim; where no
document exists, the gap is named in the last section.

## The essay in one paragraph

Brander argues that decentralized social protocols keep converging on the same
shape. Centralized services own the keys, so they can lock anyone out ("not your
keys, not your data"). Federation (email, Mastodon, Matrix) is easy to build but
network effects concentrate users on a few large servers, producing an oligopoly.
Peer-to-peer gives people their own keys but peers are unreliable, so networks
grow "superpeers" and recentralize. Nostr skips to the end state: users sign
their own messages with their own keys and post them to several cheap, dumb,
untrusted relays that do not talk to each other; anyone can verify a message,
and anyone can switch relays.

## What the thread argued about

The comments split into recurring challenges. The names are the commenters who
raised each one.

1. **"Relays" don't relay** (ralferoo, fc417fc802, treyd, supermatt, nunobrito).
   Relays never forward to each other, so either clients fan out every message
   or everyone converges on a few big relays. Defenders cited the outbox model
   (NIP-65 relay lists), client rebroadcast and negentropy sync; critics called
   the result a statically peered superpeer network, fragmented like federation.
2. **Censorship is still possible, and unprovable** (wmf, eykanal, supermatt,
   pjc50). A relay can drop or slow a key and nobody can prove it; a government
   can ban relays or lean on phone vendors. "You cannot censor Nostr" was called
   a religious claim. pjc50 asked how a network with no delete handles illegal
   content.
3. **Who pays for relays?** (AceJohnny2, eykanal, wmf). Dumb pipes have no
   quality differentiation and real bandwidth costs, so prices race to zero or
   the few that can afford it become the oligopoly. Zaps and paid relays were
   offered; Lightning's practicality was disputed (therein).
4. **People cannot and will not manage keys** (rglullis, krautburglar, sneak's
   law, acdha, verdverm). Losing a key loses identity and history with no one to
   call. The house-key analogy fails because houses sit inside a legal system
   that restores access. Signal and WhatsApp "work" because they manage keys
   for the user and fall back to phone numbers, which is not self-custody.
   nunobrito added that a Nostr key is an identity forever: no rotation, unlike
   PGP.
5. **A signature proves a key, not a person** (camgunz). "Verifiable as yours"
   means signed by the same key; you can still call yourself anything.
6. **Spam, DoS and moderation** (pyrolistical, acdha, vintermann,
   attila-lendvai, bflesch, AuthAuth, pests, digitalbase). Every platform needs
   discovery, spam control and moderation; once relays do those they are no
   longer dumb. Proof of work is weak against botnets; paid relays exclude
   ordinary users; web of trust works for digitalbase's app. Spam and
   moderation are the same operation (hiding what the reader did not ask for)
   except where spam is a denial of service. bflesch argued nobody with
   reputation wants their words next to "sewage", and that people join
   communities for exclusivity (lobste.rs). pests described atproto's
   subscribable labelers; curtisblaine argued multi-relay reading mainly makes
   activist moderators less decisive.
7. **It's Usenet, NNTP, PGP email, REST** (bawolff, camgunz, gaigalas, wmf,
   nunobrito). Signed messages through dumb stores existed in 2001 and 1995.
   The novelty claimed for Nostr is only that keys and signed plain text became
   trivial to use, which is where PGP failed (key distribution).
8. **Blockchain versus "just exit"** (lazzlazzlazz, stonogo, beeflet). One camp
   said federated designs always collapse to a few monetizing providers and only
   Byzantine fault-tolerant consensus avoids it; the other that cryptographic
   security does not require a global immutable ledger.
9. **Relays lose data; the network heals** (digitalbase). A major relay deleted
   its whole dataset and users barely noticed because clients and other relays
   rebroadcast. Encrypted DMs still leaked metadata to relays (shakna, citing
   [eprint 2025/1459](https://eprint.iacr.org/2025/1459)).
10. **Adoption** (wmf, poulpy123, verdverm). Nostr has under 1% of Bluesky's
    daily users and a crypto-heavy culture; fans selling it badly was named as
    its "fatal flaw" by a fan.

## Where v3 sits in Brander's taxonomy

v3 is none of his four shapes, and the difference is the point. A v3 instance is
a single self-hosted website run by one community. It does not try to be a
global network, and it has no relays. What it takes from the Nostr side of the
argument is the keys and the signatures: members hold OpenPGP keys in their
browsers, every public write is signed, and the server verifies rather than
vouches. What it takes from the Usenet and PGP side is the format: every
canonical fact is a small plain-text file in Git, and the database and pages are
derived. See [PRINCIPLES.md](../../PRINCIPLES.md) and the
[trust model](../architecture/public_architecture_and_trust.md).

The "relay" in v3 is the Git repository. Anyone allowed to read the site can
download the whole record with its history and rebuild a working instance
elsewhere. That turns censorship resistance from a network property into an
exit property: the operator can moderate freely, and the community can leave
with everything if the operator goes bad. The research paper in this repository,
[Byzantine Fault-Tolerant Governance for Internet Message Boards](../research/sigtbd_fault_tolerant_governance_for_internet_message_boards.md),
makes the case that forking is the recovery procedure for governance failure,
not a breakdown of it. This is the same lazzlazzlazz-versus-stonogo argument
with a third answer: no consensus protocol, no relay set, cheap exit.

## How v3 addresses each challenge

### 1. Relays, discovery and scalability

v3 opts out of network-wide discovery. One instance serves one community, and
the read path is static HTML plus SQLite, which Apache can serve without PHP
under [high-traffic mode](../plans/high_traffic_mode/high_traffic_mode_step2_feature_description.md).
There is no fan-out problem because there is no fan-out.

Cross-instance movement is import, not relay. An operator can pull another
instance's public record by name or URL; records arrive with their signatures,
unsigned or badly signed records are reported rather than accepted, local
records win on conflict, and remote deletions never delete local content
([instance content sync](../plans/instance_content_sync/instance_content_sync_step1_solution_assessment.md),
[concept](../concepts/instance_content_import.md)). An incremental change-feed
sync is assessed but not built
([Step 1](../plans/instance_incremental_sync/instance_incremental_sync_step1_solution_assessment.md)).
This is closer to Usenet peering than to Nostr relays, and it is deliberate:
treyd's criticism that Nostr has no decoupling between data and server does not
apply, because the data is a Git repository that any host can carry.

### 2. Censorship by the operator, and proving it

v3 does not promise that an operator cannot remove content. It promises that
removal leaves evidence and that the community can leave. Operator removal and
author retraction are both committed to Git with a retained, signed record and a
plain-text acceptance receipt that contains no deleted prose
([post retraction](../reference/author_post_retraction.md)). Deletions are
therefore visible commits, which answers supermatt's point that relay censorship
cannot be proven. Backups include full history, and a new steward can rebuild
from the record alone ([10 reasons, item 1](10_reasons_to_consider_v3.md)).

Government-scale blocking is out of scope for a single website, as it is for a
relay. v3's position on pjc50's illegal-content question is the opposite of
Nostr's: the operator can and must delete. Immediate content deletion with a
retained record is the current direction, with a later purge of Git history as a
separate release ([retraction handoff](../plans/retraction_simplification_handoff.md)).
Copies already downloaded by others cannot be recalled, and the docs say so.

### 3. Incentives

v3 removes the relay operator from the picture rather than paying them. The
community hosts its own instance, and the stack is chosen so that one instance
keeps running for years with little upkeep and no outside services: PHP, SQLite,
Git, Apache, plain HTML, OpenPGP
([longevity principles](../../PRINCIPLES.md#stack-and-longevity)). Optional
features that need a vendor, such as LLM responses, must be safe to turn off.
There is no payment layer and no plan for one. The remaining incentive problem
is human: someone has to be the steward, and the product docs name a named
successor as a pilot requirement
([best-fit audiences](v3_best_fit_audiences.md)).

### 4. Key management

The thread's strongest objection is answered in v3 by refusing to make the key
the user's problem on day one.

- **No key ceremony.** On instances that enable it, the browser prepares a
  `guest` keypair silently; the first post, reply or Like registers it. No
  dialog asks for a name ([authentication modes](../reference/authentication_modes.md)).
  This is the bawolff position (an app that stores the key as a file and does
  not talk about it) without handing custody to a server.
- **Keyless participation is kept, not phased out.** Anonymous posts and
  hint-only votes stay possible; abuse is handled by recording how much trust an
  action carries, not by requiring a key
  ([principle](../../PRINCIPLES.md#keep-participation-open-to-people-without-a-key)).
  This directly rejects rglullis's "all or nothing" complaint about Nostr.
- **Accommodate first, verify later.** An unverified action is accepted and
  marked; only verified actions count where trust matters
  ([principle](../../PRINCIPLES.md#accommodate-first-verify-later)).
- **Backups a person can hold.** Account offers a passphrase-encrypted copy of
  the key as a text file or as a Starfield PNG, verified locally before
  download, and a restore flow on another device
  ([key backups](../reference/key_backups.md), [SFENC](../specs/sfenc.md)).
- **One user, many keys.** A username groups every approved key that shares it.
  A new device's key joins once a member approves it. Losing one browser is
  therefore not losing the identity, which is the "who do you call" answer: you
  call a member who knows you. This also answers nunobrito's rotation point;
  OpenPGP keys can expire and be replaced, and the username outlives any one
  key.
- **The server never holds the private key**, even to make recovery easier
  ([principle](../../PRINCIPLES.md#private-keys-stay-in-the-members-browser)).

What remains unfinished is listed in the last section: lost-all-keys recovery,
guided device transfer, and who may approve a key into someone else's username.

### 5. A signature is not an identity

v3 says exactly what camgunz says. "A signature proves control of a key, not
that a claim is true or who someone is in real life. Say exactly that much and
no more" ([principle](../../PRINCIPLES.md#authorship-is-shown-by-signature-not-by-display-name)).
Usernames are not unique; the fingerprint is. Approval, authentication, a saved
key and a published identity are kept as separate states
([principle](../../PRINCIPLES.md#identity-access-and-approval-are-separate)).

### 6. Spam, abuse and moderation

v3 answers bflesch rather than shark_laser: the unit of trust is a community
that vouches for its members, not a filter over a global stream.

- **Membership is a web of trust.** Approved members approve others with signed
  actions; invitations derive approval from the issuer; the approval history is
  public ([10 reasons, item 3](10_reasons_to_consider_v3.md)). An instance can
  be public or approved-members-only for everything including feeds, APIs and
  downloads.
- **Operator seeds the roots.** Trust anchors are explicit records, not a
  default relay list ([user approval seed record](../specs/user_approval_seed_record_v1.md)).
- **Advisory machine scoring.** Fastmod gives posts rubric probabilities that
  stay separate from the canonical record and are advisory until thresholds and
  human overrides are evaluated ([Fastmod](../reference/fast_post_scoring.md)).
- **Labels and reactions are records.** Thread labels and post reactions are
  append-only signed records, which is the same shape as atproto's labels and
  Nostr's NIP-32, scoped to one community
  ([thread label record](../specs/thread_label_record_v1.md),
  [post reaction record](../specs/post_reaction_record_v1.md)).
- **Tone is set by rules, not filters** ([ZenMemes rules](zenmemes_rules.txt)).

The thread's DoS concern (attila-lendvai: spam must be made economically
expensive) is only partly answered; see the gaps.

### 7. "It's just Usenet and PGP"

v3 agrees and treats that as a compliment. The stack principle prefers
components with twenty years of backward compatibility, and OpenPGP is named in
it. Where PGP failed on key distribution, v3 makes the instance the keyserver:
the public key, fingerprint, username and first identity event are bound by an
[identity bootstrap record](../specs/identity_bootstrap_record_v1.md) that
anyone can check, and the server verifies with `gpg` itself
([agent API](../reference/agent_api.md)). A Usenet-reader presentation has
been assessed for Forte
([backlog](../plans/backlog/usenet_forte_agent_interface_step1_solution_assessment.md)).

### 8. Blockchain versus exit

v3 has no ledger and no consensus. Its answer to "federation always
recentralizes" is the research paper's: make complete board state exportable
and reproducible so that exit is cheap, and treat moderator misconduct as an
ordinary fault to route around.

### 9. Data loss and metadata leaks

Nostr heals a wiped relay because many clients hold copies. v3 heals a wiped
server because the operator and anyone else who downloaded the repository hold
copies, and derived state is rebuildable. The difference is that v3 members'
browsers do not replicate the record; see the gaps. On private messages, v3
already states that sender and recipient metadata is visible to the server and
that this is not a blanket confidentiality guarantee
([10 reasons, item 5](10_reasons_to_consider_v3.md)), which is the honest
version of the DM-leak finding.

### 10. Adoption and the ordinary member

v3 is aimed at existing communities with a steward, not at displacing a global
network ([best-fit audiences](v3_best_fit_audiences.md)). The everyday member
is offered a reason that has nothing to do with keys: a home for the group's
recurring discussions that it can keep. Basic reading and posting work without
JavaScript and in old browsers ([principle](../../PRINCIPLES.md#basic-reading-and-posting-work-without-javascript)),
and agents get the same plain-text entry points as people
([BLESSING.md](../../BLESSING.md), [`/llms.txt`](../reference/agent_api.md)).

## Things the thread raises that v3 has not addressed yet

Each item says what exists today and whether a plan already covers it. Items
marked **no plan** have no planning document in `docs/plans/`.

1. **Member-side replication.** Exit only works if someone outside the
   operator holds a recent copy. Today that is a manual Backup download.
   Nothing lets members or a second steward mirror automatically, and a hostile
   operator can simply stop offering downloads. The import command is the
   natural base for a scheduled member mirror, and a signed instance manifest
   would let a mirror prove what it holds
   ([portability assessment](../plans/instance_portability_gap_assessment.md)
   describes the bundle; scheduling was deferred in
   [instance content sync](../plans/instance_content_sync/instance_content_sync_step1_solution_assessment.md)).
   **Partially planned.**
2. **Censorship evidence versus history purge.** Deletions are provable only
   while Git history survives. The planned history purge removes the deleted
   prose and, with it, part of the evidence. The retained retraction and
   removal receipts need to be enough on their own to show what was removed,
   by whom and when, after the purge. The handoff says the record "should
   remain useful after the original content has been purged" but the fields
   are not yet defined ([handoff](../plans/retraction_simplification_handoff.md)).
   **Planned, fields undefined.**
3. **Key revocation and compromise.** The code revokes invitations, not keys.
   There is no way to say "this key is stolen, stop trusting it", and a revoked
   key would still be an encryption recipient for private messages. This is the
   release gate in [feature priorities](v3_feature_priorities.md) and item 4
   there. **Planned as a priority, not started.**
4. **Lost-all-keys recovery.** Same-username approval covers a lost device
   only while another approved key exists. A member with no key left has no
   documented path except asking a member to approve a brand-new key, which
   the backlog notes an ordinary member should not be able to do for someone
   else's existing account. A recovery quorum of members is sketched there
   ([key approval policy](../plans/backlog/account_key_approval_policy_findings_and_recommendations.md)).
   **Backlog option, undecided.**
5. **Write-side abuse limits.** The agent API documents that the code has no
   rate limits, and every reaction or post is a Git commit, so a flood grows the
   repository and rebuild time without bound. The proof-of-work guest key idea
   and a compaction pass for old reactions are both noted but unscheduled
   ([guest keypair and PoW](../plans/guest_identity_proof_of_work_voting.md)).
   High-traffic mode protects reads, not writes. acdha's point that PoW is weak
   against botnets applies here too; per-identity and per-address limits are
   the missing complement. **Noted, unscheduled.**
6. **Reader-side filtering.** There is no mute, block, or ignore list, and no
   way to subscribe to another member's labels the way atproto users subscribe
   to labelers. Moderation is community-level only. Thread labels are the
   record type that could carry it. **No plan.**
7. **Human moderation workflow.** No moderation queue, no member suspension,
   no thread locking, no reversible hiding of human posts with a reason. The
   current flag reducer only hides agent replies. The product docs rate this as
   the release gate for open growth ([feature priorities, item 4](v3_feature_priorities.md)).
   **Planned as a priority, not started.**
8. **Portable trust between instances.** A key can post to several instances
   through the API, but approval is per instance, and an imported record from
   another instance carries no information about who vouched for its author
   there. Import verifies signatures, not standing. Whether approvals should
   ever travel with records is undecided. **No plan.**
9. **Deletion across instances.** Imports never delete local content, which is
   the right default, but it means a retraction on the source instance does not
   reach copies made by import. The cooperative deletion notes put cross-instance
   propagation in future scope
   ([discussion](../plans/backlog/server_cooperative_content_deletion_discussion.md)).
   **Deferred by decision.**
10. **Discovery beyond one instance.** v3 has RSS, `/llms.txt`, plain-text
    APIs, and a planned [site text search](../plans/site_text_search/site_text_search_step1_solution_assessment.md), but no
    answer to "how does someone find this community or follow one person across
    communities". If that is a non-goal, it should be written into
    `PRINCIPLES.md` so it is not re-argued; if not, the import feed is the
    starting point. **Decision needed.**
11. **Steward succession.** The incentive answer depends on a second person who
    can take over. Guided setup, backup drill and handover are item 8 in
    [feature priorities](v3_feature_priorities.md). **Planned, not started.**
12. **Legal takedown posture.** The operator can remove content and will be
    able to purge history, but there is no runbook stating what an operator
    must do on a legal demand, what the offered downloads retain, and how
    mirrors are notified. **No plan.**

Items 1, 2, 5 and 8 are the ones the Nostr thread makes most pointed, because
each is where v3's "the record is portable" promise meets a case the docs do not
yet cover.
