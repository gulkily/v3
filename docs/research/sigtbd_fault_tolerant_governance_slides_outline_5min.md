# Slide Deck Outline: 5-Minute Version

## Title

Byzantine Fault-Tolerant Governance for Internet Message Boards

Subtitle: Exit-oriented architecture under adversarial moderation

## Talk Goal

Deliver a strong short talk that presents one clear systems claim, one concrete mechanism, and one memorable punchline.

## Runtime

- Target: 5 minutes
- Expected actual runtime: 5:15 to 6:00

## Slide 1: Title

**Content**

- Byzantine Fault-Tolerant Governance for Internet Message Boards
- Exit-oriented architecture under adversarial moderation

**Speaker note**

Open in full deadpan. Present the topic as though it is an entirely standard distributed systems paper.

## Slide 2: Problem

**Header**

Message boards have a governance availability problem

**Content**

- A large number of users are governed by a very small number of moderators.
- This architecture assumes those moderators remain reasonable forever.
- This is not a safe systems assumption.

**Speaker note**

Set up the problem clearly and quickly.

## Slide 3: Threat Model

**Header**

Threat model

**Content**

- hostile maintainers
- moderator coups
- non-consensual platform updates
- burnout, disappearance, selective export denial

**Footer line**

We model these as ordinary infrastructure faults.

**Speaker note**

This is one of the key joke slides. Deliver it with no wink.

## Slide 4: Core Idea

**Header**

The internet routes around damage

**Content**

- Packet loss is damage.
- Link failure is damage.
- Censorship is damage.
- Communities should also be able to route around moderator damage.

**Speaker note**

This is the conceptual bridge for the whole talk.

## Slide 5: System

**Header**

Portable community state

**Content**

- repository archive with canonical content and history
- SQLite read-model for local browsing and inspection
- complete board snapshot, not a courtesy export

**Footer line**

Forking is not governance failure. Forking is failover.

**Speaker note**

This is the main mechanism slide. Keep the wording crisp.

## Slide 6: Governance Result

**Header**

Cheap exit changes power

**Content**

- admins can control an instance, but not necessarily the future
- users inherit a practical means of refusal
- bad governance becomes less final

**Speaker note**

This is the core contribution slide.

## Slide 7: Emergent Property

**Header**

Administrative horizontal scaling

**Content**

- under repeated schism, forks proliferate
- operational sovereignty becomes cheaper
- admin-to-user ratio may exceed 1.0

**Footer line**

We treat this as a success condition.

**Speaker note**

This is the punchline. Pause before the footer line.

## Slide 8: Conclusion

**Header**

Byzantine Fault-Tolerant Governance for Internet Message Boards

**Content**

- moderator authoritarianism is ordinary infrastructure failure
- communities should be able to route around moderator damage
- portable state makes governance failure less final
- forking is failover

**Footer line**

Questions, forks, and constitutional crises

**Speaker note**

End here and leave this slide up for questions. Do not switch to a blank slide or a pure thank-you slide.

## Suggested Timing

- Slide 1: 0:20
- Slide 2: 0:40
- Slide 3: 0:45
- Slide 4: 0:45
- Slide 5: 0:55
- Slide 6: 0:45
- Slide 7: 0:45
- Slide 8: 0:25

## Optional Backup Slides

### Backup 1: Example Recovery Sequence

- moderator changes rules unilaterally
- users disagree
- snapshot already exists
- board is reconstructed elsewhere
- legitimacy follows users rather than machine access

### Backup 2: One-Sentence Thesis

We present an exit-oriented architecture for internet message boards that treats administrator misconduct as a routine fault model and community forking as a recovery primitive.

## Visual Guidance

- Keep the visual style restrained and academic.
- Prefer one idea per slide.
- Use one simple diagram for snapshot -> fork -> continued board.
- Use one simple chart for admin-to-user ratio over time.

## Non-Negotiable Lines

- We model these as ordinary infrastructure faults.
- Communities should be able to route around moderator damage.
- Forking is not governance failure. Forking is failover.
- We treat this as a success condition.
