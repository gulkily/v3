# 10 reasons to consider v3 for your community's discussion space

Someone in your community writes a useful explanation. A month later, someone else asks the same question. The answer still exists somewhere, but finding it means asking the right person, remembering the right phrase, or scrolling through conversations that have moved on.

Then an organizer leaves. A new cohort arrives. A project restarts. The group has accumulated years of knowledge, but passing it on still depends on whoever remembers where everything went.

Choosing a discussion platform means deciding what happens to that knowledge.

v3 is a self-hostable forum built around plain-text records, browser-held identity keys, and a history the community can inspect and take with it. It supports threaded discussion, membership approvals, private messages, and optional AI assistance. Its strongest case is for communities that expect their conversations to matter beyond the moment they happen.

Here are ten reasons to consider giving yours a home there.

## 1. Your community can keep its history when its circumstances change

A student club changes officers. A reading group changes organizers. A volunteer who maintained the website moves away. A server needs replacing.

These are ordinary events in the life of a community. They should not put its accumulated work at risk.

v3 stores its canonical discussion records as plain-text files in Git, which tracks their history. Its database and generated pages are built from those records. The Backup page offers downloads of the content repository, including its Git history, and the read-model database; operator tools can import a repository archive and rebuild the site. [Architecture and backup details](../architecture/public_architecture_and_trust.md)

That gives a community something concrete to hand to its next steward: the record and a way to reconstruct its discussion space. Preservation still takes backups and someone responsible for them, but continuity is part of the design.

## 2. A conversation has a shape that survives your absence

Imagine a makerspace discussing a new workshop. One person asks about equipment, another raises accessibility concerns, and a third volunteers to teach. A useful discussion needs to preserve which answer belongs to which question.

v3 gives posts a thread, replies an explicit parent, and individual contributions their own links. Tags help group related discussions. A member can point a newcomer to the relevant exchange instead of retelling it from memory. [Post and reply structure](../specs/canonical_post_record_v1.md)

That is particularly valuable when participation is intermittent. People with jobs, families, or different schedules can contribute to the same conversation without being present at the same hour.

There is still work to do on general search and personal catch-up tools. The structure already gives the conversation a durable address; making that address easier to rediscover is a clear next step.

## 3. Membership can grow through people who know one another

A neighborhood group, creative circle, or shared house often already has a way of deciding who belongs: someone knows you, brings you along, and vouches for you.

v3 makes that relationship explicit. Approved members can approve other identities through signed actions, and invitations help people join. User and activity views expose the approval history. The operator establishes the initial trust anchors. [Signed approvals](../plans/archive/signed_user_approvals_step4_implementation_summary.md)

For a community built through relationships, this can make membership understandable: who introduced someone, who approved them, and how the group expanded.

It works best where people take vouching seriously. An approval records a judgment; the community still has to exercise that judgment and handle disagreements.

## 4. Authorship can be checked beyond a displayed username

A familiar name is helpful. A verifiable signature provides a different kind of evidence.

With v3's browser identity, the browser creates and stores an OpenPGP private key. Signed writes can be verified against the corresponding public key. The public record can include the signature and the identity material needed to check it. [Identity and trust model](../architecture/public_architecture_and_trust.md)

For a research circle, that can preserve attribution to a particular key. For an organizer, it makes an approval attributable. For someone building tools around the archive, it provides material they can inspect independently.

A signature demonstrates control of a key, not that a claim is true or that the signer has a particular real-world identity. Members also need to preserve their keys: copying and restoring a key is available, while a smoother recovery and device-transfer experience remains unfinished.

## 5. You can decide whether the shared discussion is public or members only

Some groups want visitors to read their discussions before joining. Others want a space for an existing membership.

v3 supports both public instances and an approved-members-only mode. In the latter, unapproved visitors are limited to the Lobby, Account, and their own profile; the access gate covers content routes, feeds, APIs, downloads, and generated artifacts. This is a choice for the whole instance. [Access configuration](../../README.md#local-run)

Approved users can also exchange private messages. The browser signs and encrypts the message, and the server stores an encrypted envelope plus routing metadata separately from the public record. [Private messaging](../plans/private_messaging/private_messaging_step4_implementation_summary.md)

These mechanisms serve different purposes. A members-only board is access-controlled, while private-message bodies are encrypted in the browser. Private-message recipients currently include all approved keys grouped under the same username, making careful key approval essential. This is a useful community messaging feature with a specific trust model, rather than a blanket confidentiality guarantee.

## 6. A lost connection does not have to end your reading

You open a discussion before a train journey. The connection drops halfway through. Being able to finish reading should not require another signal bar.

On public instances, v3 can save a bounded snapshot containing visible pinned threads and up to 50 additional recently active threads, with their visible replies, subject to a 25 MiB limit. Supported Board, Tags, and saved thread URLs remain readable offline. [Offline reading](../runbooks/offline_reading.md)

There is also a local Outbox for Likes and reply or thread drafts. A compose page already open can save a draft; queued work can be delivered after reconnection while an Outbox or offline-reader page is open. Drafts require an explicit decision to queue them.

The boundaries matter: this is a recent public reading set, not the entire archive; members-only offline reading is not supported; a closed browser cannot deliver the queue. Within those boundaries, the discussion can accompany people through an unreliable connection.

## 7. The space can reflect what your community actually does

A quote archive, a music club, and a technical discussion group should have room to express different identities.

v3 has site profiles and themes, including a quote-oriented presentation, a club presentation, and a multipane forum interface called Forte. Optional event fields put a date, time, location, and link alongside a discussion. Configurable media features provide cards and supported inline previews for recognized links. [Themes](../runbooks/theme_development_guide.md) and [feature options](../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php)

For a club, that means the invitation to an event and the conversation around it can occupy the same place. For an archive, the entries can receive a presentation suited to browsing them.

These are implemented building blocks, with some features disabled by default. They give a technically supported community a starting point for a space that feels like its own.

## 8. AI assistance can sit beside the contribution it helps explain

A member posts a dense argument. Someone else wants a simpler explanation. A third person would benefit from a constructive counterpoint.

v3 lets approved readers request response modes including logic analysis, summary and key takeaways, simple explanation, constructive counterpoint, and explanation of a joke or reference. The request is tied to a post, and generated replies appear under an agent identity. [Available response modes](../../src/ForumRewrite/Agent/AgentResponseTask.php)

The potential benefit is shared assistance: an explanation can become part of the discussion for the next reader too. These requests use bounded post context, so a requested summary should not be mistaken for a comprehensive summary of the whole community archive.

AI features require provider configuration and a running worker. Operators can control them separately, and the selected provider receives the context needed for enabled requests. The community should choose whether and where that assistance belongs.

## 9. Your members can build on the discussion record

A technically inclined member may want to make a reading list, analyze the archive, or build a new way to browse it.

v3 exposes plain-text read APIs, RSS activity feeds, downloadable SQLite data, and a browser SQL viewer. It also provides machine-readable entry points for agents. The underlying records and documented formats give extensions something concrete to work with. [Extension cookbook](../examples/extension_improvement_cookbook.md)

A community does not need to build any of those extensions to start participating. But if a useful idea emerges later, it has a practical route to experimentation.

This is especially appealing to research groups, developers, and archivists whose questions change over time. They can ask new questions of their own material without waiting for a particular interface feature.

## 10. A technically supported group can operate it on familiar infrastructure

Self-hosting is easier to evaluate when the operating model is legible.

v3 uses PHP, Git, and SQLite, with an Apache deployment path and generated HTML for eligible reads. The project documents deployment, rebuilding derived data, diagnosing stale state, and recovering from operational failures. Optional background work runs through an internal task queue. [Deployment](../runbooks/production_deploy.md) and [recovery](../runbooks/operator_recovery.md)

For a group with a capable maintainer, that offers a realistic basis for running and adapting its own discussion space. It still requires host validation, updates, backups, and attention to performance; actual operating cost depends on the deployment and enabled services.

The practical appeal is that responsibility can be understood and transferred. A community can choose its steward and preserve the means for the next one to take over.

## Start with a conversation worth keeping

v3 is most compelling for a club, local network, research circle, or archive with recurring discussions, relationships that matter, and someone willing to maintain the space.

It is still developing. Personal notifications, general search, easier identity recovery, and broader moderation controls need attention. A group that depends on instant alerts, voice rooms, or mature mobile chat workflows should account for those gaps before moving its daily coordination.

A useful first step is smaller: put one recurring discussion on v3. Give it an owner, invite the people who contribute to it, and see whether the resulting record helps someone who was not there at the beginning.

That is the promise worth testing: your community's next conversation can make its accumulated knowledge easier to inherit.
