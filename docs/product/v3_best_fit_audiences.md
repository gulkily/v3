# Groups and individuals best suited to v3

v3's strongest initial audience is an existing community with valuable recurring discussions, membership grounded in relationships, and a technical steward. The best early adoption begins with a durable discussion or archive alongside the group's existing coordination habits.

This assessment reflects repository state at `b0bf4172`, reviewed on October 10, 2026. Audience fit is a product hypothesis to validate through pilots, not a claim about demonstrated adoption or market size. The companion [blog](10_reasons_to_consider_v3.md) explains the benefits; [feature priorities](v3_feature_priorities.md) address the adoption barriers.

## The strongest initial audience

Prioritize a local club, makerspace, creative collective, or founder and researcher circle that meets repeatedly and has a maintainer inside the group. These communities combine three favorable conditions: people already have reasons to talk, organizers can make membership approvals meaningful, and past conversations have value for newcomers.

The person choosing v3 is likely an organizer frustrated by repeating context or a technical member concerned about preserving the group's history. The everyday member needs a much simpler proposition: a reliable place to find the discussion, reply, and return later.

The initial pitch should therefore be concrete: **give the group's recurring discussions and history a home it can keep.** A cryptographic identity or Git repository helps deliver that promise, but is rarely the ordinary member's reason to participate.

## Priority groups

| Group | Recurring need | Why v3 fits | Barrier to address in a pilot |
| --- | --- | --- | --- |
| Local clubs, makerspaces, and creative collectives | Preserve project discussions and connect them to recurring gatherings | Threads, invitations, approvals, optional event details, and adaptable presentation | Members may miss replies without notifications; event attendance workflows are incomplete |
| Founder circles, shared workspaces, and relationship-based local networks | Exchange introductions, questions, resources, and institutional knowledge | Visible membership approvals, profiles, public or members-only instances | Recovery on another device and understandable approval practices |
| Public reading groups and independent research circles | Keep arguments, responses, and references accessible over time | Parent-linked replies, stable links, exportable records, optional post-level AI explanations | General search, citations and formatting, and the absence of a mature editing workflow |
| Quote archives and communities preserving a text collection | Preserve material while allowing browsing and new contributions | QDB presentation, quote search, voting, repository archives, and an existing QDB import path | Other archive formats need import work; moderation and preservation policies need an owner |
| Developer communities and small technical projects | Discuss decisions and build tools around their own record | Plain-text APIs, RSS, SQLite access, signed records, and self-hosting | Search, notifications, and integrations; v3 does not supply a full issue-tracking workflow |
| Student and alumni clubs with continuity across cohorts | Transfer knowledge when leadership changes | Repository exports, rebuild tools, discussions around events, and membership history | A named successor must inherit hosting and backups; casual members need smoother onboarding |

Existing site profiles demonstrate relevant implementation choices; they do not establish that these segments are already successful customers. See [site presentation](../../src/ForumRewrite/ProfilePresentationContent.php), [event support](../plans/mitrapclub_events_experience_step4_implementation_summary.md), and the [QDB import runbook](../runbooks/qdb_archive_import.md).

## Individuals who are especially likely to value it

**The organizer who keeps answering the same question.** They need a link to the previous discussion and a place to build on it. Their success measure is fewer repeated explanations and newcomers finding useful context themselves.

**The archivist or institutional memory keeper.** They care about what survives a leadership transition. Repository downloads, human-readable records, and reconstruction tools are unusually relevant to their work. They need a regular backup routine and a clear distinction between the canonical discussion archive and private operational data.

**The technical steward.** They can run the application, monitor its worker, restore backups, and adapt presentation. This person makes early adoption feasible for everyone else. They need a second person who can take over; relying on one enthusiast would recreate the continuity problem v3 aims to solve.

**The thoughtful but intermittent contributor.** They have useful things to say without wanting to monitor a live conversation all day. Thread structure and public offline reading help. Missing personal notifications and unread tracking are especially costly for this person.

**The member who builds small tools.** APIs, feeds, a downloadable read model, and documented records make custom views and experiments plausible. Treat these as extensions someone can build, not integrations delivered out of the box. [Extension patterns](../examples/extension_improvement_cookbook.md)

**The person who values verifiable authorship.** Signed contributions can preserve attribution to a key. The relevant benefit is inspectability; neither signatures nor membership approvals establish the truth of a statement or guarantee real-world identity. [Trust model](../architecture/public_architecture_and_trust.md)

## Good fits with specific conditions

**Members-only social groups** can use the instance-wide access gate, but should begin with ordinary community discussion. Membership governance and device recovery need improvement. Private-message encryption also depends on which keys have been approved under a username; access-controlled board content is not end-to-end encrypted. [Access configuration](../../README.md#local-run) and [recipient-key selection](../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php)

**Multilingual communities** can enable visible Unicode prose, with emoji controlled by an additional flag. Those defaults are currently off, and machine identifiers remain constrained. Validate the actual languages, input methods, and reading experience before adopting. [Feature definitions](../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php)

**People with intermittent connectivity** benefit when recent public discussions are enough. Offline reading is bounded, private instances do not support it, and queued delivery requires an appropriate page to be open. It is a useful accommodation, not a general offline collaboration system. [Offline boundaries](../runbooks/offline_reading.md)

**Music and other creative communities** have event and media-link building blocks, but media embeds do not amount to an upload library. A group exchanging large files, private recordings, or complex portfolios will need additional tools.

## Audiences to defer

| Audience | Why current v3 is a difficult fit |
| --- | --- |
| Groups whose main activity is continuous live chat, voice, or video | Personal alerts, presence, live message delivery, group messaging, and voice/video are not a complete product here |
| High-volume public communities with frequent adversarial behavior | Approval is not a complete moderation lifecycle; human-content enforcement and member suspension need a coherent interface |
| Groups whose central requirement is high-assurance confidential communication | Key membership and recovery need stronger guarantees, message metadata remains visible to the server, and browser-delivered code remains a trust boundary |
| Organizations requiring enterprise administration | SSO, a granular role model, and departmental permissions are not established capabilities in this implementation |
| Communities without anyone willing to operate the service | Documentation and a familiar stack still leave hosting, updates, restore drills, and worker maintenance to someone |
| Creators whose primary need is paid membership or a media business | Billing, subscriptions, media management, and monetization workflows are not the core offering |

These are scope judgments from the current application routes, stores, and interfaces. They do not predict whether v3 could serve these groups after further development.

## A practical way to choose early pilots

Select groups that can name a discussion they wish they had preserved, identify who will steward the installation, and recruit a small set of members to use it repeatedly. Avoid asking a new platform to create the community's reason to exist.

Begin with one recurring activity: a weekly reading discussion, project review, meeting follow-up, or curated quote collection. Keep urgent coordination in the group's established channel while evaluating the forum. Use public access only for material intended to be public.

Over four weeks, look for evidence that:

1. New members can join and make a first contribution without repeated intervention.
2. Participants return and respond after the organizer's initial invitation.
3. Someone reuses an older thread to answer a new question.
4. Members can resume participation on a second device with understandable assistance.
5. The steward can export and restore the discussion record into a separate instance.

Treat these as proposed evaluation criteria, not existing product metrics. Record failures in the members' own terms: “I did not know anyone replied,” “I could not find the answer,” or “my account disappeared.” Those observations should determine whether notifications, search, or identity recovery is the immediate next investment.

For the first pilots, recruit an organizer and a technical steward together. The organizer supplies the reason to return; the steward makes that return dependable.
