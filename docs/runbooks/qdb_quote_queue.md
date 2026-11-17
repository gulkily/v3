# QDB quote queue

Turns on moderator approval for new qdb quotes. Background: [quote queue concept](../concepts/quote_queue.md).

## Turn it on

1. Deploy a release that includes the queue.
2. Sign in with an operator key and open Tools → Feature Flags.
3. Turn on **QDB quote queue** (`FORUM_QDB_QUEUE_ENABLED`). The page signs the change; that first signed switch-on is the queue's start time.
4. Do not set `FORUM_QDB_QUEUE_ENABLED` in the environment or private config instead. Without a signed switch-on record there is no start time, and nothing waits.

Quotes created before the start time stay listed. Turning the flag off and on again keeps the first start time.

## Check it on the live site

1. In a private window, add a quote. The quote's page says "Pending approval".
2. Open `/queue`: the quote is there. Open `/latest`: it is not. The footer reads "N quotes approved; M pending".
3. In the operator browser, open `/queue` and press Approve. Reload: the quote is on `/latest` and gone from `/queue`.
4. Add a second test quote and press Reject. It leaves `/queue`, and its address returns 404.
5. Remove the rejected test quote from the repository with `./v3 delete-thread <thread-id>`.

## If something is wrong

- Approve and Reject are missing: this browser does not hold an active operator key. Sign in with one.
- "Only an operator signing in this browser can approve or reject quotes.": the browser has no private key, or the key is not an active operator.
- Every new quote is listed at once: the flag is off, or it was set outside Tools → Feature Flags. Check `records/feature-flag-changes/` for a signed `FORUM_QDB_QUEUE_ENABLED` change.
- To stop moderating, turn the flag off. Every quote is listed again; nothing is deleted.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
