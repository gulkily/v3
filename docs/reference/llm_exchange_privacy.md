# LLM payload cleanup after retraction

Saved LLM exchanges may remain visible after a post is deleted. Retraction no longer performs fail-closed visibility checks or revalidates every late provider response against repository history. The next successful [maintenance sweep](retraction_sweep.md) scrubs affected request, response, error and provider metadata; readers then hide the scrubbed exchange.

Existing dependency manifests identify direct and contextual post dependencies. Known unrelated exchanges remain unchanged. When legacy dependencies cannot be determined and accepted retractions exist, the payload is conservatively scrubbed instead of retained indefinitely. This can remove unrelated old diagnostic data. No new provenance or progress ledger is introduced.

Late results can arrive after a sweep and are handled by later full scans. The existing queue discovers work every five minutes, subject to backlog and failures; there is no all-copy availability deadline. Review task-queue failures or run `./v3 retraction-sweep run` manually. SQLite readers can delay WAL checkpoint completion; retry after they finish.

Published replies/quotes, external provider logs, backups and Git history are outside this local payload cleanup. See [retraction acceptance, migration and recovery](author_post_retraction.md).
