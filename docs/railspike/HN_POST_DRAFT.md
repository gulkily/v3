# HN Post Draft

## Title

Show HN: Railspike — review and reshape agent plans in Markdown

## Body

An agent’s first plan often needs discussion: question an assumption, combine parts of two approaches, or cut scope before work begins. I made Railspike to give that discussion a persistent home in Markdown files, with explicit approvals between steps.

Railspike is a set of Markdown instruction files. An agent produces a concise assessment when needed, requirements, and a staged plan. You can edit the files or ask for revisions, combine compatible Step 1 options, and refine the recommendation before approving each step. Only after Step 3 does implementation begin on a feature branch. The approved plans are grouped in a feature folder and committed with the necessary navigation repairs first, and each implementation stage gets its own commit plus an updated verification summary.

The intended audience is people using Codex, Claude Code, or Copilot for planning, documentation, development, bug fixes, test suites, and checklists. A planning handoff can be useful even when implementation is deferred. It adds process, so it is probably excessive for a trivial edit or a one-line fix.

The [quick start](https://github.com/gulkily/railspike#quick-start) covers installation, the first prompt, and the approval loop.

This is not an autonomous-agent framework, a replacement for engineering judgment, or a universal process. It does not prevent hallucinations or guarantee a good design; it makes the decisions, risks, approval points, and verification record easier to inspect.

I would especially value feedback on: does the option-and-revision loop help you shape the work, where do the approval gates feel too rigid, and which agent integrations would make it more useful?
