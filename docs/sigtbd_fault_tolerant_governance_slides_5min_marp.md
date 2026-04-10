---
marp: true
theme: default
paginate: true
size: 16:9
style: |
  section {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 30px;
    padding: 56px 72px;
  }
  h1, h2 {
    font-family: "Trebuchet MS", "Segoe UI", sans-serif;
    color: #111111;
  }
  h1 {
    font-size: 1.55em;
    margin-bottom: 0.35em;
  }
  h2 {
    font-size: 1.05em;
    margin-top: 0;
  }
  ul {
    margin-top: 0.5em;
  }
  strong {
    color: #000000;
  }
  footer {
    color: #444444;
    font-size: 0.48em;
  }
---

# Byzantine Fault-Tolerant Governance for Internet Message Boards
## Exit-oriented architecture under adversarial moderation

We / MIT

<!--
Open in full deadpan.
Present this as a normal systems talk.
-->

---

# Claim

- Message boards have a governance availability problem.
- The usual architecture assumes moderators remain reasonable forever.
- This is not a safe systems assumption.

<!--
Start with the ordinary setup.
Keep it short and declarative.
-->

---

# Threat Model

- hostile maintainers
- moderator coups
- non-consensual platform updates
- burnout, disappearance, selective export denial

**We model these as ordinary infrastructure faults.**

<!--
This is one of the central joke slides.
Do not play it broadly.
-->

---

# Prior Art

- Packet loss is damage.
- Link failure is damage.
- Censorship is damage.
- Communities should route around moderator damage.

<!--
This is the conceptual bridge.
Move quickly to the last bullet.
-->

---

# Mechanism

- repository archive with canonical content and history
- SQLite read-model for local browsing and inspection
- complete board snapshot, not a courtesy export

**Forking is not governance failure. Forking is failover.**

<!--
Tie this to the actual implementation.
This is the system slide.
-->

---

# Result

- admins can control an instance, but not necessarily the future
- users inherit a practical means of refusal
- bad governance becomes less final

<!--
This is the contribution slide.
Keep it crisp and serious.
-->

---

# Emergent Property

- repeated schism produces more forks
- operational sovereignty becomes cheaper
- the admin-to-user ratio may exceed 1.0

**We treat this as a success condition.**

<!--
Pause before the last line.
Let the room catch up.
-->

---

# Byzantine Fault-Tolerant Governance for Internet Message Boards

- moderator authoritarianism is ordinary infrastructure failure
- communities should be able to route around moderator damage
- portable state makes governance failure less final
- forking is failover

Questions, forks, and constitutional crises

<!--
End here.
Leave this slide up for questions.
Do not switch to a thank-you slide.
-->
