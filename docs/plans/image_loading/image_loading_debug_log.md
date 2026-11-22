# image_loading Railspike debug log

## 2026-11-22T16:30Z — Step 1

### Necessary/useful instructions

- Host `AGENTS.md`, inspect existing implementations first: reading `ImageStore` showed images are kept outside the web folder on purpose, which turned "serve without PHP" from a speed fix into a security trade-off.
- `PRINCIPLES.md`, a flag for security trade-offs: shaped Option A's flag, off by default.
- Host `AGENTS.md`, search feedback before a feature: found PF-20261111-F14 for the offline part.
- Host `AGENTS.md`, a reported failure is replayed or reported unverified: the blank image in the offline web app is recorded as not explained.
- `step1_solution_assessment.md`, Original Query preservation: the request spans two messages with an assistant list between them; the bracketed note says what "all 3" means.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- None found.

### Uncertain assessments

- The maintainer asked for all three fixes; Option B does less than that. It is kept as an alternative because it avoids the access trade-off, and the recommendation stays with what was asked.
- Timings were taken from a distant workspace and can't separate PHP from network time.
