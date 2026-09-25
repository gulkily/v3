# Slide Deck Outline: Byzantine Fault-Tolerant Governance for Internet Message Boards

## Talk Goal

Present a deadpan-comedic systems talk that argues moderator despotism should be treated as routine infrastructure failure, and that cheap community exit is a technical recovery mechanism rather than a social breakdown.

## Tone

- Serious academic delivery.
- Minimal wink-at-the-audience phrasing.
- Let the absurdity come from normal systems language applied to moderators.
- Use one major joke per slide at most.

## Slide 1: Title

**Title**

Byzantine Fault-Tolerant Governance for Internet Message Boards

**Subtitle**

Exit-oriented architecture under adversarial moderation

**Speaker note**

Open as if this is an entirely normal distributed systems problem.

## Slide 2: Problem Statement

**Header**

Message boards have a governance availability problem

**Content**

- Most communities are governed by a very small number of moderators.
- These moderators can delete content, change policy, and alter platform behavior.
- The dominant architecture assumes they will continue being reasonable forever.

**Speaker note**

Set up the premise in plain language. No joke yet beyond the mismatch between trust assumptions and reality.

## Slide 3: Threat Model

**Header**

Threat model

**Content**

- hostile maintainers
- moderator coups
- non-consensual platform updates
- operator disappearance or burnout
- selective export denial

**Footer line**

We model these not as exceptional social events, but as ordinary infrastructure faults.

**Speaker note**

Deliver this very dryly. This is one of the core joke slides.

## Slide 4: Prior Art

**Header**

The internet already knows what to do with damage

**Content**

- Packet loss is damage.
- Link failure is damage.
- Censorship is damage.
- The internet routes around damage.

**Speaker note**

Introduce the “internet sees censorship as damage and routes around it” idea directly here.

## Slide 5: The Missing Step

**Header**

Communities do not route around moderator damage

**Content**

- Boards are often technically centralized even when socially distributed.
- Users can theoretically leave, but usually cannot take complete state with them.
- This makes governance disputes sticky, emotional, and final.

**Speaker note**

The serious claim is that governance fragility is partly an architecture problem.

## Slide 6: Thesis

**Header**

Main claim

**Content**

We present an exit-oriented architecture for internet message boards that treats administrator misconduct as a routine fault model and community forking as a recovery primitive.

**Speaker note**

Put the thesis line up full-screen. Read it slowly.

## Slide 7: System Design

**Header**

Design principle

**Content**

- Community continuity should not depend on current administrators.
- Complete board state should be exportable.
- Reconstruction should be possible outside the control surface of the live instance.

**Speaker note**

This is the engineering slide that makes the premise feel real.

## Slide 8: Artifacts

**Header**

Portable community state

**Content**

- Repository archive with canonical content and history
- SQLite read-model for local inspection and indexing
- Together: complete board snapshot, not a courtesy export

**Speaker note**

Tie this directly to the real project implementation.

## Slide 9: What This Enables

**Header**

Recovery operations

**Content**

- restore
- mirror
- migrate
- fork

**Footer line**

Forking is not governance failure. Forking is failover.

**Speaker note**

This is another big joke line. Keep it straight.

## Slide 10: Open-Source Community Data

**Header**

Making the community itself open source

**Content**

- Software can be forked.
- Infrastructure can be replicated.
- Community state can also be reproducible.

**Speaker note**

This slide should feel slightly provocative but still legible.

## Slide 11: Governance Consequences

**Header**

Cheap exit changes power

**Content**

- Legitimacy is no longer determined solely by server possession.
- Administrators can control an instance, but not necessarily the future.
- Users inherit a practical means of refusal.

**Speaker note**

This is the heart of the “technical solution to governance problem” framing.

## Slide 12: Evaluation Metrics

**Header**

What do we measure?

**Content**

- snapshot completeness
- restoration latency
- migration complexity
- survivability under adversarial moderation

**Speaker note**

Serious systems-paper mode.

## Slide 13: Main Result

**Header**

Main finding

**Content**

Bad governance becomes less final when exit is cheap and state is portable.

**Speaker note**

Pause here. This is the simplest statement of the contribution.

## Slide 14: Emergent Property

**Header**

Administrative horizontal scaling

**Content**

- Under repeated schism, forks proliferate.
- Operational sovereignty becomes cheaper.
- In the limit, the admin-to-user ratio may exceed 1.0.

**Footer line**

We treat this as a success condition.

**Speaker note**

This is the punchiest joke in the deck. Keep it absolutely serious.

## Slide 15: Failure Modes

**Header**

Limitations

**Content**

- fragmentation
- social overhead
- duplicated infrastructure
- arguments that now come with tarballs

**Speaker note**

One slightly less formal line at the end helps the room breathe.

## Slide 16: Discussion

**Header**

What this does not solve

**Content**

- It does not make people wise.
- It does not prevent conflict.
- It does not eliminate bad moderators.
- It makes bad moderators less architecturally decisive.

**Speaker note**

Important grounding slide.

## Slide 17: Closing Claim

**Header**

Conclusion

**Content**

- Moderator authoritarianism should be modeled as ordinary infrastructure failure.
- Communities should be able to route around moderator damage.
- Message boards should be designed to survive their own administrators.

**Speaker note**

This should land as the real thesis, not just a joke.

## Slide 18: Final Slide

**Header**

Thank you

**Content**

Questions, forks, and constitutional crises

## Optional Backup Slides

### Backup 1: Example Fault Sequence

- admin changes rules unilaterally
- users disagree
- exports exist
- board is reconstructed elsewhere
- legitimacy follows users, not machine access

### Backup 2: Terminology

- hostile maintainer
- non-consensual platform update
- moderator damage
- recovery primitive
- operational sovereignty

### Backup 3: Policy Claim

- good governance cannot be guaranteed
- irreversible governance failure can be reduced
- portability lowers the cost of saying no

## Suggested Runtime

- 12 minutes: slides 1-17, about 35-45 seconds each
- 15 minutes: add more examples on slides 8, 11, and 14

## Visual Direction

- Use a restrained academic style, not meme slides.
- Dark text on light background or the existing `light` theme styling.
- One diagram showing:
  - live board
  - downloadable snapshot
  - forked board
- One chart showing admin-to-user ratio increasing over time under repeated schism.

## One-Sentence Talk Summary

When internet communities treat administrators as a trusted center, governance failures become catastrophic; when they treat community state as portable, moderator despotism becomes just another fault to route around.
