# Should v3 do discovery beyond one instance?

Written 2026-10-30 to settle gap 10 in [the Nostr relay debate and how v3 answers it](nostr_relay_debate_and_v3.md). The question: should v3 help people find communities and follow people across instances, or should it declare that a non-goal and record the decision in `PRINCIPLES.md`?

"Discovery" bundles three different things. Each essay below treats them separately where it matters:

1. **Successor discovery.** A community moved, forked or was mirrored. How do its members find the new home?
2. **Person discovery.** One key posts on several instances. How does a reader follow that person everywhere?
3. **Community discovery.** A stranger wants to find a v3 community about their interest. How?

Today v3 has none of the three. It has RSS, `/llms.txt`, plain-text APIs, an operator-run import of another instance's public record, and a planned per-instance text search. The product docs defer "broad federation" behind the participation loop ([feature priorities](v3_feature_priorities.md)).

## For declaring discovery a non-goal

v3's unit is a community that already exists. The best-fit audience is a club, a research circle, an archive with a steward ([best-fit audiences](v3_best_fit_audiences.md)). These people found each other before they found v3. The product promise is a home for conversations they are already having, not an audience they do not yet have. Discovery solves a problem the target users do not report.

Every discovery mechanism reintroduces the thing v3 was built to avoid. A directory is a central party that decides who is listed. A crawler needs instances to announce themselves to it, and whoever runs it ranks them. A follow graph across instances needs a shared identity namespace, and v3 deliberately refuses to make a username global. The Hacker News thread shows where this leads: Nostr's "dumb relays" grew relay lists, outbox hints, labelers and web-of-trust filters the moment reach mattered. bflesch's objection, that nobody with a reputation wants their words in a global stream next to sewage, is answered in v3 by not having a global stream. Reach is what makes spam and moderation hard. A community of a few hundred people with a steward does not have a spam problem at scale; a network does.

The web already does discovery. A v3 instance is a website. Search engines index it, people link to it, RSS carries it, and agents read `/llms.txt`. The old internet discovered communities through links and word of mouth for twenty years before any protocol tried to own the function. Building discovery into v3 duplicates something the web provides for free and that v3 would do worse.

Finally, the priority list is already long and every item on it serves a member who is present today: notifications, search, key recovery, moderation, succession. Discovery serves a hypothetical future member. A principle that says "one instance is one community; the web does discovery" frees every future planning cycle from re-arguing this.

## For pursuing discovery

The exit promise is incomplete without successor discovery. v3 says a community can take its history and leave. But a fork or a mirror that nobody can find is not an exit. When a domain lapses, an operator disappears, or a community splits, members have no in-band way to learn where the record went. The Nostr thread's one undisputed success story was exactly this: a relay wiped its data and users barely noticed, because clients knew other places to look. v3 members know one URL. The [governance paper](../research/sigtbd_fault_tolerant_governance_for_internet_message_boards.md) treats forking as the recovery procedure. A recovery procedure whose output cannot be located is not one.

The pieces already exist and point this way. Instances can import each other's records. A key is portable and can post to several instances through the API. Imports verify signatures, so a record carries its author with it. What is missing is small: a signed, plain-text record inside the repository that says "this community's other homes, mirrors and successors are here", carried in every archive, readable by every importer. That is not a directory. It is the community describing itself in its own record, which is the one place v3 already trusts.

Person discovery has a similar floor. Two instances that already import from each other could show "this key also posts at..." from records they already hold, with no new namespace. It does not require a global graph, only honesty about data already in hand.

Agents make the case sharper. v3 promises to serve language models as participants with documented entry points. An agent that is told "join a v3 community" has no way to find one, and no way to learn that the community it joined last month has moved. The audience v3 singles out as first-class is the one most blocked by the absence of discovery.

The cost of deciding "non-goal" is that the decision is sticky. Communities that outgrow one instance, archives that want to cross-reference, and the first real fork will all hit the wall, and by then the record format will have hardened without a place to put peer information.

## Recommendation

The two essays argue past each other on one axis: who does the looking. The
"for" essay's concrete asks are all facts a community or an author can state
about themselves. The "against" essay's fears are all cases where v3 would have
to go and evaluate other instances. Pull that axis out and the disagreement
resolves into three tiers. Only the middle one depends on configuration.

The project already crosses the "never look outward" line in one place:
`import-instance` is an operator pointing their instance at another by URL and
pulling its public records. Nobody treats that as a violation. So the rule is
not that v3 never looks outward. It is that v3 looks outward only where the
operator names the place.

**Tier 1, always on, in the record: self-description.** A community lists its
mirrors and successors. An author lists where else their key posts. Both are
signed facts in Git that travel with every archive. No flag, because exit
depends on the first and the second is the one Nostr idea the thread never
knocked down: the outbox model is authors publishing where they post and
readers following that list.

**Tier 2, operator opt-in, default off: aggregation.** An instance follows a
list of peer instances its operator typed in and shows what those peers publish
about themselves: name, successors, perhaps recent public threads through the
import path that exists. A club with sister clubs, or an archive that
cross-references two others, turns it on. It depends on no outside service, so
it fits the existing rule that optional features must be safe to switch off.
Allowing it in principle does not schedule it; the feature priorities still
defer broad federation behind notifications, search and key recovery.

**Tier 3, not even as configuration.** Crawling instances the operator did not
name. Ranking other communities' content. A global username namespace. And the
one that matters most: shipping any default peer or registry URL in the code.
treyd's point in the thread was that sticky defaults recentralize. An empty peer
list by default is what keeps tier 2 honest. An aggregating instance can still
grow large and become a de facto directory; the principle cannot prevent that,
only ensure that no community needs it to be found and that leaving costs
nothing.

The resulting principle was added to `PRINCIPLES.md` on 2026-10-30 as
[Communities describe themselves](../../PRINCIPLES.md#communities-describe-themselves-instances-look-outward-only-where-their-operator-points-them).
The demo-friendly version is [How communities find each other](how_communities_find_each_other.md).
Person discovery as a feature, and tier 2, remain unscheduled; the successor
list is the piece the [mirroring proposal](../plans/member_side_mirroring_proposal.md) needs first.
