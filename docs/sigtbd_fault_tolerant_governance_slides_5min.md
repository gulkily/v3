# Byzantine Fault-Tolerant Governance for Internet Message Boards
## Exit-oriented architecture under adversarial moderation

We / MIT

Notes:
- Open in full deadpan.
- Present this as a normal systems talk.

---

# Message Boards Have a Governance Availability Problem

- A large number of users are governed by a very small number of moderators.
- These moderators can delete content, change rules, and alter platform behavior.
- The dominant architecture assumes they remain reasonable forever.

Notes:
- Start with the ordinary setup.
- "This is not a safe systems assumption" is the line to land.

---

# Threat Model

- hostile maintainers
- moderator coups
- non-consensual platform updates
- burnout, disappearance, selective export denial

**We model these as ordinary infrastructure faults.**

Notes:
- This is one of the central joke slides.
- Do not play it broadly.

---

# The Internet Already Knows What To Do With Damage

- Packet loss is damage.
- Link failure is damage.
- Censorship is damage.
- Communities should also be able to route around moderator damage.

Notes:
- This is the conceptual bridge for the whole talk.
- Move quickly to the last bullet.

---

# Portable Community State

- Repository archive with canonical content and history
- SQLite read-model for local browsing and inspection
- Complete board snapshot, not a courtesy export

**Forking is not governance failure. Forking is failover.**

Notes:
- Tie this to the actual implementation.
- This is the mechanism slide.

---

# Cheap Exit Changes Power

- Admins can control an instance, but not necessarily the future.
- Users inherit a practical means of refusal.
- Bad governance becomes less final.

Notes:
- This is the contribution slide.
- Keep it crisp and serious.

---

# Administrative Horizontal Scaling

- Under repeated schism, forks proliferate.
- Operational sovereignty becomes cheaper.
- The admin-to-user ratio may exceed `1.0`.

**We treat this as a success condition.**

Notes:
- Pause before the last line.
- Let the room catch up.

---

# Byzantine Fault-Tolerant Governance for Internet Message Boards

- Moderator authoritarianism is ordinary infrastructure failure.
- Communities should be able to route around moderator damage.
- Portable state makes governance failure less final.
- Forking is failover.

Questions, forks, and constitutional crises

Notes:
- End here.
- Leave this slide up for questions.
- Do not switch to a thank-you slide.
