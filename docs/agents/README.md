# Agent instructions

Procedures for agents working on this project: follow the one that matches the task. These are instructions for development work; the prompts the application itself loads at runtime live in [`prompts/`](../../prompts/) and are not listed here.

- [Feedback triage](feedback_triage.md): after `./v3 import-instance`, turn new site feedback into feedback item changes.
- [Thread to pull request](thread_to_pr.md): when someone pastes a ZenMemes thread asking for a change, build it, open a PR, and reply in the thread. The person-facing side of this is [Contribute with an agent](../contribute_with_an_agent.md).
- [Claude posting skill](claude_zenmemes_skill.md): the source of Claude's `zenmemes-posting` skill, which posts through `public/frm.py` and reports trouble to one standing thread. Edit it here, then install the same text in the skill.
